<?php

namespace Database\Seeders;

use App\Models\Period;
use App\Models\Period\Brand;
use App\Models\Period\Contact;
use App\Models\Period\Product;
use App\Models\Period\ProductCategory;
use App\Models\Period\Unit;
use App\Support\Period\PeriodContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Frozen public storefront snapshot, exclusively for isolated Mars UAT Lab.
 *
 * Price is the photographed storefront VAT-inclusive retail price in TRY.
 * The application's list_price is VAT-exclusive: 20% is only a demo VAT
 * assumption, never a verified SKU tax determination.
 * Existing products/images/prices and stock movements are never modified.
 */
final class UatStorefrontCatalogSeeder extends Seeder
{
    public function run(): void
    {
        if (env('MARS_UAT_STORE_CATALOG') !== 'YES'
            || DB::connection('master')->getDatabaseName() !== 'mars_uat_master') {
            throw new RuntimeException('BLOCKED: only explicitly authorized isolated UAT catalog import is permitted.');
        }

        $period = Period::query()->where('database_name', 'mars_uat_demo_2026')->firstOrFail();

        if ($period->status !== 'active' || (int) $period->year !== 2026) {
            throw new RuntimeException('BLOCKED: UAT period is not writable.');
        }

        $manifest = json_decode(
            (string) file_get_contents(resource_path('demo/storefront-catalog.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $entries = $manifest['products'] ?? [];
        if (count($entries) < 10 || (int) ($manifest['vat_assumption_percent'] ?? -1) !== 20) {
            throw new RuntimeException('BLOCKED: invalid catalog snapshot.');
        }

        PeriodContext::withinSystem($period, function () use ($entries, $manifest): void {
            if (DB::connection('period')->getDatabaseName() !== 'mars_uat_demo_2026') {
                throw new RuntimeException('BLOCKED: unexpected UAT period database.');
            }

            // Check all images exist before making even one database change.
            foreach ($entries as $entry) {
                $code = (string) $entry['code'];
                if (! preg_match('/^DM-(AV|HK)-[0-9]{3}$/D', $code)) {
                    throw new RuntimeException('Invalid demo product code.');
                }

                $source = (string) $entry['source'];
                $url = (string) $entry['url'];
                $validSource = match ($source) {
                    'Avizelerim' => str_starts_with($url, 'https://avizelerim.com/urun/'),
                    'Heka Store' => str_starts_with($url, 'https://hekastore.com/collections/avize/products/'),
                    default => false,
                };
                if (! $validSource) {
                    throw new RuntimeException('Invalid storefront product URL.');
                }

                $file = resource_path('demo/catalog-images/'.$code.'.bin');
                if (! is_file($file) || filesize($file) < 4000) {
                    throw new RuntimeException('Missing source photograph: '.$code);
                }
            }

            DB::connection('period')->transaction(function () use ($entries, $manifest): void {
                $unit = Unit::query()->firstOrCreate(
                    ['code' => 'ADET'],
                    ['name' => 'Adet', 'is_base' => true, 'is_active' => true],
                );

                // Partner contact only: do not fabricate production orders, sales or balances.
                Contact::query()->firstOrCreate(
                    ['title' => 'DEMO · Heka Store'],
                    [
                        'type' => 'legal',
                        'address' => 'UAT demo üretim iş ortağı. Gerçek cari işlemi değildir.',
                        'is_active' => true,
                    ]
                );

                foreach ($entries as $entry) {
                    $code = (string) $entry['code'];
                    $source = (string) $entry['source'];
                    $gross = (string) $entry['price_including_vat'];

                    if (! preg_match('/^\d{1,9}\.\d{2}$/D', $gross)) {
                        throw new RuntimeException('Invalid public price in snapshot: '.$code);
                    }

                    $category = ProductCategory::query()->firstOrCreate(
                        ['name' => 'DEMO · Avize'],
                        ['sort_order' => 900, 'is_active' => true]
                    );

                    $brand = Brand::query()->firstOrCreate(
                        ['name' => 'DEMO · '.$source],
                        ['is_active' => true]
                    );

                    $product = Product::query()->firstOrCreate(
                        ['code' => $code],
                        [
                            'name' => $entry['name'],
                            'description' => sprintf(
                                'UAT KATALOG DEMOSU · Kaynak: %s · Yayın fiyatı KDV dahil %s TRY (%s). '
                                .'Demo verisinde %%20 KDV varsayılarak KDV hariç fiyat hesaplandı. '
                                .'Kaynak ürün: %s · Gerçek stok veya üretim kaydı değildir.',
                                $source,
                                $gross,
                                $manifest['snapshot_date'],
                                $entry['url']
                            ),
                            'category_id' => $category->id,
                            'brand_id' => $brand->id,
                            'unit_id' => $unit->id,
                            'vat_rate' => '20.0000',
                            'list_price' => bcdiv($gross, '1.20', 4),
                            'currency' => 'TRY',
                            'kind' => 'normal',
                            'channel_stock_mode' => 'stock',
                            'is_active' => true,
                        ],
                    );

                    $name = strtolower($code).'-source-photo.png';
                    $exists = $product->attachments()
                        ->where('collection', 'Ortak')
                        ->where('original_name', $name)
                        ->exists();

                    if ($exists || $product->attachments()->where('collection', 'Ortak')->exists()) {
                        continue;
                    }

                    $image = (string) file_get_contents(
                        resource_path('demo/catalog-images/'.$code.'.bin')
                    );
                    $size = @getimagesizefromstring($image);
                    if (! $size || $size[0] < 120 || $size[1] < 120) {
                        throw new RuntimeException('Invalid source image dimensions: '.$code);
                    }

                    $decoded = @imagecreatefromstring($image);
                    if ($decoded === false) {
                        throw new RuntimeException('Invalid source image format: '.$code);
                    }

                    // Normalize storefront JPEG/PNG/WebP to the existing safe PNG attachment format.
                    ob_start();
                    $encoded = imagepng($decoded, null, 7);
                    $png = ob_get_clean();
                    imagedestroy($decoded);
                    if (! $encoded || ! is_string($png) || strlen($png) > 25 * 1024 * 1024) {
                        throw new RuntimeException('Could not normalize photo: '.$code);
                    }

                    $disk = (string) config('attachments.disk', 'attachments');
                    $path = 'uat-demo/storefronts/'.$name;
                    if (! Storage::disk($disk)->put($path, $png)) {
                        throw new RuntimeException('Unable to persist image: '.$code);
                    }

                    try {
                        $product->attachments()->firstOrCreate(
                            ['collection' => 'Ortak', 'original_name' => $name],
                            [
                                'disk' => $disk,
                                'path' => $path,
                                'mime' => 'image/png',
                                'size' => strlen($png),
                                'sort_order' => 0,
                                'uploaded_by_name' => 'Mars UAT Storefront Snapshot',
                            ]
                        );
                    } catch (\Throwable $error) {
                        Storage::disk($disk)->delete($path);
                        throw $error;
                    }
                }
            });

            $this->command?->info(
                'PASS: '.count($entries).' storefront demo products with stored real source photos; '
                .'existing records and stock unchanged.'
            );
        });
    }
}
