<?php

namespace App\Support\Concurrency;

use App\Exceptions\IdempotencyInProgressException;
use App\Support\Period\PeriodContext;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;
use Throwable;

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
                $connection = DB::connection('period');
                $inserted = self::insertProcessing($connection, $key, $action);
                $record = self::lockedRecord($connection, $key);

                self::assertRecord($record, $action);

                if ($inserted === 0) {
                    return self::existingResult($record);
                }

                $result = $callback();

                self::markDone($connection, (int) $record->id, self::encodeResult($result));

                return $result;
            },
            attempts: 3,
        );
    }

    public static function runMaster(
        string $key,
        string $action,
        Closure $callback,
    ): mixed {
        $connection = DB::connection('master');

        /** @var array{existing: bool, id: int, result: mixed} $claim */
        $claim = $connection->transaction(function () use ($connection, $key, $action): array {
            $inserted = self::insertProcessing($connection, $key, $action);
            $record = self::lockedRecord($connection, $key);

            self::assertRecord($record, $action);

            if ($inserted === 0) {
                return [
                    'existing' => true,
                    'id' => (int) $record->id,
                    'result' => self::existingResult($record),
                ];
            }

            return [
                'existing' => false,
                'id' => (int) $record->id,
                'result' => null,
            ];
        }, attempts: 3);

        if ($claim['existing']) {
            return $claim['result'];
        }

        try {
            $result = $callback();
            $encoded = self::encodeResult($result);
        } catch (Throwable $exception) {
            $connection->table('idempotency_keys')
                ->where('id', $claim['id'])
                ->where('status', 'processing')
                ->delete();

            throw $exception;
        }

        $connection->transaction(function () use ($connection, $claim, $encoded, $action): void {
            $record = $connection->table('idempotency_keys')
                ->where('id', $claim['id'])
                ->lockForUpdate()
                ->first();

            self::assertRecord($record, $action);

            if ($record->status !== 'processing') {
                throw new RuntimeException('Master idempotency kaydı beklenmeyen durumda.');
            }

            self::markDone($connection, (int) $record->id, $encoded);
        }, attempts: 3);

        return $result;
    }

    private static function insertProcessing(
        Connection $connection,
        string $key,
        string $action,
    ): int {
        return $connection->table('idempotency_keys')->insertOrIgnore([
            'key' => $key,
            'action' => $action,
            'status' => 'processing',
            'result' => null,
            'completed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private static function lockedRecord(
        Connection $connection,
        string $key,
    ): ?object {
        return $connection->table('idempotency_keys')
            ->where('key', $key)
            ->lockForUpdate()
            ->first();
    }

    private static function assertRecord(?object $record, string $action): void
    {
        if (! $record) {
            throw new RuntimeException('Idempotency kaydı oluşturulamadı.');
        }

        if ((string) $record->action !== $action) {
            throw new RuntimeException('Aynı idempotency key farklı action için kullanılamaz.');
        }
    }

    private static function existingResult(object $record): mixed
    {
        if ($record->status === 'done') {
            return self::decodeResult($record->result);
        }

        throw new IdempotencyInProgressException('İşlem aynı istek anahtarıyla halen sürüyor.');
    }

    private static function markDone(
        Connection $connection,
        int $id,
        string $encoded,
    ): void {
        $connection->table('idempotency_keys')
            ->where('id', $id)
            ->update([
                'status' => 'done',
                'result' => $encoded,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private static function encodeResult(mixed $result): string
    {
        $payload = $result instanceof Model
            ? [
                'type' => 'eloquent_model',
                'class' => $result::class,
                'connection' => $result->getConnectionName(),
                'key' => $result->getKey(),
            ]
            : [
                'type' => 'value',
                'value' => $result,
            ];

        try {
            return json_encode(
                $payload,
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

        try {
            $decoded = is_array($stored)
                ? $stored
                : json_decode((string) $stored, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Saklanmış idempotency sonucu okunamadı.',
                previous: $exception,
            );
        }

        if (($decoded['type'] ?? 'value') !== 'eloquent_model') {
            return $decoded['value'] ?? null;
        }

        $class = $decoded['class'] ?? null;

        if (! is_string($class) || ! is_a($class, Model::class, true)) {
            throw new RuntimeException('Saklanmış model idempotency sonucu geçersiz.');
        }

        /** @var Model $model */
        $model = new $class;

        if (is_string($decoded['connection'] ?? null) && $decoded['connection'] !== '') {
            $model->setConnection($decoded['connection']);
        }

        return $model->newQuery()->findOrFail($decoded['key'] ?? null);
    }
}
