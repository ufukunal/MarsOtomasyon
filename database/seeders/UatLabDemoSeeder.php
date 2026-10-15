<?php

namespace Database\Seeders;

use App\Models\Period;
use App\Models\Period\Brand;
use App\Models\Period\Contact;
use App\Models\Period\Location;
use App\Models\Period\Product;
use App\Models\Period\ProductCategory;
use App\Models\Period\StockBalance;
use App\Models\Period\StockMovement;
use App\Models\Period\Unit;
use App\Support\Period\PeriodContext;
use App\Support\Products\UatDemoImagePainter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Non-destructive, repeatable sample dataset for the existing isolated UAT Lab.
 * Never runs in production databases; no truncate, delete, or real-company edits.
 * Explicit execution:
 * MARS_UAT_DEMO_SEED=YES php artisan db:seed --class=Database\\Seeders\\UatLabDemoSeeder --force
 */
final class UatLabDemoSeeder extends Seeder
{
    public function run(): void
    {
        // The raw operator/UAT guard is intentionally not taken from cached config.
        // @phpstan-ignore-next-line
        if (env('MARS_UAT_DEMO_SEED') !== 'YES'
            || DB::connection('master')->getDatabaseName() !== 'mars_uat_master') {
            throw new RuntimeException('BLOCKED: explicit UAT-only demo seeding required.');
        }

        $period = Period::query()->where('database_name', 'mars_uat_demo_2026')->firstOrFail();
        if ((int) $period->year !== 2026 || $period->status !== 'active') {
            throw new RuntimeException('BLOCKED: target is not the active UAT 2026 period.');
        }

        PeriodContext::withinSystem($period, function (): void {
            if (DB::connection('period')->getDatabaseName() !== 'mars_uat_demo_2026') {
                throw new RuntimeException('BLOCKED: period DB does not match isolated UAT demo.');
            }

            DB::connection('period')->transaction(function (): void {
                $unit = Unit::query()->firstOrCreate(
                    ['code' => 'ADET'],
                    ['name' => 'Adet', 'is_base' => true, 'is_active' => true],
                );

                $categories = [];
                foreach (['Aydınlatma', 'Elektrik Malzemeleri', 'Dekorasyon'] as $label) {
                    $name = 'DEMO · '.$label;
                    $categories[$label] = ProductCategory::query()->firstOrCreate(
                        ['name' => $name],
                        ['sort_order' => 900, 'is_active' => true],
                    );
                }

                $brands = [];
                foreach (['Noya', 'Aurora', 'Tekno'] as $label) {
                    $brands[$label] = Brand::query()->firstOrCreate(
                        ['name' => 'DEMO · '.$label],
                        ['is_active' => true],
                    );
                }

                $locations = [];
                foreach ([
                    ['code' => 'DM-UAT-MERKEZ', 'name' => 'DEMO · Merkez Depo'],
                    ['code' => 'DM-UAT-MAGAZA', 'name' => 'DEMO · Mağaza Deposu'],
                ] as $item) {
                    $locations[] = Location::query()->firstOrCreate(
                        ['code' => $item['code']],
                        ['name' => $item['name'], 'kind' => 'warehouse', 'is_default' => false, 'is_active' => true],
                    );
                }

                foreach ([
                    ['DEMO · Kuzey Yapı A.Ş.', 'İstanbul', 'buyer'],
                    ['DEMO · Doğu Elektrik Ltd.', 'Ankara', 'buyer'],
                    ['DEMO · Aydınlatma Tedarik A.Ş.', 'İzmir', 'supplier'],
                ] as [$name, $city, $role]) {
                    Contact::query()->firstOrCreate(
                        ['title' => $name],
                        [
                            'type' => 'legal',
                            'city' => $city,
                            'address' => 'Yalnızca UAT test kaydı',
                            'is_active' => true,
                            'term_days' => 30,
                            'risk_limit' => '50000.0000',
                        ],
                    );
                }

                $items = [
                    // code, name, brand, category, price, warehouse stock, reserved
                    ['DM-UAT-001', 'DEMO · Aurora Gold Avize', 'Aurora', 'Aydınlatma', '4200.0000', '18.000', '4.000'],
                    ['DM-UAT-002', 'DEMO · Luna Kristal Avize', 'Noya', 'Aydınlatma', '6850.0000', '7.000', '2.000'],
                    ['DM-UAT-003', 'DEMO · Salon Sarkıt', 'Noya', 'Aydınlatma', '2650.0000', '21.000', '0.000'],
                    ['DM-UAT-004', 'DEMO · LED Sürücü 24V', 'Tekno', 'Elektrik Malzemeleri', '375.0000', '80.000', '12.000'],
                    ['DM-UAT-005', 'DEMO · Duvar Aplik', 'Aurora', 'Dekorasyon', '1950.0000', '33.000', '5.000'],
                    ['DM-UAT-006', 'DEMO · Kristal Bağlantı Aparatı', 'Tekno', 'Elektrik Malzemeleri', '18.5000', '240.000', '60.000'],
                ];

                foreach ($items as [$code, $name, $brand, $category, $price, $qty, $reserved]) {
                    $product = Product::query()->firstOrCreate(
                        ['code' => $code],
                        [
                            'name' => $name,
                            'brand_id' => $brands[$brand]->id,
                            'category_id' => $categories[$category]->id,
                            'unit_id' => $unit->id,
                            'list_price' => $price,
                            'currency' => 'TRY',
                            'vat_rate' => '20.0000',
                            'kind' => 'normal',
                            'channel_stock_mode' => 'stock',
                            'is_active' => true,
                        ],
                    );

                    $this->seedProductImages($product, (int) substr($code, -1));

                    // Do not change an existing product or an existing stock balance.
                    $balance = StockBalance::query()->firstOrCreate(
                        ['product_id' => $product->id, 'location_id' => $locations[0]->id],
                        [
                            'quantity' => $qty,
                            'reserved' => $reserved,
                            'consignment_reserved' => '0.000',
                            'quarantine' => '0.000',
                        ],
                    );

                    // One matching immutable opening movement, inserted only once.
                    // Never manufacture a movement for a pre-existing balance.
                    if ($balance->wasRecentlyCreated) {
                        StockMovement::query()->firstOrCreate(
                            [
                                'product_id' => $product->id,
                                'location_id' => $locations[0]->id,
                                'document_no' => 'DM-UAT-OPEN-'.$code,
                            ],
                            [
                                'product_code' => $code,
                                'movement_date' => '2026-01-15',
                                'direction' => 'in',
                                'reason' => 'opening',
                                'quantity' => $qty,
                                'unit_cost' => '0.0000',
                                'total_cost' => '0.0000',
                                'balance_after' => $qty,
                                'avg_cost_after' => '0.0000',
                                'document_type' => 'demo_opening',
                                'note' => 'UAT demo verisi; gerçek ticari işlem değildir.',
                                'created_by_name' => 'Mars UAT Demo',
                            ],
                        );
                    }
                }

                // The raw operator/UAT guard is intentionally not taken from cached config.
                // @phpstan-ignore-next-line
                $this->command?->info('PASS: isolated UAT demo: 6 products, 12 illustrations, 3 contacts, 2 warehouses; existing records preserved.');
            });
        });
    }

    /**
     * Add a cover and one gallery image only to demo products with no prior
     * images. Never reorder/delete/replace an existing cover. All assets stay
     * on the authenticated attachment disk, shared across UAT web releases.
     */
    private function seedProductImages(Product $product, int $variant): void
    {
        $collection = (string) config('product_images.fallback', 'Ortak');
        $disk = (string) config('attachments.disk', 'attachments');
        // Existing demo assets are updated only by an explicit, UAT-only opt-in.
        // User-uploaded images and other collections are never altered.
        // The raw operator/UAT guard is intentionally not taken from cached config.
        // @phpstan-ignore-next-line
        $refresh = env('MARS_UAT_DEMO_REFRESH_IMAGES') === 'YES';

        foreach ([false, true] as $gallery) {
            $role = $gallery ? 'gallery' : 'cover';
            $name = strtolower($product->code).'-'.$role.'.png';
            $path = 'uat-demo/products/v2/'.$name;

            $existing = $product->attachments()
                ->where('collection', $collection)
                ->where('original_name', $name)
                ->where('uploaded_by_name', 'Mars UAT Demo')
                ->first();

            if ($existing && ! $refresh) {
                continue;
            }

            // Do not insert a new cover into a product that already has any
            // non-demo attachment: this avoids overriding someone's cover order.
            if (! $existing && $product->attachments()->where('collection', $collection)->exists()) {
                continue;
            }

            $png = UatDemoImagePainter::png($variant, $gallery);
            if (! Storage::disk($disk)->put($path, $png)) {
                throw new RuntimeException('Demo ürün görseli diske yazılamadı: '.$name);
            }

            try {
                if ($existing) {
                    // The attachment ID/order stay unchanged; only the
                    // UAT-generated image bytes move to the new versioned path.
                    $existing->update([
                        'disk' => $disk,
                        'path' => $path,
                        'mime' => 'image/png',
                        'size' => strlen($png),
                    ]);
                } else {
                    $product->attachments()->firstOrCreate(
                        ['collection' => $collection, 'original_name' => $name],
                        [
                            'disk' => $disk,
                            'path' => $path,
                            'mime' => 'image/png',
                            'size' => strlen($png),
                            'sort_order' => $gallery ? 1 : 0,
                            'uploaded_by_name' => 'Mars UAT Demo',
                        ],
                    );
                }
            } catch (\Throwable $error) {
                Storage::disk($disk)->delete($path);
                throw $error;
            }
        }
    }
}
