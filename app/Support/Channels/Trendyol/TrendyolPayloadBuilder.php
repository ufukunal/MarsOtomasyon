<?php

namespace App\Support\Channels\Trendyol;

use App\Models\Period\ChannelProductListing;
use App\Support\Channels\ChannelContentResolver;
use App\Support\Channels\ChannelImageResolver;
use App\Support\Channels\ChannelPriceResolver;
use App\Support\Channels\ChannelStockResolver;
use DomainException;

final class TrendyolPayloadBuilder
{
    public function __construct(
        private readonly ChannelContentResolver $content,
        private readonly ChannelImageResolver $images,
        private readonly ChannelPriceResolver $prices,
        private readonly ChannelStockResolver $stock,
    ) {}

    /** @return array<string,mixed> */
    public function createItem(ChannelProductListing $listing): array
    {
        $listing->loadMissing(['product', 'product.brand']);
        $meta = $listing->category_metadata ?? [];
        $barcode = trim((string) ($listing->product->barcode ?? ''));

        if ($barcode === '') {
            throw new DomainException('Trendyol yayınlama için ürün barcode zorunludur.');
        }

        $brandId = (int) ($meta['brand_id'] ?? 0);
        $categoryId = (int) ($meta['category_id'] ?? 0);

        if ($brandId <= 0 || $categoryId <= 0) {
            throw new DomainException('Trendyol brand_id/category_id metadata zorunludur.');
        }

        $images = $this->images->urls($listing, 'Trendyol');

        if ($images === []) {
            throw new DomainException('Trendyol yayınlama için en az bir ürün görseli zorunludur.');
        }

        if (collect($images)->contains(fn (string $url): bool => ! str_starts_with($url, 'https://'))) {
            throw new DomainException('Trendyol ürün görselleri public HTTPS URL üzerinden erişilebilir olmalıdır.');
        }

        $content = $this->content->resolve($listing);
        $salePrice = $this->prices->price($listing);
        $listPrice = isset($meta['list_price'])
            ? bcadd((string) $meta['list_price'], '0', 4)
            : $salePrice;

        if (bccomp($listPrice, $salePrice, 4) < 0) {
            throw new DomainException('Trendyol listPrice salePrice değerinden düşük olamaz.');
        }

        $quantity = $this->integerQuantity($this->stock->quantity($listing));
        $item = [
            'barcode' => $barcode,
            'title' => mb_substr($content['title'], 0, 100),
            'description' => $content['description'],
            'productMainId' => mb_substr((string) ($meta['product_main_id'] ?? $listing->product->code), 0, 40),
            'brandId' => $brandId,
            'categoryId' => $categoryId,
            'quantity' => $quantity,
            'stockCode' => mb_substr((string) $listing->product->code, 0, 100),
            'listPrice' => (float) $listPrice,
            'salePrice' => (float) $salePrice,
            'vatRate' => (int) bcadd((string) $listing->product->vat_rate, '0', 0),
            'images' => array_map(fn (string $url): array => ['url' => $url], array_slice($images, 0, 8)),
            'attributes' => $this->attributes($meta['attributes'] ?? []),
        ];

        if ($listing->lead_time_days !== null) {
            $item['deliveryOptions'] = ['deliveryDuration' => (int) $listing->lead_time_days];
        }

        foreach (['origin', 'shipment_address_id', 'returning_address_id', 'lot_number'] as $key) {
            if (! array_key_exists($key, $meta) || $meta[$key] === null || $meta[$key] === '') {
                continue;
            }

            $target = match ($key) {
                'shipment_address_id' => 'shipmentAddressId',
                'returning_address_id' => 'returningAddressId',
                'lot_number' => 'lotNumber',
                default => $key,
            };
            $item[$target] = $meta[$key];
        }

        if (is_array($meta['cargo_providers'] ?? null) && $meta['cargo_providers'] !== []) {
            $item['cargoProviders'] = array_values($meta['cargo_providers']);
        }

        return $item;
    }

    /** @return array<string,mixed> */
    public function contentItem(ChannelProductListing $listing): array
    {
        if (! $listing->external_product_id) {
            throw new DomainException('Trendyol content update için external_product_id/contentId zorunludur.');
        }

        $content = $this->content->resolve($listing);
        $images = $this->images->urls($listing, 'Trendyol');
        $item = [
            'contentId' => (int) $listing->external_product_id,
            'title' => mb_substr($content['title'], 0, 100),
            'description' => $content['description'],
        ];

        if ($images !== []) {
            if (collect($images)->contains(fn (string $url): bool => ! str_starts_with($url, 'https://'))) {
                throw new DomainException('Trendyol ürün görselleri public HTTPS URL üzerinden erişilebilir olmalıdır.');
            }

            $item['images'] = array_map(fn (string $url): array => ['url' => $url], array_slice($images, 0, 8));
        }

        $attributes = $this->attributes(($listing->category_metadata ?? [])['attributes'] ?? []);

        if ($attributes !== []) {
            $item['attributes'] = $attributes;
        }

        return $item;
    }

    /** @return array{barcode:string,quantity:int,salePrice:float,listPrice:float} */
    public function inventoryItem(ChannelProductListing $listing, ?string $quantity = null, ?string $price = null): array
    {
        $listing->loadMissing('product');
        $barcode = trim((string) ($listing->product->barcode ?? ''));

        if ($barcode === '') {
            throw new DomainException('Trendyol stok/fiyat sync için barcode zorunludur.');
        }

        $quantity ??= $this->stock->quantity($listing);
        $price ??= $this->prices->price($listing);
        $listPrice = isset(($listing->category_metadata ?? [])['list_price'])
            ? bcadd((string) $listing->category_metadata['list_price'], '0', 4)
            : $price;

        if (bccomp($listPrice, $price, 4) < 0) {
            $listPrice = $price;
        }

        return [
            'barcode' => $barcode,
            'quantity' => min(20000, $this->integerQuantity($quantity)),
            'salePrice' => (float) $price,
            'listPrice' => (float) $listPrice,
        ];
    }

    /** @return array{barcode:string,deliveryOptions:array{deliveryDuration:int}} */
    public function deliveryItem(ChannelProductListing $listing, int $leadTimeDays): array
    {
        $listing->loadMissing('product');
        $barcode = trim((string) ($listing->product->barcode ?? ''));

        if ($barcode === '' || $leadTimeDays < 0) {
            throw new DomainException('Trendyol delivery sync barcode/lead time geçersiz.');
        }

        return [
            'barcode' => $barcode,
            'deliveryOptions' => ['deliveryDuration' => $leadTimeDays],
        ];
    }

    /** @return list<array<string,mixed>> */
    private function attributes(mixed $attributes): array
    {
        if (! is_array($attributes)) {
            return [];
        }

        $result = [];

        foreach ($attributes as $attribute) {
            if (! is_array($attribute) || (int) ($attribute['attribute_id'] ?? 0) <= 0) {
                continue;
            }

            $row = ['attributeId' => (int) $attribute['attribute_id']];

            if (isset($attribute['attribute_value_ids']) && is_array($attribute['attribute_value_ids'])) {
                $row['attributeValueIds'] = array_values(array_map('intval', $attribute['attribute_value_ids']));
            } elseif (isset($attribute['attribute_value_id'])) {
                $row['attributeValueId'] = (int) $attribute['attribute_value_id'];
            } elseif (isset($attribute['custom_value'])) {
                $row['customAttributeValue'] = (string) $attribute['custom_value'];
            } else {
                continue;
            }

            $result[] = $row;
        }

        return $result;
    }

    private function integerQuantity(string $quantity): int
    {
        if (bccomp($quantity, '0', 3) <= 0) {
            return 0;
        }

        return (int) bcadd($quantity, '0', 0);
    }
}
