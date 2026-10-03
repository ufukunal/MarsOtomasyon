<?php

namespace App\Support\Concurrency;

use App\Exceptions\IdempotencyInProgressException;
use App\Support\Period\PeriodContext;
use Closure;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;

final class IdempotencyKey
{
    public static function run(
        string $key,
        string $action,
        Closure $callback,
    ): mixed {
        PeriodContext::ensureWritable();

        return DB::connection('period')->transaction(
            function () use ($key, $action, $callback): mixed {
                $inserted = DB::connection('period')
                    ->table('idempotency_keys')
                    ->insertOrIgnore([
                        'key' => $key,
                        'action' => $action,
                        'status' => 'processing',
                        'result' => null,
                        'completed_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                $record = DB::connection('period')
                    ->table('idempotency_keys')
                    ->where('key', $key)
                    ->lockForUpdate()
                    ->first();

                if (! $record) {
                    throw new RuntimeException('Idempotency kaydı oluşturulamadı.');
                }

                if ((string) $record->action !== $action) {
                    throw new RuntimeException('Aynı idempotency key farklı action için kullanılamaz.');
                }

                if ($inserted === 0) {
                    if ($record->status === 'done') {
                        return self::decodeResult($record->result);
                    }

                    throw new IdempotencyInProgressException('İşlem aynı istek anahtarıyla halen sürüyor.');
                }

                $result = $callback();
                $encoded = self::encodeResult($result);

                DB::connection('period')
                    ->table('idempotency_keys')
                    ->where('id', $record->id)
                    ->update([
                        'status' => 'done',
                        'result' => $encoded,
                        'completed_at' => now(),
                        'updated_at' => now(),
                    ]);

                return $result;
            },
            attempts: 3,
        );
    }

    private static function encodeResult(mixed $result): string
    {
        try {
            return json_encode(
                ['value' => $result],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Idempotent action sonucu JSON-serializable olmalıdır.',
                previous: $exception,
            );
        }
    }

    private static function decodeResult(mixed $stored): mixed
    {
        if ($stored === null) {
            return null;
        }

        if (is_array($stored)) {
            return $stored['value'] ?? null;
        }

        try {
            $decoded = json_decode((string) $stored, true, flags: JSON_THROW_ON_ERROR);

            return $decoded['value'] ?? null;
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Saklanmış idempotency sonucu okunamadı.',
                previous: $exception,
            );
        }
    }
}
