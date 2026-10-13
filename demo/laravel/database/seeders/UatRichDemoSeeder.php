<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Documents\CalculateDocumentTotals;
use App\Models\Period;
use App\Models\Period\Brand;
use App\Models\Period\Contact;
use App\Models\Period\ContactAddress;
use App\Models\Period\ContactPerson;
use App\Models\Period\Document;
use App\Models\Period\DocumentLine;
use App\Models\Period\ImportContainer;
use App\Models\Period\ImportCostItem;
use App\Models\Period\ImportFile;
use App\Models\Period\ImportPackage;
use App\Models\Period\Location;
use App\Models\Period\Product;
use App\Models\Period\ProductCategory;
use App\Models\Period\ProductionOrder;
use App\Models\Period\ProductionOrderComponent;
use App\Models\Period\ProductionRecipe;
use App\Models\Period\ProductionRecipeLine;
use App\Models\Period\StockBalance;
use App\Models\Period\StockMovement;
use App\Models\Period\Transfer;
use App\Models\Period\TransferLine;
use App\Models\Period\TransportSlip;
use App\Models\Period\TransportSlipItem;
use App\Models\Period\Unit;
use App\Models\Period\WarehouseSlip;
use App\Models\Period\WarehouseSlipLine;
use App\Support\Period\PeriodContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Opt-in, reproducible, non-destructive UAT fixture importer.
 *
 * Install extracted package at storage/app/uat-demo-rich/ and run ONLY in
 * an isolated test period after migrations and initial company setup.
 *
 * NOTE: Financially posted / completed scenario statuses in CSV are NOT
 * faked in the application. All trade documents are seeded as drafts. Full
 * accounting, stock postings, and completion flows require the Domain Actions.
 *
 * Explicit command:
 * MARS_UAT_RICH_DEMO_SEED=YES php artisan db:seed --class='Database\Seeders\UatRichDemoSeeder' --force
 */
final class UatRichDemoSeeder extends Seeder
{
    private string $base;

    /** @var array<string,int> */
    private array $productIds = [];
    /** @var array<string,int> */
    private array $contactIds = [];
    /** @var array<string,int> */
    private array $locationIds = [];
    /** @var array<string,int> */
    private array $documentIds = [];
    /** @var array<string,int> */
    private array $recipeIds = [];
    /** @var array<string,int> */
    private array $orderIds = [];
    /** @var array<string,int> */
    private array $fileIds = [];
    /** @var array<string,int> */
    private array $containerIds = [];
    private int $unitId;

    public function run(): void
    {
        if (env('MARS_UAT_RICH_DEMO_SEED') !== 'YES'
            || DB::connection('master')->getDatabaseName() !== 'mars_uat_master'
            || app()->environment('production')) {
            throw new RuntimeException('BLOCKED: explicit isolated UAT authorization required.');
        }

        $this->base = storage_path('app/uat-demo-rich');
        $manifestPath = $this->base.'/manifest.json';
        if (! is_file($manifestPath)) {
            throw new RuntimeException('BLOCKED: extracted UAT demo package is missing.');
        }
        $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        if (($manifest['version'] ?? null) !== '2026.10.13.1'
            || ($manifest['target_period'] ?? null) !== 'mars_uat_demo_2026'
            || ($manifest['counts']['products'] ?? null) !== 250
            || ($manifest['counts']['contacts'] ?? null) !== 100
            || ($manifest['counts']['documents'] ?? null) !== 1210) {
            throw new RuntimeException('BLOCKED: UAT demo manifest mismatch.');
        }

        // Check the required inputs and known attachment locations before writing.
        foreach (['products', 'contacts', 'locations', 'documents', 'document_lines',
            'import_files', 'containers', 'packages', 'production_recipes',
            'production_orders'] as $file) {
            if (! is_file($this->base.'/csv/'.$file.'.csv')) {
                throw new RuntimeException('BLOCKED: required CSV missing: '.$file);
            }
        }
        foreach ($this->csv('products') as $p) {
            foreach (['image_main', 'image_detail', 'technical_sheet'] as $asset) {
                $path = $this->base.'/'.($p[$asset] ?? '');
                if (! is_file($path) || filesize($path) < 1000) {
                    throw new RuntimeException('BLOCKED: missing asset for '.$p['sku'].': '.$asset);
                }
            }
        }

        $period = Period::query()->where('database_name', 'mars_uat_demo_2026')->firstOrFail();
        if ((int) $period->year !== 2026 || $period->status !== 'active') {
            throw new RuntimeException('BLOCKED: UAT 2026 period must be active.');
        }

        PeriodContext::withinSystem($period, function (): void {
            if (DB::connection('period')->getDatabaseName() !== 'mars_uat_demo_2026') {
                throw new RuntimeException('BLOCKED: period connection is not isolated UAT.');
            }
            DB::connection('period')->transaction(function (): void {
                $this->importReferenceCards();
                $this->importStock();
                $this->importDocuments();
                $this->importWarehouse();
                $this->importImports();
                $this->importProduction();
            }, attempts: 1);
            // Assets are added after the DB transaction; rerun can resume assets.
            $this->importAttachments();
            $this->command?->info('PASS: UAT rich demo master data + draft scenarios imported; posted states were not fabricated.');
        });
    }

    /** @return list<array<string,string>> */
    private function csv(string $name): array
    {
        $path = $this->base.'/csv/'.$name.'.csv';
        $fp = fopen($path, 'rb');
        if ($fp === false) {
            throw new RuntimeException('Cannot open UAT fixture file '.$name);
        }
        try {
            $header = fgetcsv($fp, 0, ',', '"', '\\');
            if (! is_array($header)) {
                throw new RuntimeException('Malformed fixture CSV '.$name);
            }
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]) ?? $header[0];
            $rows = [];
            while (($cells = fgetcsv($fp, 0, ',', '"', '\\')) !== false) {
                if (count($cells) !== count($header)) {
                    throw new RuntimeException('Invalid row column count in '.$name);
                }
                $rows[] = array_combine($header, $cells);
            }
            return $rows;
        } finally {
            fclose($fp);
        }
    }

    private function id(array $ids, string $key, string $kind): int
    {
        if (! isset($ids[$key])) {
            throw new RuntimeException('Missing demo '.$kind.' reference: '.$key);
        }
        return $ids[$key];
    }

    private function importReferenceCards(): void
    {
        $unit = Unit::query()->firstOrCreate(
            ['code' => 'ADET'], ['name' => 'Adet', 'is_base' => true, 'is_active' => true]
        );
        $this->unitId = (int) $unit->id;
        $categoryIds = [];
        $brandIds = [];
        foreach ($this->csv('products') as $r) {
            if (! isset($categoryIds[$r['category']])) {
                $category = ProductCategory::query()->firstOrCreate(
                    ['name' => 'DEMO · '.$r['category']],
                    ['sort_order' => 900, 'is_active' => true]
                );
                $categoryIds[$r['category']] = $category->id;
            }
            if (! isset($brandIds[$r['brand']])) {
                $brand = Brand::query()->firstOrCreate(
                    ['name' => $r['brand']], ['is_active' => true]
                );
                $brandIds[$r['brand']] = $brand->id;
            }
            $details = implode("\n", [
                'SENTETİK DEMO ÜRÜNÜ - teknik belgedeki veriler temsilidir.',
                'Model: '.$r['model'], 'Malzeme: '.$r['material'],
                'Kaplama: '.$r['finish'], 'Güç: '.$r['power_w'].' W',
                'CCT: '.$r['kelvin'].' K', 'CRI: '.$r['cri'],
                'IP: '.$r['ip_rating'], 'Ağırlık: '.$r['weight_kg'].' kg',
                'Ölçüler: '.$r['width_mm'].' x '.$r['height_mm'].' mm',
            ]);
            $p = Product::query()->firstOrCreate(['code' => $r['sku']], [
                'name' => $r['name'], 'description' => $details,
                'category_id' => $categoryIds[$r['category']], 'brand_id' => $brandIds[$r['brand']],
                'unit_id' => $this->unitId, 'barcode' => $r['barcode'],
                'list_price' => $r['list_price_try'], 'vat_rate' => $r['vat_rate'],
                'currency' => 'TRY', 'kind' => 'normal', 'channel_stock_mode' => $r['stock_mode'],
                'min_stock' => $r['min_stock'], 'is_active' => true,
            ]);
            if ((string) $p->name !== (string) $r['name']) {
                throw new RuntimeException('BLOCKED: existing product code collision: '.$r['sku']);
            }
            $this->productIds[$r['sku']] = (int) $p->id;
        }
        foreach ($this->csv('contacts') as $r) {
            $contact = Contact::query()->firstOrCreate(['title' => $r['name']], [
                'type' => $r['type'], 'tax_office' => $r['tax_office'],
                'address' => $r['address'], 'city' => $r['city'], 'district' => $r['district'],
                'phone' => $r['phone'], 'email' => $r['email'],
                'term_days' => (int) $r['term_days'], 'risk_limit' => $r['risk_limit'],
                'discount_rate' => $r['discount_rate'], 'is_active' => $r['status'] === 'active',
            ]);
            $this->contactIds[$r['code']] = (int) $contact->id;
        }
        foreach ($this->csv('contact_addresses') as $r) {
            $id = $this->id($this->contactIds, $r['contact_code'], 'contact');
            ContactAddress::query()->firstOrCreate(
                ['contact_id' => $id, 'type' => $r['type'], 'title' => $r['name']],
                ['address' => $r['address'], 'city' => $r['city'], 'district' => $r['district'], 'is_default' => true]
            );
        }
        foreach ($this->csv('contact_people') as $r) {
            ContactPerson::query()->firstOrCreate(
                ['contact_id' => $this->id($this->contactIds, $r['contact_code'], 'contact'), 'name' => $r['name']],
                ['title' => $r['title'], 'phone' => $r['phone'], 'email' => $r['email'], 'is_default' => true]
            );
        }
        foreach ($this->csv('locations') as $r) {
            $location = Location::query()->firstOrCreate(['code' => $r['code']], [
                'name' => $r['name'], 'kind' => $r['kind'],
                'plate' => $r['plate'] ?: null,
                'subcontractor_contact_id' => $r['subcontractor_contact_code']
                    ? $this->id($this->contactIds, $r['subcontractor_contact_code'], 'subcontractor') : null,
                'is_default' => false, 'is_active' => true,
            ]);
            $this->locationIds[$r['code']] = (int) $location->id;
        }
    }

    private function importStock(): void
    {
        foreach ($this->csv('stock_balances') as $r) {
            $pid = $this->id($this->productIds, $r['sku'], 'product');
            $lid = $this->id($this->locationIds, $r['location_code'], 'location');
            $stock = StockBalance::query()->firstOrCreate(
                ['product_id' => $pid, 'location_id' => $lid],
                ['quantity' => $r['quantity'], 'reserved' => '0.000',
                 'consignment_reserved' => '0.000', 'quarantine' => '0.000']
            );
            if ($stock->wasRecentlyCreated) {
                $unitCost = $r['unit_cost_try'];
                StockMovement::query()->create([
                    'product_id' => $pid, 'location_id' => $lid, 'product_code' => $r['sku'],
                    'movement_date' => '2026-01-06', 'direction' => 'in', 'reason' => 'opening',
                    'quantity' => $r['quantity'], 'unit_cost' => $unitCost,
                    'total_cost' => bcmul($r['quantity'], $unitCost, 4),
                    'balance_after' => $r['quantity'], 'avg_cost_after' => $unitCost,
                    'document_type' => 'demo_opening',
                    'document_no' => 'DM26-OPEN-'.$r['sku'].'-'.$r['location_code'],
                    'note' => 'Isolated UAT opening snapshot; not a real commercial posting.',
                    'created_by_name' => 'Mars UAT Rich Demo',
                ]);
            }
        }
    }

    private function importDocuments(): void
    {
        $rows = $this->csv('document_lines');
        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r['document_no']][] = $r;
        }
        $calc = app(CalculateDocumentTotals::class);
        foreach ($this->csv('documents') as $r) {
            $docLines = $grouped[$r['number']] ?? [];
            if ($docLines === []) {
                throw new RuntimeException('Missing document lines: '.$r['number']);
            }
            $inputs = [];
            foreach ($docLines as $line) {
                $inputs[] = [
                    'quantity' => $line['quantity'], 'unit_price' => $line['unit_price'],
                    'line_discount_rate' => '0', 'line_discount_amount' => '0',
                    'vat_rate' => $line['vat_rate'],
                ];
            }
            $totals = $calc->handle($inputs);
            if (bccomp($totals->grandTotal, $r['grand_total'], 2) !== 0) {
                throw new RuntimeException('BLOCKED: invoice total mismatch: '.$r['number']);
            }
            $doc = Document::query()->firstOrCreate(['document_type' => $r['document_type'], 'number' => $r['number']], [
                'revision_no' => $r['document_type'] === 'quote' ? 1 : 0,
                'document_date' => $r['document_date'], 'due_date' => $r['due_date'],
                'contact_id' => $this->id($this->contactIds, $r['contact_code'], 'contact'),
                'currency' => 'TRY', 'exchange_rate' => '1.000000', 'status' => 'draft',
                'discount_rate' => $totals->discountRate, 'discount_amount' => $totals->discountAmount,
                'subtotal' => $totals->subtotal, 'tax_base' => $totals->taxBase,
                'vat_amount' => $totals->vatAmount, 'rounding_difference' => $totals->roundingDifference,
                'grand_total' => $totals->grandTotal,
                'notes' => 'UAT sentetik. Hedef senaryo='.$r['scenario_status'].'; kaynak='.$r['linked_source'].'. '
                    .'Belge gerçek muhasebeleştirme yapılmadan TASLAK tutulur.',
                'created_by_name' => 'Mars UAT Rich Demo',
            ]);
            if ($doc->status !== 'draft') {
                throw new RuntimeException('BLOCKED: demo importer refuses non-draft document: '.$r['number']);
            }
            $this->documentIds[$r['number']] = (int) $doc->id;
            foreach ($docLines as $k => $line) {
                $pid = $this->id($this->productIds, $line['sku'], 'product');
                DocumentLine::query()->firstOrCreate(
                    ['document_id' => $doc->id, 'line_no' => (int) $line['line_no']],
                    ['line_kind' => 'stock', 'product_id' => $pid, 'description' => $line['description'],
                     'unit_id' => $this->unitId, 'quantity' => $line['quantity'],
                     'conversion_factor' => '1.000000', 'base_quantity' => $line['quantity'],
                     'location_id' => $this->id($this->locationIds, $line['location_code'], 'location'),
                     'unit_price' => $line['unit_price'], 'vat_rate' => $line['vat_rate'],
                     'line_discount_rate' => $totals->lines[$k]->discountRate,
                     'line_discount_amount' => $totals->lines[$k]->discountAmount,
                     'line_total' => $totals->lines[$k]->lineTotal,
                     'reserve_stock' => false, 'cancelled_quantity' => '0.000']
                );
            }
        }
    }

    private function importWarehouse(): void
    {
        foreach ($this->csv('warehouse_slips') as $r) {
            $slip = WarehouseSlip::query()->firstOrCreate(['number' => $r['number']], [
                'slip_date' => $r['date'], 'location_id' => $this->id($this->locationIds, $r['location_code'], 'location'),
                'direction' => $r['direction'], 'reason' => $r['reason'], 'status' => 'draft',
                'note' => 'UAT demo target='.$r['scenario_status'].'; no physical posting',
            ]);
            if ($slip->status !== 'draft') {
                throw new RuntimeException('BLOCKED: existing non-draft warehouse slip');
            }
        }
        foreach ($this->csv('warehouse_slip_lines') as $r) {
            $slip = WarehouseSlip::query()->where('number', $r['slip_no'])->firstOrFail();
            WarehouseSlipLine::query()->firstOrCreate(
                ['warehouse_slip_id' => $slip->id, 'product_id' => $this->id($this->productIds, $r['sku'], 'product')],
                ['quantity' => $r['quantity'], 'unit_cost' => $r['unit_cost_try']]
            );
        }
        foreach ($this->csv('transfers') as $r) {
            $trans = Transfer::query()->firstOrCreate(['number' => $r['number']], [
                'from_location_id' => $this->id($this->locationIds, $r['from_location_code'], 'location'),
                'to_location_id' => $this->id($this->locationIds, $r['to_location_code'], 'location'),
                'transfer_date' => $r['date'], 'status' => 'draft',
                'note' => 'UAT demo target='.$r['scenario_status'].'; no real stock move',
            ]);
            if ($trans->status !== 'draft') {
                throw new RuntimeException('BLOCKED: existing non-draft transfer');
            }
        }
        foreach ($this->csv('transfer_lines') as $r) {
            $trans = Transfer::query()->where('number', $r['transfer_no'])->firstOrFail();
            TransferLine::query()->firstOrCreate(
                ['transfer_id' => $trans->id, 'product_id' => $this->id($this->productIds, $r['sku'], 'product')],
                ['quantity' => $r['quantity'], 'received_quantity' => '0.000']
            );
        }
        foreach ($this->csv('transport_slips') as $r) {
            $slip = TransportSlip::query()->firstOrCreate(['number' => $r['number']], [
                'slip_date' => $r['slip_date'],
                'contact_id' => $this->id($this->contactIds, $r['contact_code'], 'contact'),
                'location_id' => $this->id($this->locationIds, $r['location_code'], 'location'),
                'order_reference' => $r['order_reference'], 'dispatch_reference' => $r['dispatch_reference'],
                'shipping_type' => $r['shipping_type'], 'carrier' => $r['carrier'],
                'destination_warehouse' => $r['destination_warehouse'],
                'package_count' => (int) $r['package_count'], 'weight_kg' => $r['weight_kg'],
                'tracking_number' => $r['tracking_number'],
                'contains_glass' => $r['contains_glass'] === 'True',
                'fragile' => $r['fragile'] === 'True',
                'keep_upright' => $r['keep_upright'] === 'True',
                'do_not_stack' => $r['do_not_stack'] === 'True',
                'delivery_company' => $r['delivery_company'], 'delivery_person' => $r['delivery_person'],
                'delivery_phone' => $r['delivery_phone'], 'delivery_city' => $r['delivery_city'],
                'delivery_district' => $r['delivery_district'], 'delivery_address' => $r['delivery_address'],
                'note' => $r['notes'], 'status' => 'draft',
            ]);
        }
        foreach ($this->csv('transport_slip_items') as $r) {
            $slip = TransportSlip::query()->where('number', $r['transport_slip_no'])->firstOrFail();
            TransportSlipItem::query()->firstOrCreate(
                ['transport_slip_id' => $slip->id, 'product_id' => $this->id($this->productIds, $r['sku'], 'product')],
                ['quantity' => $r['quantity']]
            );
        }
    }

    private function importImports(): void
    {
        foreach ($this->csv('import_files') as $r) {
            $file = ImportFile::query()->firstOrCreate(['number' => $r['number']], [
                'supplier_contact_id' => $this->id($this->contactIds, $r['supplier_contact_code'], 'contact'),
                'receiving_location_id' => $this->id($this->locationIds, $r['receiving_location_code'], 'location'),
                'country' => $r['country'], 'incoterm' => $r['incoterm'], 'currency' => $r['currency'],
                'exchange_rate' => $r['exchange_rate'], 'etd' => $r['etd'], 'eta' => $r['eta'],
                'status' => 'draft', 'notes' => $r['notes'].' Hedef durum: '.$r['scenario_status'],
                'created_by_name' => 'Mars UAT Rich Demo',
            ]);
            if ($file->status !== 'draft') {
                throw new RuntimeException('BLOCKED: existing non-draft import file');
            }
            $this->fileIds[$r['number']] = (int) $file->id;
        }
        foreach ($this->csv('containers') as $r) {
            $cid = $this->id($this->fileIds, $r['import_file_no'], 'import file');
            $c = ImportContainer::query()->firstOrCreate(['import_file_id' => $cid, 'container_no' => $r['container_no']], [
                'container_type' => $r['container_type'], 'seal_no' => $r['seal_no'],
                'gross_weight_kg' => $r['gross_weight_kg'], 'volume_cbm' => $r['volume_cbm'],
                'etd' => $r['etd'], 'eta' => $r['eta'], 'status' => 'planned',
                'notes' => 'DEMO '.$r['number'],
            ]);
            $this->containerIds[$r['number']] = (int) $c->id;
        }
        foreach ($this->csv('packages') as $r) {
            ImportPackage::query()->firstOrCreate(
                ['import_file_id' => $this->id($this->fileIds, $r['import_file_no'], 'import file'), 'carton_no' => $r['carton_no']],
                ['container_id' => $this->id($this->containerIds, $r['container_no'], 'container'),
                 'product_id' => $this->id($this->productIds, $r['sku'], 'product'),
                 'location_id' => $this->id($this->locationIds, $r['location_code'], 'location'),
                 'component_name' => 'DEMO ürün/koli', 'quantity' => $r['quantity'],
                 'unit_price' => $r['unit_price_foreign'], 'weight_kg' => $r['weight_kg'],
                 'volume_cbm' => $r['volume_cbm'], 'status' => $r['database_seed_status'],
                 'notes' => $r['notes']]
            );
        }
        foreach ($this->csv('import_cost_items') as $r) {
            ImportCostItem::query()->firstOrCreate(
                ['import_file_id' => $this->id($this->fileIds, $r['import_file_no'], 'import file'), 'name' => $r['cost_name']],
                ['amount' => $r['amount'], 'currency' => $r['currency'],
                 'exchange_rate' => '1.000000', 'amount_try' => $r['amount'],
                 'allocation_basis' => $r['allocation_basis'],
                 'notes' => 'DEMO maliyet; dağıtım henüz çalıştırılmadı.']
            );
        }
    }

    private function importProduction(): void
    {
        foreach ($this->csv('production_recipes') as $r) {
            $recipe = ProductionRecipe::query()->firstOrCreate(
                ['product_id' => $this->id($this->productIds, $r['sku'], 'product'), 'revision_no' => 1],
                ['number' => $r['number'], 'output_quantity' => $r['output_quantity'],
                 'is_active' => true, 'created_by_name' => 'Mars UAT Rich Demo']
            );
            $this->recipeIds[$r['number']] = (int) $recipe->id;
        }
        foreach ($this->csv('production_recipe_lines') as $r) {
            $recipeId = $this->id($this->recipeIds, $r['recipe_no'], 'recipe');
            ProductionRecipeLine::query()->firstOrCreate(
                ['production_recipe_id' => $recipeId,
                 'component_product_id' => $this->id($this->productIds, $r['component_sku'], 'component')],
                ['unit_id' => $this->unitId, 'quantity' => $r['quantity'],
                 'base_quantity' => number_format((float) $r['quantity'], 3, '.', ''),
                 'conversion_factor' => '1.000000']
            );
        }
        foreach ($this->csv('production_orders') as $r) {
            $order = ProductionOrder::query()->firstOrCreate(['number' => $r['number']], [
                'document_date' => $r['document_date'],
                'product_id' => $this->id($this->productIds, $r['sku'], 'product'),
                'recipe_id' => $this->id($this->recipeIds, $r['recipe_no'], 'recipe'),
                'recipe_revision_no' => 1, 'planned_quantity' => $r['planned_quantity'],
                'production_type' => $r['production_type'],
                'subcontractor_contact_id' => $r['subcontractor_contact_code']
                    ? $this->id($this->contactIds, $r['subcontractor_contact_code'], 'subcontractor') : null,
                'subcontractor_location_id' => $r['subcontractor_location_code']
                    ? $this->id($this->locationIds, $r['subcontractor_location_code'], 'subcontractor location') : null,
                'source_sales_order_id' => $this->id($this->documentIds, $r['source_sales_order_no'], 'sales order'),
                'status' => 'draft', 'notes' => $r['notes'].' Hedef='.$r['scenario_status'],
                'created_by_name' => 'Mars UAT Rich Demo',
            ]);
            if ($order->status !== 'draft') {
                throw new RuntimeException('BLOCKED: existing non-draft production order');
            }
            $this->orderIds[$r['number']] = (int) $order->id;
        }
        foreach ($this->csv('production_order_components') as $r) {
            ProductionOrderComponent::query()->firstOrCreate(
                ['production_order_id' => $this->id($this->orderIds, $r['production_order_no'], 'production order'),
                 'component_product_id' => $this->id($this->productIds, $r['component_sku'], 'component')],
                ['unit_id' => $this->unitId,
                 'planned_quantity' => number_format((float) $r['planned_quantity'], 3, '.', ''),
                 'planned_base_quantity' => number_format((float) $r['planned_quantity'], 3, '.', ''),
                 'conversion_factor' => '1.000000']
            );
        }
    }

    private function importAttachments(): void
    {
        $disk = (string) config('attachments.disk', 'attachments');
        foreach ($this->csv('products') as $r) {
            $product = Product::query()->findOrFail($this->id($this->productIds, $r['sku'], 'product'));
            foreach (['image_main', 'image_detail', 'technical_sheet'] as $key) {
                $relative = $r[$key];
                $source = $this->base.'/'.$relative;
                $name = basename($relative);
                $collection = $key === 'technical_sheet' ? 'Teknik Föy' : 'Ortak';
                $original = $product->attachments()
                    ->where('collection', $collection)
                    ->where('original_name', $name)->first();
                if ($original) {
                    continue;
                }
                // Do not reorder or override any pre-existing gallery created by an operator.
                if ($collection === 'Ortak' && $product->attachments()
                    ->where('collection', 'Ortak')->where('uploaded_by_name', '!=', 'Mars UAT Rich Demo')->exists()) {
                    continue;
                }
                $bytes = file_get_contents($source);
                if ($bytes === false) {
                    throw new RuntimeException('Missing UAT image or technical sheet: '.$relative);
                }
                $target = 'uat-demo/rich/'.$relative;
                if (! Storage::disk($disk)->put($target, $bytes)) {
                    throw new RuntimeException('Cannot save UAT demo attachment: '.$relative);
                }
                $product->attachments()->firstOrCreate(
                    ['collection' => $collection, 'original_name' => $name],
                    ['disk' => $disk, 'path' => $target,
                     'mime' => $key === 'technical_sheet' ? 'application/pdf' : 'image/png',
                     'size' => strlen($bytes), 'sort_order' => $key === 'image_detail' ? 1 : 0,
                     'uploaded_by_name' => 'Mars UAT Rich Demo']
                );
            }
        }
    }
}
