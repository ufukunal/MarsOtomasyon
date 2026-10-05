<?php

namespace App\Support\Integrity\Checks;

use App\Enums\ChannelStockMode;
use App\Enums\LocationKind;
use App\Models\Period\ChannelAccountPeriodSetting;
use App\Models\Period\ChannelProductListing;
use App\Models\Period\ChannelSyncError;
use App\Models\Period\ChannelSyncEvent;
use App\Models\SalesChannelAccount;
use App\Support\Integrity\IntegrityCheck;
use App\Support\Integrity\IntegrityResult;
use App\Support\Period\PeriodContext;
use Illuminate\Support\Facades\Schema;

final class ChannelIntegrityCheck implements IntegrityCheck
{
    public function name(): string
    {
        return 'channels_phase9';
    }

    public function run(): IntegrityResult
    {
        $started = hrtime(true);

        if (! Schema::connection('period')->hasTable('channel_product_listings')
            || ! Schema::connection('master')->hasTable('sales_channel_accounts')) {
            return new IntegrityResult(
                checked: 0,
                mismatches: [],
                durationMs: $this->elapsed($started),
                meta: ['status' => 'not_applicable'],
            );
        }

        $companyId = PeriodContext::companyId();
        $accounts = SalesChannelAccount::query()
            ->where('company_id', $companyId)
            ->get()
            ->keyBy('id');
        $mismatches = [];

        foreach ($accounts as $account) {
            if ($account->is_active
                && $account->platform->value === 'hepsiburada'
                && trim((string) ($account->external_store_id ?: ($account->credentials()['merchant_id'] ?? ''))) === '') {
                $mismatches[] = [
                    'channel_account_id' => $account->id,
                    'reason' => 'hepsiburada_merchant_id_missing',
                ];
            }
        }

        $settings = ChannelAccountPeriodSetting::query()
            ->with('marketplaceCustomerContact')
            ->orderBy('id')
            ->get();

        foreach ($settings as $setting) {
            if (! $accounts->has($setting->channel_account_id)
                || $setting->marketplaceCustomerContact === null
                || ! $setting->marketplaceCustomerContact->is_active) {
                $mismatches[] = [
                    'channel_account_period_setting_id' => $setting->id,
                    'reason' => 'period_account_or_marketplace_contact_invalid',
                ];
            }
        }

        $listings = ChannelProductListing::query()
            ->with(['product', 'locations.location'])
            ->orderBy('id')
            ->get();

        $externalListingGroups = $listings
            ->filter(fn ($listing): bool => trim((string) $listing->external_listing_id) !== '')
            ->groupBy(fn ($listing): string => $listing->channel_account_id.':'.$listing->external_listing_id);

        foreach ($externalListingGroups as $key => $group) {
            if ($group->count() > 1) {
                $mismatches[] = [
                    'mapping_key' => $key,
                    'listing_ids' => $group->pluck('id')->values()->all(),
                    'reason' => 'duplicate_external_listing_mapping',
                ];
            }
        }

        foreach ($listings as $listing) {
            if (! $accounts->has($listing->channel_account_id) || $listing->product === null) {
                $mismatches[] = [
                    'channel_product_listing_id' => $listing->id,
                    'reason' => 'listing_account_or_product_invalid',
                ];
                continue;
            }

            $effectiveMode = $listing->stock_mode
                ? ChannelStockMode::from((string) $listing->stock_mode)
                : $listing->product->channel_stock_mode;

            if ($effectiveMode === ChannelStockMode::Stock && $listing->locations->isEmpty()) {
                $mismatches[] = [
                    'channel_product_listing_id' => $listing->id,
                    'reason' => 'stock_mode_location_scope_missing',
                ];
            }

            if ($effectiveMode === ChannelStockMode::Production
                && ($listing->fixed_quantity === null || $listing->lead_time_days === null)) {
                $mismatches[] = [
                    'channel_product_listing_id' => $listing->id,
                    'reason' => 'production_mode_fields_missing',
                ];
            }

            if ($effectiveMode === ChannelStockMode::Manual && $listing->manual_quantity === null) {
                $mismatches[] = [
                    'channel_product_listing_id' => $listing->id,
                    'reason' => 'manual_mode_quantity_missing',
                ];
            }

            foreach ($listing->locations as $mapping) {
                if ($mapping->location === null
                    || ! $mapping->location->is_active
                    || $mapping->location->kind === LocationKind::Subcontractor) {
                    $mismatches[] = [
                        'channel_listing_location_id' => $mapping->id,
                        'reason' => 'listing_location_invalid',
                    ];
                }
            }
        }

        $syncEvents = ChannelSyncEvent::query()->with('errors')->orderBy('id')->get();

        foreach ($syncEvents as $event) {
            if (! $accounts->has($event->channel_account_id)
                || ($event->payload_hash !== null && ! preg_match('/^[a-f0-9]{64}$/D', (string) $event->payload_hash))
                || ($event->safe_metadata !== null && ! is_array($event->safe_metadata))) {
                $mismatches[] = [
                    'channel_sync_event_id' => $event->id,
                    'reason' => 'sync_event_account_or_hash_invalid',
                ];
            }

            if ($event->errors->count() > 1
                || ($event->errors->isNotEmpty() && $event->status !== 'failed')) {
                $mismatches[] = [
                    'channel_sync_event_id' => $event->id,
                    'reason' => 'sync_error_state_invalid',
                ];
            }
        }

        $orphanErrors = ChannelSyncError::query()
            ->whereDoesntHave('event')
            ->count();

        if ($orphanErrors > 0) {
            $mismatches[] = [
                'count' => $orphanErrors,
                'reason' => 'orphan_sync_errors',
            ];
        }

        return new IntegrityResult(
            checked: $settings->count()
                + $listings->count()
                + $listings->sum(fn ($listing): int => $listing->locations->count())
                + $syncEvents->count(),
            mismatches: $mismatches,
            durationMs: $this->elapsed($started),
        );
    }

    private function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
