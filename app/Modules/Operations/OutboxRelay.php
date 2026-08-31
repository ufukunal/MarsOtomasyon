<?php

namespace App\Modules\Operations;

use App\Foundation\Outbox\OutboxEventCatalog;
use App\Modules\Operations\Jobs\DeliverNotification;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class OutboxRelay
{
    public function relay(int $limit = 50): int
    {
        $limit = max(1, min($limit, 500));
        $relayed = 0;

        while ($relayed < $limit) {
            $claim = $this->claim();
            if ($claim === null) {
                break;
            }

            try {
                DeliverNotification::dispatch($claim['delivery_id']);
                DB::table('outbox_messages')
                    ->where('id', $claim['outbox_id'])
                    ->where('status', 'leased')
                    ->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                        'leased_at' => null,
                        'lease_expires_at' => null,
                        'lease_owner' => null,
                        'last_error_code' => null,
                        'last_error_message' => null,
                        'updated_at' => now(),
                    ]);
                $relayed++;
            } catch (Throwable $exception) {
                $delay = $this->backoffSeconds($claim['attempts']);
                DB::table('outbox_messages')
                    ->where('id', $claim['outbox_id'])
                    ->where('status', 'leased')
                    ->update([
                        'status' => 'failed',
                        'available_at' => now()->addSeconds($delay),
                        'leased_at' => null,
                        'lease_expires_at' => null,
                        'lease_owner' => null,
                        'last_error_code' => 'queue_dispatch_failed',
                        'last_error_message' => mb_substr($exception->getMessage(), 0, 4000),
                        'updated_at' => now(),
                    ]);
            }
        }

        return $relayed;
    }

    /** @return array{outbox_id:int,delivery_id:int,attempts:int}|null */
    private function claim(): ?array
    {
        return DB::transaction(function (): ?array {
            $now = now();
            $row = DB::table('outbox_messages')
                ->where('event_name', OutboxEventCatalog::NOTIFICATION_DELIVERY_REQUESTED_V1)
                ->where('available_at', '<=', $now)
                ->where(function (Builder $query) use ($now): void {
                    $query->whereIn('status', ['pending', 'failed'])
                        ->orWhere(function (Builder $leased) use ($now): void {
                            $leased->where('status', 'leased')->where('lease_expires_at', '<=', $now);
                        });
                })
                ->orderBy('id')
                ->lock('FOR UPDATE SKIP LOCKED')
                ->first();
            if ($row === null) {
                return null;
            }

            $payload = json_decode((string) $row->payload, true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($payload) || ! isset($payload['delivery_id']) || ! is_numeric($payload['delivery_id'])) {
                throw new RuntimeException('Notification outbox payload is invalid.');
            }
            $attempts = (int) $row->attempts + 1;
            DB::table('outbox_messages')->where('id', $row->id)->update([
                'status' => 'leased',
                'attempts' => $attempts,
                'leased_at' => $now,
                'lease_expires_at' => $now->copy()->addMinutes(2),
                'lease_owner' => mb_substr((string) Str::uuid(), 0, 100),
                'updated_at' => $now,
            ]);

            return [
                'outbox_id' => (int) $row->id,
                'delivery_id' => (int) $payload['delivery_id'],
                'attempts' => $attempts,
            ];
        });
    }

    private function backoffSeconds(int $attempt): int
    {
        return match (true) {
            $attempt <= 1 => 15,
            $attempt === 2 => 60,
            $attempt === 3 => 300,
            $attempt === 4 => 900,
            default => 3600,
        };
    }
}
