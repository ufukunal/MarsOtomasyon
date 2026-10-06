<?php

namespace App\Support\Concurrency;

use App\Exceptions\IdempotencyInProgressException;
use App\Support\Period\PeriodContext;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;
use Throwable;

final class IdempotencyKey
{
    private const STALE_AFTER_HOURS = 24;

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

                if ($inserted === 0 && $record->status === 'done') {
                    return self::decodeResult($record->result);
                }

                if ($inserted === 0 && ! self::claimStaleProcessing($connection, $record)) {
                    throw new IdempotencyInProgressException(
                        'İşlem aynı istek anahtarıyla halen sürüyor.',
                    );
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

            if ($inserted === 0 && $record->status === 'done') {
                return [
                    'existing' => true,
                    'id' => (int) $record->id,
                    'result' => self::decodeResult($record->result),
                ];
            }

            if ($inserted === 0 && ! self::claimStaleProcessing($connection, $record)) {
                throw new IdempotencyInProgressException(
                    'İşlem aynı istek anahtarıyla halen sürüyor.',
                );
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

    private static function claimStaleProcessing(
        Connection $connection,
        object $record,
    ): bool {
        if ($record->status !== 'processing' || ! self::isStale($record)) {
            return false;
        }

        $connection->table('idempotency_keys')
            ->where('id', (int) $record->id)
            ->where('status', 'processing')
            ->update([
                'result' => null,
                'completed_at' => null,
                'updated_at' => now(),
            ]);

        return true;
    }

    private static function isStale(object $record): bool
    {
        if (! isset($record->updated_at)) {
            return false;
        }

        return CarbonImmutable::parse((string) $record->updated_at)
            ->lessThanOrEqualTo(now()->subHours(self::STALE_AFTER_HOURS));
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
                'type' => 'structured_value',
                'value' => self::encodeStructuredValue($result),
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

    private static function encodeStructuredValue(mixed $value): mixed
    {
        if ($value instanceof Model) {
            return [
                '__type' => 'eloquent_model',
                'class' => $value::class,
                'connection' => $value->getConnectionName(),
                'key' => $value->getKey(),
            ];
        }

        if (! is_array($value)) {
            return $value;
        }

        $encoded = [];

        foreach ($value as $key => $item) {
            $encoded[$key] = self::encodeStructuredValue($item);
        }

        return $encoded;
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

        if (($decoded['type'] ?? 'value') === 'eloquent_model') {
            return self::restoreModel($decoded);
        }

        if (($decoded['type'] ?? 'value') === 'structured_value') {
            return self::decodeStructuredValue($decoded['value'] ?? null);
        }

        return $decoded['value'] ?? null;
    }

    private static function decodeStructuredValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (($value['__type'] ?? null) === 'eloquent_model') {
            return self::restoreModel($value);
        }

        $decoded = [];

        foreach ($value as $key => $item) {
            $decoded[$key] = self::decodeStructuredValue($item);
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function restoreModel(array $payload): Model
    {
        $class = $payload['class'] ?? null;

        if (! is_string($class) || ! is_a($class, Model::class, true)) {
            throw new RuntimeException('Saklanmış model idempotency sonucu geçersiz.');
        }

        /** @var Model $model */
        $model = new $class;

        if (is_string($payload['connection'] ?? null) && $payload['connection'] !== '') {
            $model->setConnection($payload['connection']);
        }

        return $model->newQuery()->findOrFail($payload['key'] ?? null);
    }
}
