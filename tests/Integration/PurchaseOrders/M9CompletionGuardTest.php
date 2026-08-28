<?php

use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Tax;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\PurchaseOrders\Enums\PurchaseOrderProgressType;
use App\Modules\PurchaseOrders\Enums\PurchaseOrderStatus;
use App\Modules\PurchaseOrders\Models\PurchaseOrder;
use App\Modules\PurchaseOrders\Progress\PurchaseOrderProgressService;
use App\Modules\Quotes\Pricing\PriceBasis;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(DatabaseMigrations::class);

it('keeps cancelled progress independent, idempotent and bounded against receipt and invoice capacity', function (): void {
    [$company, $supplier, $product, $tax, $warehouse, $location] = m9CompletionFixture('M9-CANCEL');
    $order = m9CompletionOrder($company, $supplier, $product, $tax, $warehouse, $location, '10');
    $line = $order->lines()->firstOrFail();
    $progress = app(PurchaseOrderProgressService::class);

    DB::transaction(function () use ($company, $line, $progress): void {
        $progress->record(
            new SourceEffectIdentity((int) $company->getKey(), 'm9_completion', 'received-1', 'progress.receive'),
            (int) $line->getKey(),
            PurchaseOrderProgressType::Received,
            '3',
        );
        $progress->record(
            new SourceEffectIdentity((int) $company->getKey(), 'm9_completion', 'invoice-1', 'progress.invoice'),
            (int) $line->getKey(),
            PurchaseOrderProgressType::Invoiced,
            '2',
        );
        $first = $progress->record(
            new SourceEffectIdentity((int) $company->getKey(), 'm9_completion', 'cancel-1', 'progress.cancel'),
            (int) $line->getKey(),
            PurchaseOrderProgressType::Cancelled,
            '4',
        );
        $replay = $progress->record(
            new SourceEffectIdentity((int) $company->getKey(), 'm9_completion', 'cancel-1', 'progress.cancel'),
            (int) $line->getKey(),
            PurchaseOrderProgressType::Cancelled,
            '4.000000',
        );

        expect($replay->getKey())->toBe($first->getKey());
    });

    $projection = DB::table('purchase_order_line_progress')
        ->where('purchase_order_line_id', $line->getKey())
        ->first();

    expect($projection)->not->toBeNull()
        ->and((string) $projection->net_received_quantity)->toBe('3.000000')
        ->and((string) $projection->net_invoiced_quantity)->toBe('2.000000')
        ->and((string) $projection->cancelled_quantity)->toBe('4.000000')
        ->and((string) $projection->receive_remaining_quantity)->toBe('3.000000')
        ->and((string) $projection->invoice_remaining_quantity)->toBe('4.000000')
        ->and(DB::table('purchase_order_line_progress_effects')->where('progress_type', 'cancelled')->count())->toBe(1);

    expect(fn () => DB::transaction(fn () => $progress->record(
        new SourceEffectIdentity((int) $company->getKey(), 'm9_completion', 'cancel-over', 'progress.cancel'),
        (int) $line->getKey(),
        PurchaseOrderProgressType::Cancelled,
        '4',
    )))->toThrow(ValidationException::class);

    $projection = DB::table('purchase_order_line_progress')
        ->where('purchase_order_line_id', $line->getKey())
        ->first();

    expect($projection)->not->toBeNull()
        ->and((string) $projection->cancelled_quantity)->toBe('4.000000')
        ->and((string) $projection->receive_remaining_quantity)->toBe('3.000000')
        ->and((string) $projection->invoice_remaining_quantity)->toBe('4.000000');
});

/** @return array{Company,Account,Product,Tax,Warehouse,WarehouseLocation} */
function m9CompletionFixture(string $code): array
{
    $company = Company::query()->create(['code' => $code, 'name' => 'Company '.$code]);
    $supplier = Account::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'SUP',
        'type' => AccountType::Supplier,
        'status' => AccountStatus::Active,
        'legal_name' => 'Tedarikçi '.$code,
        'trade_name' => null,
        'tax_identity_type' => TaxIdentityType::None,
        'tax_number' => null,
        'tax_office' => null,
        'book_currency_code' => 'TRY',
        'due_days' => 0,
        'discount_rate' => '0.000000',
        'risk_limit' => '0.000000',
    ]);
    $category = Category::query()->create([
        'company_id' => $company->getKey(), 'code' => 'CAT', 'name' => 'Kategori', 'is_active' => true,
    ]);
    $unit = Unit::query()->create([
        'company_id' => $company->getKey(), 'code' => 'ADET', 'name' => 'Adet', 'is_active' => true,
    ]);
    $tax = Tax::query()->create([
        'company_id' => $company->getKey(), 'code' => 'KDV20', 'name' => 'KDV %20', 'rate' => '20.000000', 'is_active' => true,
    ]);
    $product = Product::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'SKU',
        'status' => ProductStatus::Active,
        'name' => 'Ürün '.$code,
        'category_id' => $category->getKey(),
        'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(),
        'sale_price_net' => '120.000000',
        'purchase_price_net' => '100.000000',
    ]);
    $warehouse = Warehouse::query()->create([
        'company_id' => $company->getKey(), 'code' => 'WH', 'name' => 'Ana Depo', 'is_active' => true,
    ]);
    $location = WarehouseLocation::query()->create([
        'company_id' => $company->getKey(),
        'warehouse_id' => $warehouse->getKey(),
        'code' => 'A1',
        'name' => 'A1',
        'is_active' => true,
    ]);

    return [$company, $supplier, $product, $tax, $warehouse, $location];
}

function m9CompletionOrder(
    Company $company,
    Account $supplier,
    Product $product,
    Tax $tax,
    Warehouse $warehouse,
    WarehouseLocation $location,
    string $quantity,
): PurchaseOrder {
    $totals = DB::selectOne(
        'SELECT CAST(CAST(? AS numeric) * 100 AS numeric(20,6))::text AS base, '
        .'CAST(CAST(? AS numeric) * 20 AS numeric(20,6))::text AS tax, '
        .'CAST(CAST(? AS numeric) * 120 AS numeric(20,6))::text AS gross',
        [$quantity, $quantity, $quantity],
    );
    if ($totals === null) {
        throw new RuntimeException('M9 completion fixture totals could not be calculated.');
    }

    $order = PurchaseOrder::query()->create([
        'company_id' => $company->getKey(),
        'account_id' => $supplier->getKey(),
        'number' => 'PO-'.$company->code,
        'series_code' => 'default',
        'sequence_value' => 1,
        'status' => PurchaseOrderStatus::Open,
        'order_date' => '2026-08-27',
        'currency_code' => 'TRY',
        'document_discount_rate' => '0.000000',
        'base_net_total' => (string) $totals->base,
        'line_discount_total' => '0.000000',
        'document_discount_total' => '0.000000',
        'net_total' => (string) $totals->base,
        'tax_total' => (string) $totals->tax,
        'gross_total' => (string) $totals->gross,
        'note' => null,
        'opened_at' => now(),
    ]);
    $order->lines()->create([
        'company_id' => $company->getKey(),
        'logical_line_key' => (string) Str::uuid(),
        'position' => 1,
        'product_id' => $product->getKey(),
        'warehouse_id' => $warehouse->getKey(),
        'location_id' => $location->getKey(),
        'product_code' => $product->code,
        'product_name' => $product->name,
        'description' => 'M9 completion line',
        'quantity' => $quantity,
        'price_basis' => PriceBasis::Net,
        'unit_price' => '100.000000',
        'line_discount_rate' => '0.000000',
        'tax_id' => $tax->getKey(),
        'tax_code' => $tax->code,
        'tax_rate' => '20.000000',
        'tax_is_zeroed' => false,
        'tax_zero_reason_id' => null,
        'tax_zero_reason_code' => null,
        'base_net' => (string) $totals->base,
        'line_discount_net' => '0.000000',
        'document_discount_net' => '0.000000',
        'net_total' => (string) $totals->base,
        'tax_total' => (string) $totals->tax,
        'gross_total' => (string) $totals->gross,
    ]);

    return $order->load('lines.progress');
}
