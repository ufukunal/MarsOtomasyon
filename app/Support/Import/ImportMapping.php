<?php

namespace App\Support\Import;

final class ImportMapping
{
    /** @return array<string, array{label:string, required:bool}> */
    public static function fields(string $type): array
    {
        return match ($type) {
            'contact' => [
                'title' => ['label' => 'Unvan', 'required' => true],
                'type' => ['label' => 'Tip', 'required' => false],
                'tax_office' => ['label' => 'Vergi Dairesi', 'required' => false],
                'tax_number' => ['label' => 'Vergi No', 'required' => false],
                'national_id' => ['label' => 'TC Kimlik', 'required' => false],
                'address' => ['label' => 'Adres', 'required' => false],
                'city' => ['label' => 'İl', 'required' => false],
                'district' => ['label' => 'İlçe', 'required' => false],
                'phone' => ['label' => 'Telefon', 'required' => false],
                'email' => ['label' => 'E-posta', 'required' => false],
                'term_days' => ['label' => 'Vade Günü', 'required' => false],
                'risk_limit' => ['label' => 'Risk Limiti', 'required' => false],
                'discount_rate' => ['label' => 'İskonto %', 'required' => false],
                'code' => ['label' => 'Cari Kodu', 'required' => false],
            ],
            'product' => [
                'code' => ['label' => 'Ürün Kodu', 'required' => true],
                'name' => ['label' => 'Ürün Adı', 'required' => true],
                'unit_code' => ['label' => 'Birim Kodu', 'required' => true],
                'barcode' => ['label' => 'Barkod', 'required' => false],
                'vat_rate' => ['label' => 'KDV %', 'required' => false],
                'list_price' => ['label' => 'Liste Fiyatı', 'required' => false],
                'currency' => ['label' => 'Para Birimi', 'required' => false],
                'kind' => ['label' => 'Tip', 'required' => false],
                'channel_stock_mode' => ['label' => 'Kanal Stok Modu', 'required' => false],
            ],
            'price_list' => [
                'list_name' => ['label' => 'Fiyat Listesi', 'required' => true],
                'product_code' => ['label' => 'Ürün Kodu', 'required' => true],
                'price' => ['label' => 'Fiyat', 'required' => true],
                'valid_from' => ['label' => 'Başlangıç', 'required' => false],
                'valid_to' => ['label' => 'Bitiş', 'required' => false],
            ],
            'opening_stock' => [
                'product_code' => ['label' => 'Ürün Kodu', 'required' => true],
                'location_code' => ['label' => 'Lokasyon Kodu', 'required' => true],
                'quantity' => ['label' => 'Miktar', 'required' => true],
                'unit_cost' => ['label' => 'Birim Maliyet', 'required' => true],
            ],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, string|null>  $mapping
     * @return array<string, mixed>
     */
    public static function map(array $row, array $mapping): array
    {
        $mapped = [];

        foreach ($mapping as $systemField => $sourceColumn) {
            $mapped[$systemField] = $sourceColumn !== null && $sourceColumn !== ''
                ? ($row[$sourceColumn] ?? null)
                : null;
        }

        return $mapped;
    }
}
