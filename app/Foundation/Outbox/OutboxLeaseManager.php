<?php

namespace App\Foundation\Outbox;

use App\Foundation\Clock\Clock;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class OutboxLeaseManager
{
    public function __construct(private Clock $clock) {}

    /** @return list<int> */
    public function claim(string $owner, int $limit = 100, int $leaseSeconds = 90): array
    {
        $owner = trim($owner);
        if ($owner === '') {
            throw new LogicException('Outbox lease owner must not be blank.');
        }

        $limit = max(1, min(500, $limit));
        $leaseSeconds = max(30, $leaseSeconds);
        $now = $this->clock->now();
        $expiresAt = $this->plusSeconds($now, $leaseSeconds);

        return DB::transaction(function () use ($owner, $limit, $now, $expiresAt): array {
            $rows = DB::table('outbox_messages')
                ->where('available_at', '<=', $now)
                ->where(function ($query) use ($now): void {
                    $query->where('status', OutboxStatus::Pending->value)
                        ->orWhere(function ($expired) use ($now): void {
                            $expired->where('status', OutboxStatus::Leased->value)
                                ->where('lease_expires_at', '<=', $now)
                                ->whereIn('retry_capability', [
                                    OutboxRetryCapability::SafeRetry->value,
                                    OutboxRetryCapability::IdempotentWithKey->value,
                                ]);
                        });
                })
                ->orderBy('available_at')
                ->orderBy('id')
                ->limit($limit)
                ->lock('FOR UPDATE SKIP LOCKED')
                ->get(['id']);

            $claimed = [];
            foreach ($rows as $row) {
                $id = (int) $row->id;
                DB::table('outbox_messages')->where('id', $id)->update([
                    'status' => OutboxStatus::Leased->value,
                    'attempts' => DB::raw('attempts + 1'),
                    'leased_at' => $now,
                    'lease_expires_at' => $expiresAt,
                    'lease_owner' => $owner,
                    'last_error_code' => null,
                    'last_error_message' => null,
                    'updated_at' => $now,
                ]);
                $claimed[] = $id;
            }

            return $claimed;
        }, 3);
    }

    public function complete(int $id, string $owner): void
    {
        $now = $this->clock->now();
        $updated = DB::table('outbox_messages')
            ->where('id', $id)
            ->where('status', OutboxStatus::Leased->value)
            ->where('lease_owner', $owner)
            ->update([
                'status' => OutboxStatus::Completed->value,
                'completed_at' => $now,
                'leased_at' => null,
                'lease_expires_at' => null,
                'lease_owner' => null,
                'last_error_code' => null,
                'last_error_message' => null,
                'updated_at' => $now,
            ]);

        if ($updated !== 1) {
            throw new LogicException('Outbox completion requires the active lease owner.');
        }
    }

    public function fail(int $id, string $owner, string $errorCode, string $errorMessage, int $retryDelaySeconds = 60): void
    {
        DB::transaction(function () use ($id, $owner, $errorCode, $errorMessage, $retryDelaySeconds): void {
            $row = DB::table('outbox_messages')
                ->where('id', $id)
                ->lockForUpdate()
                ->first(['status', 'lease_owner', 'retry_capability']);

            if ($row === null
                || (string) $row->status !== OutboxStatus::Leased->value
                || (string) $row->lease_owner !== $owner) {
                throw new LogicException('Outbox failure handling requires the active lease owner.');
            }

            $capability = OutboxRetryCapability::from((string) $row->retry_capability);
            $automaticRetry = in_array($capability, [
                OutboxRetryCapability::SafeRetry,
                OutboxRetryCapability::IdempotentWithKey,
            ], true);
            $now = $this->clock->now();

            DB::table('outbox_messages')->where('id', $id)->update([
                'status' => $automaticRetry ? OutboxStatus::Pending->value : OutboxStatus::Failed->value,
                'available_at' => $automaticRetry ? $this->plusSeconds($now, max(1, $retryDelaySeconds)) : $now,
                'leased_at' => null,
                'lease_expires_at' => null,
                'lease_owner' => null,
                'last_error_code' => mb_substr(trim($errorCode), 0, 100),
                'last_error_message' => mb_substr(trim($errorMessage), 0, 4000),
                'updated_at' => $now,
            ]);
        }, 3);
    }

    public function quarantineExpiredAmbiguous(): int
    {
        $now = $this->clock->now();

        return DB::table('outbox_messages')
            ->where('status', OutboxStatus::Leased->value)
            ->where('lease_expires_at', '<=', $now)
            ->whereIn('retry_capability', [
                OutboxRetryCapability::QueryBeforeRetry->value,
                OutboxRetryCapability::NeverAutoRetry->value,
            ])
            ->update([
                'status' => OutboxStatus::Failed->value,
                'available_at' => $now,
                'leased_at' => null,
                'lease_expires_at' => null,
                'lease_owner' => null,
                'last_error_code' => 'AMBIGUOUS_OUTCOME_REVIEW_REQUIRED',
                'last_error_message' => 'Lease expired after an attempt whose retry contract requires reconciliation or forbids automatic retry.',
                'updated_at' => $now,
            ]);
    }

    private function plusSeconds(DateTimeImmutable $time, int $seconds): DateTimeImmutable
    {
        return $time->add(new DateInterval('PT'.$seconds.'S'));
    }
}
