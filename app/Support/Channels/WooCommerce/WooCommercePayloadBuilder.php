<?php

namespace App\Support\Channels\WooCommerce;

use App\Models\Period\ChannelProductListing;
use App\Support\Channels\ChannelContentResolver;
use App\Support\Channels\ChannelImageResolver;
use App\Support\Channels\ChannelPriceResolver;
use App\Support\Channels\ChannelStockResolver;
use DomainException;

final class WooCommercePayloadBuilder
{
    public function __construct(
        private readonly ChannelContentResolver $content,
        private readonly ChannelImageResolver $images,
        private readonly ChannelPriceResolver $prices,
        private readonly ChannelStockResolver $stock,
    ) {}

    /** @return array<string,mixed> */
    public function create(ChannelProductListing $listing): array
    {
        $listing->loadMissing('product');
        $content = $this->content->resolve($listing);
        $quantity = $this->integerQuantity($this->stock->quantity($listing));
        $payload = [
            'name' => $content['title'],
            'type' => 'simple',
            'status' => $listing->is_active ? 'publish' : 'draft',
            'catalog_visibility' => 'visible',
            'description' => $content['description'],
            'sku' => $this->sku($listing),
            'regular_price' => bcadd($this->prices->price($listing), '0', 2),
            'manage_stock' => true,
            'stock_quantity' => $quantity,
            'stock_status' => $quantity > 0 ? 'instock' : 'outofstock',
            'backorders' => 'no',
            'tax_status' => 'taxable',
        ];

        $barcode = trim((string) ($listing->product->barcode ?? ''));

        if ($barcode !== '') {
            $payload['global_unique_id'] = $barcode;
        }

        $images = $this->images->urls($listing, 'WooCommerce');

        if ($images !== []) {
            if (collect($images)->contains(
                fn (string $url): bool => ! str_starts_with($url, 'https://'),
            )) {
                throw new DomainException('WooCommerce görselleri public HTTPS URL olmalıdır.');
            }

            $payload['images'] = array_map(
                fn (string $url): array => ['src' => $url],
                array_slice($images, 0, 20),
            );
        }

        return [
            ...$payload,
            ...$this->categoryFields($listing),
            ...$this->attributeFields($listing),
            ...$this->taxFields($listing),
        ];
    }

    /** @return array<string,mixed> */
    public function content(ChannelProductListing $listing): array
    {
        $content = $this->content->resolve($listing);
        $payload = [
            'name' => $content['title'],
            'description' => $content['description'],
            'status' => $listing->is_active ? 'publish' : 'draft',
        ];
        $images = $this->images->urls($listing, 'WooCommerce');

        if ($images !== []) {
            if (collect($images)->contains(
                fn (string $url): bool => ! str_starts_with($url, 'https://'),
            )) {
                throw new DomainException('WooCommerce görselleri public HTTPS URL olmalıdır.');
            }

            $payload['images'] = array_map(
                fn (string $url): array => ['src' => $url],
                array_slice($images, 0, 20),
            );
        }

        return [
            ...$payload,
            ...$this->categoryFields($listing),
            ...$this->attributeFields($listing),
            ...$this->taxFields($listing),
        ];
    }

    /** @return array{manage_stock:bool,stock_quantity:int,stock_status:string,backorders:string} */
    public function stock(string $quantity): array
    {
        $integer = $this->integerQuantity($quantity);

        return [
            'manage_stock' => true,
            'stock_quantity' => $integer,
            'stock_status' => $integer > 0 ? 'instock' : 'outofstock',
            'backorders' => 'no',
        ];
    }

    /** @return array{regular_price:string,sale_price:string} */
    public function price(string $price): array
    {
        if (bccomp($price, '0', 4) < 0) {
            throw new DomainException('WooCommerce fiyat negatif olamaz.');
        }

        return [
            'regular_price' => bcadd($price, '0', 2),
            'sale_price' => '',
        ];
    }

    private function sku(ChannelProductListing $listing): string
    {
        $listing->loadMissing('product');
        $sku = trim((string) ($listing->external_sku ?: $listing->product->code));

        if ($sku === '') {
            throw new DomainException('WooCommerce SKU boş olamaz.');
        }

        return mb_substr($sku, 0, 100);
    }

    /** @return array<string,mixed> */
    private function categoryFields(ChannelProductListing $listing): array
    {
        $meta = $listing->category_metadata ?? [];
        $ids = is_array($meta['category_ids'] ?? null)
            ? $meta['category_ids']
            : (isset($meta['category_id']) ? [$meta['category_id']] : []);
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            fn (int $id): bool => $id > 0,
        )));

        if ($ids === []) {
            return [];
        }

        return [
            'categories' => array_map(
                fn (int $id): array => ['id' => $id],
                $ids,
            ),
        ];
    }

    /** @return array<string,mixed> */
    private function attributeFields(ChannelProductListing $listing): array
    {
        $meta = $listing->category_metadata ?? [];
        $source = $meta['attributes'] ?? null;

        if (! is_array($source)) {
            return [];
        }

        $attributes = [];

        foreach ($source as $attribute) {
            if (! is_array($attribute)) {
                continue;
            }

            $options = is_array($attribute['options'] ?? null)
                ? array_values(array_filter(array_map(
                    fn ($value): string => trim((string) $value),
                    $attribute['options'],
                )))
                : [];

            if ($options === []) {
                continue;
            }

            $row = [
                'options' => $options,
                'visible' => (bool) ($attribute['visible'] ?? true),
                'variation' => false,
            ];

            if ((int) ($attribute['id'] ?? 0) > 0) {
                $row['id'] = (int) $attribute['id'];
            } elseif (trim((string) ($attribute['name'] ?? '')) !== '') {
                $row['name'] = trim((string) $attribute['name']);
            } else {
                continue;
            }

            $attributes[] = $row;
        }

        return $attributes === [] ? [] : ['attributes' => $attributes];
    }

    /** @return array<string,mixed> */
    private function taxFields(ChannelProductListing $listing): array
    {
        $meta = $listing->category_metadata ?? [];
        $taxClass = trim((string) ($meta['tax_class'] ?? ''));

        return $taxClass !== '' ? ['tax_class' => $taxClass] : [];
    }

    private function integerQuantity(string $quantity): int
    {
        if (bccomp($quantity, '0', 3) <= 0) {
            return 0;
        }

        return min(999999999, (int) bcadd($quantity, '0', 0));
    }
}
