<?php

namespace App\Support\Channels;

use App\Enums\ChannelStockMode;
use App\Enums\ProductKind;
use App\Models\Period\ChannelProductListing;
use App\Models\Period\StockBalance;
use DomainException;

final class ChannelStockResolver
{
    public function quantity(ChannelProductListing $listing): string
    {
        $listing->loadMissing(['product.setComponents', 'locations']);
        $mode = $listing->stock_mode
            ? ChannelStockMode::from((string) $listing->stock_mode)
            : $listing->product->channel_stock_mode;

        return match ($mode) {
            ChannelStockMode::Production => $this->production($listing),
            ChannelStockMode::Manual => $this->manual($listing),
            ChannelStockMode::Stock => $this->stock($listing),
        };
    }

    private function production(ChannelProductListing $listing): string
    {
        if ($listing->fixed_quantity === null || $listing->lead_time_days === null) {
            throw new DomainException('Production listing fixed quantity/lead time eksik.');
        }

        return bcadd((string) $listing->fixed_quantity, '0', 3);
    }

    private function manual(ChannelProductListing $listing): string
    {
        if ($listing->manual_quantity === null) {
            throw new DomainException('Manual listing quantity eksik.');
        }

        return bcadd((string) $listing->manual_quantity, '0', 3);
    }

    private function stock(ChannelProductListing $listing): string
    {
        $locationIds = $listing->locations
            ->pluck('location_id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        if ($locationIds === []) {
            throw new DomainException('Stock listing için location kapsamı boş.');
        }

        $available = $listing->product->kind === ProductKind::Set
            ? $this->setAvailable($listing, $locationIds)
            : $this->productAvailable((int) $listing->product_id, $locationIds);

        $afterWithhold = bcsub($available, (string) $listing->withhold_quantity, 3);

        if (bccomp($afterWithhold, '0', 3) < 0) {
            $afterWithhold = '0.000';
        }

        if ($listing->max_channel_quantity !== null
            && bccomp($afterWithhold, (string) $listing->max_channel_quantity, 3) > 0) {
            $afterWithhold = bcadd((string) $listing->max_channel_quantity, '0', 3);
        }

        return $afterWithhold;
    }

    /** @param list<int> $locationIds */
    private function productAvailable(int $productId, array $locationIds): string
    {
        return StockBalance::query()
            ->where('product_id', $productId)
            ->whereIn('location_id', $locationIds)
            ->orderBy('location_id')
            ->get()
            ->reduce(
                fn (string $sum, StockBalance $balance): string => bcadd($sum, $balance->available(), 3),
                '0.000',
            );
    }

    /** @param list<int> $locationIds */
    private function setAvailable(ChannelProductListing $listing, array $locationIds): string
    {
        if ($listing->product->setComponents->isEmpty()) {
            return '0.000';
        }

        $minimum = null;

        foreach ($listing->product->setComponents as $component) {
            $required = bcadd((string) $component->quantity, '0', 3);

            if (bccomp($required, '0', 3) <= 0) {
                return '0.000';
            }

            $available = $this->productAvailable((int) $component->component_product_id, $locationIds);

            if (bccomp($available, '0', 3) < 0) {
                $available = '0.000';
            }

            $possible = bcdiv($available, $required, 0);

            $minimum = $minimum === null || bccomp($possible, $minimum, 0) < 0
                ? $possible
                : $minimum;
        }

        return bcadd((string) ($minimum ?? '0'), '0', 3);
    }
}
