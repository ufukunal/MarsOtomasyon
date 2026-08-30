<?php

namespace App\Modules\Commerce;

use Illuminate\Support\Facades\DB;

final class CommerceSyncGuard
{
    public function shouldSend(int $effectId): bool
    {
        return DB::transaction(function () use ($effectId): bool {
            $effect = DB::table('integration_sync_effects')->where('id', $effectId)->lockForUpdate()->first();
            if ($effect === null || (string) ($effect->guard_type ?? '') !== 'listing_state') {
                return true;
            }

            $state = DB::table('channel_listing_states')
                ->where('id', (int) $effect->guard_id)
                ->where('company_id', (int) $effect->company_id)
                ->where('connection_id', (int) $effect->connection_id)
                ->first();
            if ($state === null || (int) ($effect->guard_version ?? 0) < (int) $state->desired_version) {
                DB::table('integration_sync_effects')->where('id', $effectId)->update([
                    'status' => 'ignored',
                    'ignored_reason' => $state === null ? 'listing_state_missing' : 'stale_desired_version',
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);

                return false;
            }

            return true;
        });
    }

    public function reconcile(int $effectId): void
    {
        DB::transaction(function () use ($effectId): void {
            $effect = DB::table('integration_sync_effects')->where('id', $effectId)->lockForUpdate()->first();
            if ($effect === null || (string) ($effect->guard_type ?? '') !== 'listing_state' || $effect->guard_id === null) {
                return;
            }

            $state = DB::table('channel_listing_states')->where('id', (int) $effect->guard_id)->lockForUpdate()->first();
            if ($state === null || (int) $effect->guard_version !== (int) $state->desired_version) {
                return;
            }

            if ((string) $effect->status === 'succeeded') {
                DB::table('channel_listing_states')->where('id', $state->id)->update([
                    'published_version' => (int) $state->desired_version,
                    'published_stock' => $state->desired_stock,
                    'published_price' => $state->desired_price,
                    'published_currency_code' => $state->desired_currency_code,
                    'published_media' => $state->desired_media,
                    'status' => 'synced',
                    'last_error' => null,
                    'updated_at' => now(),
                ]);
            } elseif ((string) $effect->status === 'failed') {
                DB::table('channel_listing_states')->where('id', $state->id)->update([
                    'status' => 'failed',
                    'last_error' => $effect->last_error,
                    'updated_at' => now(),
                ]);
            }
        });
    }
}
