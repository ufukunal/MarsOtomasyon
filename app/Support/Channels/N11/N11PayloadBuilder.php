<?php

namespace App\Support\Channels\N11;

use App\Models\Period\ChannelProductListing;
use App\Support\Channels\ChannelContentResolver;
use App\Support\Channels\ChannelImageResolver;
use App\Support\Channels\ChannelPriceResolver;
use App\Support\Channels\ChannelStockResolver;
use DomainException;

final class N11PayloadBuilder
{
    public function __construct(
        private readonly ChannelContentResolver $content,
        private readonly ChannelImageResolver $images,
        private readonly ChannelPriceResolver $prices,
        private readonly ChannelStockResolver $stock,
    ) {}

    /** @return array<string,mixed> */
    public function createSku(ChannelProductListing $listing): array
    {
        $listing->loadMissing('product');
        $meta = $listing->category_metadata ?? [];
        $categoryId = (int) ($meta['category_id'] ?? 0);
        $shipmentTemplate = trim((string) ($meta['shipment_template'] ?? ''));
        $barcode = trim((string) ($listing->product->barcode ?? ''));

        if ($categoryId <= 0 || $shipmentTemplate === '') {
            throw new DomainException('N11 publish için category_id ve shipment_template zorunludur.');
        }

        $content = $this->content->resolve($listing);
        $images = $this->images->urls($listing, 'N11');
        $attributes = $this->attributes($meta['attributes'] ?? []);

        if ($attributes === []) {
            throw new DomainException('N11 publish için kategori attributes mapping zorunludur.');
        }

        if ($images === [] || collect($images)->contains(
            fn (string $url): bool => ! str_starts_with($url, 'https://'),
        )) {
            throw new DomainException('N11 publish için en az bir public HTTPS ürün görseli zorunludur.');
        }

        $salePrice = $this->prices->price($listing);
        $listPrice = isset($meta['list_price'])
            ? bcadd((string) $meta['list_price'], '0', 2)
            : $salePrice;

        if (bccomp($listPrice, $salePrice, 2) < 0) {
            throw new DomainException('N11 listPrice salePrice değerinden düşük olamaz.');
        }

        return [
            'title' => $content['title'],
            'description' => $content['description'],
            'categoryId' => $categoryId,
            'currencyType' => 'TL',
            'productMainId' => (string) ($meta['product_main_id'] ?? $listing->product->code),
            'preparingDay' => max(1, (int) ($listing->lead_time_days ?? $meta['preparing_day'] ?? 1)),
            'shipmentTemplate' => $shipmentTemplate,
            'maxPurchaseQuantity' => $listing->max_channel_quantity !== null
                ? max(1, (int) $listing->max_channel_quantity)
                : null,
            'stockCode' => $this->stockCode($listing),
            'catalogId' => isset($meta['catalog_id']) ? (int) $meta['catalog_id'] : null,
            'barcode' => $barcode !== '' ? $barcode : null,
            'quantity' => min(999999, $this->integerQuantity($this->stock->quantity($listing))),
            'images' => array_values(array_map(
                fn (string $url, int $index): array => ['url' => $url, 'order' => $index],
                array_slice($images, 0, 8),
                array_keys(array_slice($images, 0, 8)),
            )),
            'attributes' => $attributes,
            'salePrice' => (float) bcadd($salePrice, '0', 2),
            'listPrice' => (float) $listPrice,
            'vatRate' => $this->vatRate($listing),
        ];
    }

    /** @return array<string,mixed> */
    public function contentUpdateSku(ChannelProductListing $listing): array
    {
        $listing->loadMissing('product');
        $meta = $listing->category_metadata ?? [];
        $content = $this->content->resolve($listing);

        $row = [
            'stockCode' => $this->stockCode($listing),
            'status' => $listing->is_active ? 'Active' : 'Suspended',
            'description' => $content['description'],
            'vatRate' => $this->vatRate($listing),
        ];

        if ($listing->lead_time_days !== null) {
            $row['preparingDay'] = max(1, (int) $listing->lead_time_days);
        }

        if (trim((string) ($meta['shipment_template'] ?? '')) !== '') {
            $row['shipmentTemplate'] = trim((string) $meta['shipment_template']);
        }

        if ($listing->max_channel_quantity !== null) {
            $row['maxPurchaseQuantity'] = max(1, (int) $listing->max_channel_quantity);
        }

        if (isset($meta['product_main_id'])) {
            $row['productMainId'] = (string) $meta['product_main_id'];
        }

        $attributes = $this->attributes($meta['attributes'] ?? []);

        if ($attributes !== []) {
            $row['attributes'] = $attributes;
        }

        return $row;
    }

    /** @return array<string,mixed> */
    public function stockPriceSku(
        ChannelProductListing $listing,
        ?string $quantity,
        ?string $price,
    ): array {
        $row = ['stockCode' => $this->stockCode($listing)];

        if ($quantity !== null) {
            $row['quantity'] = min(999999, $this->integerQuantity($quantity));
        }

        if ($price !== null) {
            $sale = bcadd($price, '0', 2);
            $meta = $listing->category_metadata ?? [];
            $list = isset($meta['list_price'])
                ? bcadd((string) $meta['list_price'], '0', 2)
                : $sale;

            if (bccomp($list, $sale, 2) < 0) {
                $list = $sale;
            }

            $row['listPrice'] = (float) $list;
            $row['salePrice'] = (float) $sale;
            $row['currencyType'] = 'TL';
        }

        return $row;
    }

    private function stockCode(ChannelProductListing $listing): string
    {
        $listing->loadMissing('product');
        $code = trim((string) ($listing->external_sku ?: $listing->product->code));

        if ($code === '') {
            throw new DomainException('N11 stockCode boş olamaz.');
        }

        return mb_substr($code, 0, 255);
    }

    /** @return list<array<string,mixed>> */
    private function attributes(mixed $attributes): array
    {
        if (! is_array($attributes)) {
            return [];
        }

        $result = [];

        foreach ($attributes as $attribute) {
            if (! is_array($attribute) || (int) ($attribute['id'] ?? $attribute['attribute_id'] ?? 0) <= 0) {
                continue;
            }

            $row = ['id' => (int) ($attribute['id'] ?? $attribute['attribute_id'])];

            if (isset($attribute['valueId']) || isset($attribute['value_id'])) {
                $row['valueId'] = (int) ($attribute['valueId'] ?? $attribute['value_id']);
                $row['customValue'] = null;
            } elseif (array_key_exists('customValue', $attribute) || array_key_exists('custom_value', $attribute)) {
                $row['valueId'] = null;
                $row['customValue'] = (string) ($attribute['customValue'] ?? $attribute['custom_value']);
            } else {
                continue;
            }

            $result[] = $row;
        }

        return $result;
    }

    private function vatRate(ChannelProductListing $listing): int
    {
        $rate = (int) bcadd((string) $listing->product->vat_rate, '0', 0);

        if (! in_array($rate, [0, 1, 10, 20], true)) {
            throw new DomainException('N11 KDV oranı yalnız 0, 1, 10 veya 20 olabilir.');
        }

        return $rate;
    }

    private function integerQuantity(string $quantity): int
    {
        if (bccomp($quantity, '0', 3) <= 0) {
            return 0;
        }

        return (int) bcadd($quantity, '0', 0);
    }
}
