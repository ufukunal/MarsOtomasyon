<?php

namespace App\Support\Channels\Hepsiburada;

use App\Models\Period\ChannelProductListing;
use App\Support\Channels\ChannelContentResolver;
use App\Support\Channels\ChannelImageResolver;
use App\Support\Channels\ChannelPriceResolver;
use App\Support\Channels\ChannelStockResolver;
use DomainException;

final class HepsiburadaPayloadBuilder
{
    public function __construct(
        private readonly ChannelContentResolver $content,
        private readonly ChannelImageResolver $images,
        private readonly ChannelPriceResolver $prices,
        private readonly ChannelStockResolver $stock,
    ) {}

    /** @return array<string,mixed> */
    public function catalogItem(
        ChannelProductListing $listing,
        string $merchantId,
    ): array {
        $listing->loadMissing(['product', 'product.brand']);
        $meta = $listing->category_metadata ?? [];
        $categoryId = (int) ($meta['category_id'] ?? 0);
        $barcode = trim((string) ($listing->product->barcode ?? ''));
        $brand = trim((string) (
            $meta['brand']
            ?? $listing->product->brand?->name
            ?? ''
        ));

        if ($categoryId <= 0 || $barcode === '' || $brand === '') {
            throw new DomainException(
                'Hepsiburada publish için category_id, barcode ve marka zorunludur.',
            );
        }

        $images = $this->publicImages($listing);
        $content = $this->content->resolve($listing);
        $attributes = is_array($meta['attributes'] ?? null)
            ? $meta['attributes']
            : [];

        $attributes = [
            ...$attributes,
            'merchantSku' => $this->merchantSku($listing),
            'VaryantGroupID' => (string) (
                $meta['variant_group_id']
                ?? 'MARS-'.$listing->product_id
            ),
            'Barcode' => $barcode,
            'UrunAdi' => $content['title'],
            'UrunAciklamasi' => $content['description'],
            'Marka' => $brand,
            'GarantiSuresi' => (int) ($meta['warranty_period'] ?? 0),
            'kg' => (string) ($meta['desi'] ?? '1'),
            'price' => $this->turkishPrice($this->prices->price($listing)),
            'stock' => (string) $this->integerQuantity($this->stock->quantity($listing)),
            'tax_vat_rate' => (string) (int) $listing->product->vat_rate,
        ];

        foreach (array_slice($images, 0, 5) as $index => $url) {
            $attributes['Image'.($index + 1)] = $url;
        }

        return [
            'categoryId' => $categoryId,
            'merchant' => $merchantId,
            'attributes' => $attributes,
        ];
    }

    /**
     * Current product-update API expects rows shaped as
     * { hbSku, fields: { ... } }.
     *
     * @return array{hbSku:string,fields:array<string,mixed>}
     */
    public function contentUpdateItem(ChannelProductListing $listing): array
    {
        $listing->loadMissing('product');

        if (! $listing->external_product_id) {
            throw new DomainException('Hepsiburada content update için HB SKU zorunludur.');
        }

        $content = $this->content->resolve($listing);
        $meta = $listing->category_metadata ?? [];
        $fields = is_array($meta['attributes'] ?? null)
            ? $meta['attributes']
            : [];

        $fields = [
            ...$fields,
            'productName' => $content['title'],
            'productDescription' => $content['description'],
            'kdv' => (string) (int) $listing->product->vat_rate,
            'warrantyPeriod' => (string) (int) ($meta['warranty_period'] ?? 0),
            'desi' => (string) ($meta['desi'] ?? '1'),
            'barcode' => (string) ($listing->product->barcode ?? ''),
        ];

        foreach (array_slice($this->publicImages($listing), 0, 10) as $index => $url) {
            $fields['image'.($index + 1)] = $url;
        }

        return [
            'hbSku' => (string) $listing->external_product_id,
            'fields' => $fields,
        ];
    }

    /** @return array{hepsiburadaSku:string,merchantSku:string,availableStock:int,maximumPurchasableQuantity:int} */
    public function stockItem(
        ChannelProductListing $listing,
        string $quantity,
    ): array {
        return [
            'hepsiburadaSku' => $this->hbSku($listing, 'stok'),
            'merchantSku' => $this->merchantSku($listing),
            'availableStock' => $this->integerQuantity($quantity),
            'maximumPurchasableQuantity' => max(
                1,
                min(
                    999999,
                    (int) ($listing->max_channel_quantity ?? 1000),
                ),
            ),
        ];
    }

    /** @return array{hepsiburadaSku:string,merchantSku:string,price:float} */
    public function priceItem(
        ChannelProductListing $listing,
        string $price,
    ): array {
        return [
            'hepsiburadaSku' => $this->hbSku($listing, 'fiyat'),
            'merchantSku' => $this->merchantSku($listing),
            'price' => (float) bcadd($price, '0', 2),
        ];
    }

    /** @return array{hepsiburadaSku:string,merchantSku:string,dispatchTime:int} */
    public function shippingItem(
        ChannelProductListing $listing,
        int $leadTimeDays,
    ): array {
        return [
            'hepsiburadaSku' => $this->hbSku($listing, 'teslimat'),
            'merchantSku' => $this->merchantSku($listing),
            'dispatchTime' => max(0, $leadTimeDays),
        ];
    }

    private function hbSku(ChannelProductListing $listing, string $operation): string
    {
        $hbSku = trim((string) ($listing->external_product_id ?? ''));

        if ($hbSku === '') {
            throw new DomainException(
                'Hepsiburada '.$operation.' sync için HB SKU zorunludur.',
            );
        }

        return $hbSku;
    }

    private function merchantSku(ChannelProductListing $listing): string
    {
        $listing->loadMissing('product');

        $sku = strtoupper(preg_replace(
            '/\s+/',
            '',
            trim((string) ($listing->external_sku ?: $listing->product->code)),
        ));

        if ($sku === '') {
            throw new DomainException('Hepsiburada merchantSku boş olamaz.');
        }

        return $sku;
    }

    /** @return list<string> */
    private function publicImages(ChannelProductListing $listing): array
    {
        $images = $this->images->urls($listing, 'Hepsiburada');

        if ($images === []) {
            throw new DomainException('Hepsiburada ürün görseli bulunamadı.');
        }

        if (collect($images)->contains(
            fn (string $url): bool => ! str_starts_with($url, 'https://'),
        )) {
            throw new DomainException('Hepsiburada görselleri public HTTPS URL olmalıdır.');
        }

        return $images;
    }

    private function integerQuantity(string $quantity): int
    {
        if (bccomp($quantity, '0', 3) <= 0) {
            return 0;
        }

        return (int) bcadd($quantity, '0', 0);
    }

    private function turkishPrice(string $price): string
    {
        return str_replace('.', ',', bcadd($price, '0', 2));
    }
}
