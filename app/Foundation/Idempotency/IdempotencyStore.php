<?php

namespace App\Foundation\Idempotency;

use App\Foundation\Clock\Clock;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

final readonly class IdempotencyStore
{
    public function __construct(private Clock $clock)
    {
    }

    public function claim(string $scope, string $key, RequestFingerprint $fingerprint): IdempotencyClaim
    {
        $this->assertInsideTransaction();
        $this->assertScope($scope);
        $this->assertKey($key);

        $now = $this->clock->now();
        $inserted = DB::table('idempotency_records')->insertOrIgnore([
            'scope' => $scope,
            'idempotency_key' => $key,
            'fingerprint' => $fingerprint->value,
            'status' => IdempotencyStatus::InProgress->value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $record = DB::table('idempotency_records')
            ->where('scope', $scope)
            ->where('idempotency_key', $key)
            ->lockForUpdate()
            ->first();

        if ($record === null) {
            throw new LogicException('Idempotency record could not be claimed.');
        }

        if (! hash_equals((string) $record->fingerprint, $fingerprint->value)) {
            throw new IdempotencyConflict('Idempotency key was already used with a different fingerprint.');
        }

        return new IdempotencyClaim(
            recordId: (int) $record->id,
            isNew: $inserted === 1,
            status: IdempotencyStatus::from((string) $record->status),
        );
    }

    public function complete(IdempotencyClaim $claim): void
    {
        $this->assertInsideTransaction();

        DB::table('idempotency_records')
            ->where('id', $claim->recordId)
            ->where('status', IdempotencyStatus::InProgress->value)
            ->update([
                'status' => IdempotencyStatus::Completed->value,
                'completed_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
    }

    private function assertInsideTransaction(): void
    {
        if (DB::connection()->transactionLevel() < 1) {
            throw new LogicException('Idempotency operations must run inside the business database transaction.');
        }
    }

    private function assertScope(string $scope): void
    {
        if (preg_match('/^[a-z0-9]+(?:[._:-][a-z0-9]+)*$/D', $scope) !== 1 || strlen($scope) > 100) {
            throw new InvalidArgumentException('Idempotency scope is not canonical.');
        }
    }

    private function assertKey(string $key): void
    {
        if ($key === '' || strlen($key) > 128 || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]*$/D', $key) !== 1) {
            throw new InvalidArgumentException('Idempotency key is invalid.');
        }
    }
}
