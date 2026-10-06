<?php

namespace App\Actions\Channels;

use App\Enums\ChannelStockMode;
use App\Enums\LocationKind;
use App\Models\Period\ChannelListingLocation;
use App\Models\Period\ChannelProductListing;
use App\Models\Period\Location;
use App\Models\Period\Product;
use App\Models\SalesChannelAccount;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Period\PeriodContext;
use DomainException;
use Illuminate\Support\Facades\DB;

final class SaveChannelProductListing
{
    /**
     * @param  list<int>  $locationIds
     * @param  array<string, mixed>  $data
     */
    public function handle(
        int $channelAccountId,
        int $productId,
        array $locationIds,
        array $data,
        ?ChannelProductListing $listing = null,
        ?int $expectedVersion = null,
    ): ChannelProductListing {
        MutationAuthorizer::authorize('channel_listings.'.($listing ? 'update' : 'create'));
        PeriodContext::ensureWritable();

        $account = SalesChannelAccount::query()->findOrFail($channelAccountId);

        if ((int) $account->company_id !== (int) PeriodContext::companyId()) {
            throw new DomainException('Kanal hesabı aktif şirkete ait değil.');
        }

        $product = Product::query()->findOrFail($productId);
        $stockMode = isset($data['stock_mode']) && $data['stock_mode'] !== ''
            ? ChannelStockMode::from((string) $data['stock_mode'])
            : null;
        $effectiveMode = $stockMode ?? $product->channel_stock_mode;

        $locationIds = array_values(array_unique(array_map('intval', $locationIds)));
        sort($locationIds, SORT_NUMERIC);

        $locations = $locationIds === []
            ? collect()
            : Location::query()
                ->whereIn('id', $locationIds)
                ->where('is_active', true)
                ->orderBy('id')
                ->get();

        if ($locations->count() !== count($locationIds)
            || $locations->contains(fn (Location $location): bool => $location->kind === LocationKind::Subcontractor)) {
            throw new DomainException('Kanal listing lokasyon kapsamı geçersiz veya fason lokasyon içeriyor.');
        }

        $maxQuantity = $this->nullableDecimal($data['max_channel_quantity'] ?? null, 3);
        $withhold = $this->decimal($data['withhold_quantity'] ?? '0', 3);
        $fixed = $this->nullableDecimal($data['fixed_quantity'] ?? null, 3);
        $manual = $this->nullableDecimal($data['manual_quantity'] ?? null, 3);
        $price = $this->nullableDecimal($data['price_override'] ?? null, 4);
        $leadTime = isset($data['lead_time_days']) && $data['lead_time_days'] !== ''
            ? (int) $data['lead_time_days']
            : null;

        foreach ([$maxQuantity, $withhold, $fixed, $manual, $price] as $value) {
            if ($value !== null && bccomp($value, '0', 4) < 0) {
                throw new DomainException('Kanal listing sayısal alanları negatif olamaz.');
            }
        }

        if ($leadTime !== null && $leadTime < 0) {
            throw new DomainException('Teslim süresi negatif olamaz.');
        }

        if ($effectiveMode === ChannelStockMode::Stock && $locationIds === []) {
            throw new DomainException('Stock modunda en az bir satış lokasyonu seçilmelidir.');
        }

        if ($effectiveMode === ChannelStockMode::Production
            && ($fixed === null || $leadTime === null)) {
            throw new DomainException('Production modunda fixed quantity ve lead time zorunludur.');
        }

        if ($effectiveMode === ChannelStockMode::Manual && $manual === null) {
            throw new DomainException('Manual modunda manual quantity zorunludur.');
        }

        return DB::connection('period')->transaction(function () use (
            $channelAccountId,
            $productId,
            $locationIds,
            $data,
            $stockMode,
            $maxQuantity,
            $withhold,
            $fixed,
            $manual,
            $price,
            $leadTime,
            $listing,
            $expectedVersion,
        ): ChannelProductListing {
            $externalListingId = trim((string) ($data['external_listing_id'] ?? '')) ?: null;
            $this->lockExternalListingIdentity($channelAccountId, $externalListingId);
            $this->assertExternalListingIdentityAvailable(
                $channelAccountId,
                $externalListingId,
                $listing?->id,
            );

            if ($listing) {
                $locked = ChannelProductListing::query()->lockForUpdate()->findOrFail($listing->id);

                if ((int) $locked->channel_account_id !== $channelAccountId
                    || (int) $locked->product_id !== $productId) {
                    throw new DomainException('Listing account/product kimliği sonradan değiştirilemez.');
                }

                $attributes = $this->attributes(
                    $data,
                    $stockMode,
                    $maxQuantity,
                    $withhold,
                    $fixed,
                    $manual,
                    $price,
                    $leadTime,
                );
                $saved = $locked->updateWithVersion(
                    $attributes,
                    $expectedVersion ?? (int) $locked->version,
                );
            } else {
                $saved = ChannelProductListing::query()->create([
                    'channel_account_id' => $channelAccountId,
                    'product_id' => $productId,
                    ...$this->attributes(
                        $data,
                        $stockMode,
                        $maxQuantity,
                        $withhold,
                        $fixed,
                        $manual,
                        $price,
                        $leadTime,
                    ),
                    'version' => 1,
                ]);
            }

            ChannelListingLocation::query()
                ->where('channel_product_listing_id', $saved->id)
                ->delete();

            foreach ($locationIds as $locationId) {
                ChannelListingLocation::query()->create([
                    'channel_product_listing_id' => $saved->id,
                    'location_id' => $locationId,
                ]);
            }

            return $saved->refresh()->load(['product', 'locations.location']);
        }, attempts: 3);
    }

    private function lockExternalListingIdentity(
        int $channelAccountId,
        ?string $externalListingId,
    ): void {
        if ($externalListingId === null) {
            return;
        }

        DB::connection('period')->select(
            'SELECT pg_advisory_xact_lock(hashtextextended(?, 0))',
            [implode('|', ['channel-listing-external-id', $channelAccountId, $externalListingId])],
        );
    }

    private function assertExternalListingIdentityAvailable(
        int $channelAccountId,
        ?string $externalListingId,
        ?int $ignoreListingId,
    ): void {
        if ($externalListingId === null) {
            return;
        }

        $duplicate = ChannelProductListing::query()
            ->where('channel_account_id', $channelAccountId)
            ->where('external_listing_id', $externalListingId)
            ->when(
                $ignoreListingId !== null,
                fn ($query) => $query->where('id', '<>', $ignoreListingId),
            )
            ->exists();

        if ($duplicate) {
            throw new DomainException('External listing ID aynı kanal hesabında yalnız bir kez eşlenebilir.');
        }
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function attributes(
        array $data,
        ?ChannelStockMode $stockMode,
        ?string $maxQuantity,
        string $withhold,
        ?string $fixed,
        ?string $manual,
        ?string $price,
        ?int $leadTime,
    ): array {
        return [
            'external_product_id' => trim((string) ($data['external_product_id'] ?? '')) ?: null,
            'external_listing_id' => trim((string) ($data['external_listing_id'] ?? '')) ?: null,
            'external_sku' => trim((string) ($data['external_sku'] ?? '')) ?: null,
            'stock_mode' => $stockMode?->value,
            'max_channel_quantity' => $maxQuantity,
            'withhold_quantity' => $withhold,
            'fixed_quantity' => $fixed,
            'manual_quantity' => $manual,
            'lead_time_days' => $leadTime,
            'price_override' => $price,
            'title_override' => trim((string) ($data['title_override'] ?? '')) ?: null,
            'description_override' => trim((string) ($data['description_override'] ?? '')) ?: null,
            'image_collection' => trim((string) ($data['image_collection'] ?? '')) ?: null,
            'category_metadata' => is_array($data['category_metadata'] ?? null)
                ? $data['category_metadata']
                : null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    private function decimal(mixed $value, int $scale): string
    {
        return bcadd((string) $value, '0', $scale);
    }

    private function nullableDecimal(mixed $value, int $scale): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->decimal($value, $scale);
    }
}
