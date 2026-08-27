<?php

use App\Foundation\Clock\Clock;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\DocumentType;
use App\Modules\Core\Enums\PostingPeriodStatus;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\DocumentSequence;
use App\Modules\Core\Models\PostingPeriod;
use App\Modules\Core\Models\Tax;
use App\Modules\Core\Models\User;
use App\Modules\GoodsReceipts\Actions\ApplyGoodsReceiptCostAdjustment;
use App\Modules\GoodsReceipts\Actions\FinalizeGoodsReceipt;
use App\Modules\GoodsReceipts\Actions\ReclassifyGoodsReceiptQuality;
use App\Modules\GoodsReceipts\Enums\GoodsReceiptQualityDisposition;
use App\Modules\GoodsReceipts\Enums\GoodsReceiptStatus;
use App\Modules\GoodsReceipts\Models\GoodsReceipt;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseLocation;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\PurchaseOrders\Enums\PurchaseOrderStatus;
use App\Modules\PurchaseOrders\Models\PurchaseOrder;
use App\Modules\PurchaseOrders\Models\PurchaseOrderLine;
use App\Modules\PurchaseReturns\Actions\CreatePurchaseReturn;
use App\Modules\PurchaseReturns\Actions\FinalizePurchaseReturn;
use App\Modules\PurchaseReturns\Actions\PurchaseReturnDraftData;
use App\Modules\PurchaseReturns\Actions\PurchaseReturnLineData;
use App\Modules\Quotes\Pricing\PriceBasis;
use App\Modules\SupplierInvoices\Actions\FinalizeSupplierInvoice;
use App\Modules\SupplierInvoices\Enums\SupplierInvoiceStatus;
use App\Modules\SupplierInvoices\Models\SupplierInvoice;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    app()->instance(Clock::class, new class implements Clock
    {
        public function now(): DateTimeImmutable
        {
            return new DateTimeImmutable('2026-08-27T12:00:00+00:00');
        }
    });
});

it('keeps the complete M9 procure-to-pay chain reconciled across quality, invoice, return, replacement and landed cost', function (): void {
    [$company, $supplier, $product, $tax, $warehouse, $location, $user, $order, $orderLine] = m9ExitFixture();

    $this->actingAs($user);
    app(ActiveCompanyContext::class)->set($company);

    $receipt = m9ExitReceipt(
        $company,
        $supplier,
        $order,
        $orderLine,
        $product,
        $warehouse,
        $location,
        'GR-M9-1',
        1,
        '6',
        '4',
        '2',
    );
    app(FinalizeGoodsReceipt::class)->handle((int) $receipt->getKey());
    $receiptLine = $receipt->lines()->firstOrFail();

    $progress = m9ExitProgress($company, $orderLine);
    expect((string) $progress->net_received_quantity)->toBe('4.000000')
        ->and((string) $progress->net_invoiced_quantity)->toBe('0.000000')
        ->and((string) DB::table('stock_balances')->where('company_id', $company->getKey())->value('quantity'))->toBe('4.000000');

    app(ReclassifyGoodsReceiptQuality::class)->handle(
        (int) $receipt->getKey(),
        (int) $receiptLine->getKey(),
        GoodsReceiptQualityDisposition::Accepted,
        '2',
        'M9 exit gate pending acceptance',
    );

    $progress = m9ExitProgress($company, $orderLine);
    $quality = DB::table('goods_receipt_line_quality')
        ->where('company_id', $company->getKey())
        ->where('goods_receipt_line_id', $receiptLine->getKey())
        ->first();
    expect((string) $progress->net_received_quantity)->toBe('6.000000')
        ->and((string) $quality->accepted_quantity)->toBe('6.000000')
        ->and((string) $quality->pending_quantity)->toBe('0.000000')
        ->and((string) DB::table('stock_balances')->where('company_id', $company->getKey())->value('quantity'))->toBe('6.000000');

    $invoice = m9ExitInvoice($company, $supplier, $order, $orderLine, $product, $tax, 'PINV-M9-1', 1, '6');
    app(FinalizeSupplierInvoice::class)->handle((int) $invoice->getKey());
    $invoiceLine = $invoice->lines()->firstOrFail();

    $progress = m9ExitProgress($company, $orderLine);
    expect((string) $progress->net_invoiced_quantity)->toBe('6.000000')
        ->and((string) $progress->invoice_remaining_quantity)->toBe('0.000000')
        ->and(DB::table('stock_movements')->count())->toBe(2);

    $movementCountBeforeCost = DB::table('stock_movements')->count();
    app(ApplyGoodsReceiptCostAdjustment::class)->handle(
        (int) $receipt->getKey(),
        (int) $receiptLine->getKey(),
        'M9-EXIT-LANDED-001',
        '60',
        'M9 exit gate landed cost',
    );
    $balance = DB::table('stock_balances')->where('company_id', $company->getKey())->first();
    expect((string) $balance->quantity)->toBe('6.000000')
        ->and((string) $balance->inventory_value)->toBe('660.000000')
        ->and((string) $balance->average_unit_cost)->toBe('110.000000')
        ->and(DB::table('stock_movements')->count())->toBe($movementCountBeforeCost);

    $return = app(CreatePurchaseReturn::class)->handle(new PurchaseReturnDraftData(
        purchaseOrderId: (int) $order->getKey(),
        returnDate: '2026-08-27',
        note: 'M9 exit gate partial return',
        lines: [new PurchaseReturnLineData((int) $receiptLine->getKey(), (int) $invoiceLine->getKey(), '2')],
    ));
    $returnLine = $return->lines()->firstOrFail();
    app(FinalizePurchaseReturn::class)->handle((int) $return->getKey());

    $progress = m9ExitProgress($company, $orderLine);
    $balance = DB::table('stock_balances')->where('company_id', $company->getKey())->first();
    expect((string) $progress->net_received_quantity)->toBe('4.000000')
        ->and((string) $progress->net_invoiced_quantity)->toBe('4.000000')
        ->and((string) $progress->receive_remaining_quantity)->toBe('2.000000')
        ->and((string) $progress->invoice_remaining_quantity)->toBe('2.000000')
        ->and((string) $balance->quantity)->toBe('4.000000')
        ->and((string) $balance->inventory_value)->toBe('440.000000')
        ->and(DB::table('purchase_order_line_progress_effects')
            ->where('source_type', 'purchase_return_line')
            ->where('source_id', (string) $returnLine->getKey())
            ->count())->toBe(2);

    $replacementReceipt = m9ExitReceipt(
        $company,
        $supplier,
        $order,
        $orderLine,
        $product,
        $warehouse,
        $location,
        'GR-M9-2',
        2,
        '2',
        '2',
        '0',
    );
    app(FinalizeGoodsReceipt::class)->handle((int) $replacementReceipt->getKey());

    $progress = m9ExitProgress($company, $orderLine);
    expect((string) $progress->net_received_quantity)->toBe('6.000000')
        ->and((string) $progress->net_invoiced_quantity)->toBe('4.000000');

    $replacementInvoice = m9ExitInvoice($company, $supplier, $order, $orderLine, $product, $tax, 'PINV-M9-2', 2, '2');
    app(FinalizeSupplierInvoice::class)->handle((int) $replacementInvoice->getKey());

    $progress = m9ExitProgress($company, $orderLine);
    $balance = DB::table('stock_balances')->where('company_id', $company->getKey())->first();
    expect((string) $progress->net_received_quantity)->toBe('6.000000')
        ->and((string) $progress->net_invoiced_quantity)->toBe('6.000000')
        ->and((string) $progress->receive_remaining_quantity)->toBe('0.000000')
        ->and((string) $progress->invoice_remaining_quantity)->toBe('0.000000')
        ->and((string) $balance->quantity)->toBe('6.000000')
        ->and((string) $balance->inventory_value)->toBe('640.000000')
        ->and(DB::table('stock_movements')->count())->toBe(4)
        ->and(DB::table('goods_receipt_cost_adjustments')->count())->toBe(1);

    $overInvoice = m9ExitInvoice($company, $supplier, $order, $orderLine, $product, $tax, 'PINV-M9-3', 3, '1');
    expect(fn () => app(FinalizeSupplierInvoice::class)->handle((int) $overInvoice->getKey()))
        ->toThrow(ValidationException::class);

    $accountNet = DB::table('account_transactions')
        ->where('company_id', $company->getKey())
        ->where('account_id', $supplier->getKey())
        ->sum('signed_amount');
    expect($overInvoice->refresh()->statusEnum())->toBe(SupplierInvoiceStatus::Draft)
        ->and((string) $accountNet)->toBe('-720.000000')
        ->and(DB::table('stock_movements')->count())->toBe(4);
});

/** @return array{Company,Account,Product,Tax,Warehouse,WarehouseLocation,User,PurchaseOrder,PurchaseOrderLine} */
function m9ExitFixture(): array
{
    $company = Company::query()->create(['code' => 'M9EXIT', 'name' => 'M9 Exit Company']);
    $supplier = Account::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'SUP',
        'type' => AccountType::Supplier,
        'status' => AccountStatus::Active,
        'legal_name' => 'M9 Exit Supplier',
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
        'code' => 'SKU-M9',
        'status' => ProductStatus::Active,
        'name' => 'M9 Exit Product',
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
    $user = User::query()->create([
        'name' => 'M9 Exit User',
        'email' => 'm9-exit@example.test',
        'password' => 'correct-password',
        'status' => UserStatus::Active,
    ]);

    PostingPeriod::query()->create([
        'company_id' => $company->getKey(),
        'code' => '2026-08',
        'name' => 'Ağustos 2026',
        'starts_on' => '2026-08-01',
        'ends_on' => '2026-08-31',
        'status' => PostingPeriodStatus::Open,
        'closed_at' => null,
    ]);
    DocumentSequence::query()->create([
        'company_id' => $company->getKey(),
        'document_type' => DocumentType::PurchaseReturn,
        'series_code' => 'default',
        'prefix' => 'PRET-',
        'padding' => 4,
        'next_value' => 1,
        'is_active' => true,
    ]);

    $order = PurchaseOrder::query()->create([
        'company_id' => $company->getKey(),
        'account_id' => $supplier->getKey(),
        'number' => 'PO-M9-EXIT',
        'series_code' => 'default',
        'sequence_value' => 1,
        'status' => PurchaseOrderStatus::Draft,
        'order_date' => '2026-08-27',
        'currency_code' => 'TRY',
        'document_discount_rate' => '0.000000',
        'base_net_total' => '600.000000',
        'line_discount_total' => '0.000000',
        'document_discount_total' => '0.000000',
        'net_total' => '600.000000',
        'tax_total' => '120.000000',
        'gross_total' => '720.000000',
        'note' => null,
    ]);
    $orderLine = $order->lines()->create([
        'company_id' => $company->getKey(),
        'logical_line_key' => (string) Str::uuid(),
        'position' => 1,
        'product_id' => $product->getKey(),
        'warehouse_id' => $warehouse->getKey(),
        'location_id' => $location->getKey(),
        'product_code' => $product->code,
        'product_name' => $product->name,
        'description' => 'M9 exit line',
        'quantity' => '6.000000',
        'price_basis' => PriceBasis::Net,
        'unit_price' => '100.000000',
        'line_discount_rate' => '0.000000',
        'tax_id' => $tax->getKey(),
        'tax_code' => $tax->code,
        'tax_rate' => '20.000000',
        'tax_is_zeroed' => false,
        'tax_zero_reason_id' => null,
        'tax_zero_reason_code' => null,
        'base_net' => '600.000000',
        'line_discount_net' => '0.000000',
        'document_discount_net' => '0.000000',
        'net_total' => '600.000000',
        'tax_total' => '120.000000',
        'gross_total' => '720.000000',
    ]);

    return [$company, $supplier, $product, $tax, $warehouse, $location, $user, $order, $orderLine];
}

function m9ExitReceipt(
    Company $company,
    Account $supplier,
    PurchaseOrder $order,
    PurchaseOrderLine $orderLine,
    Product $product,
    Warehouse $warehouse,
    WarehouseLocation $location,
    string $number,
    int $sequence,
    string $received,
    string $accepted,
    string $pending,
): GoodsReceipt {
    $receipt = GoodsReceipt::query()->create([
        'company_id' => $company->getKey(),
        'purchase_order_id' => $order->getKey(),
        'account_id' => $supplier->getKey(),
        'number' => $number,
        'series_code' => 'default',
        'sequence_value' => $sequence,
        'status' => GoodsReceiptStatus::Draft,
        'receipt_date' => '2026-08-27',
        'note' => null,
        'finalized_at' => null,
    ]);
    $receipt->lines()->create([
        'company_id' => $company->getKey(),
        'purchase_order_id' => $order->getKey(),
        'purchase_order_line_id' => $orderLine->getKey(),
        'position' => 1,
        'product_id' => $product->getKey(),
        'warehouse_id' => $warehouse->getKey(),
        'location_id' => $location->getKey(),
        'product_code' => $product->code,
        'product_name' => $product->name,
        'received_quantity' => $received,
        'accepted_quantity' => $accepted,
        'pending_quantity' => $pending,
        'rejected_quantity' => '0.000000',
        'provisional_unit_cost' => '100.000000',
        'note' => null,
    ]);

    return $receipt->load('lines');
}

function m9ExitInvoice(
    Company $company,
    Account $supplier,
    PurchaseOrder $order,
    PurchaseOrderLine $orderLine,
    Product $product,
    Tax $tax,
    string $number,
    int $sequence,
    string $quantity,
): SupplierInvoice {
    $totals = DB::selectOne(
        'SELECT CAST(CAST(? AS numeric) * 100 AS numeric(20,6))::text AS base, '
        .'CAST(CAST(? AS numeric) * 20 AS numeric(20,6))::text AS tax, '
        .'CAST(CAST(? AS numeric) * 120 AS numeric(20,6))::text AS gross',
        [$quantity, $quantity, $quantity],
    );
    if ($totals === null) {
        throw new RuntimeException('M9 exit invoice totals could not be calculated.');
    }

    $invoice = SupplierInvoice::query()->create([
        'company_id' => $company->getKey(),
        'purchase_order_id' => $order->getKey(),
        'account_id' => $supplier->getKey(),
        'number' => $number,
        'series_code' => 'default',
        'sequence_value' => $sequence,
        'status' => SupplierInvoiceStatus::Draft,
        'finalized_at' => null,
        'invoice_date' => '2026-08-27',
        'currency_code' => 'TRY',
        'document_discount_rate' => '0.000000',
        'base_net_total' => (string) $totals->base,
        'line_discount_total' => '0.000000',
        'document_discount_total' => '0.000000',
        'net_total' => (string) $totals->base,
        'tax_total' => (string) $totals->tax,
        'gross_total' => (string) $totals->gross,
        'note' => null,
    ]);
    $invoice->lines()->create([
        'company_id' => $company->getKey(),
        'purchase_order_id' => $order->getKey(),
        'purchase_order_line_id' => $orderLine->getKey(),
        'position' => 1,
        'product_id' => $product->getKey(),
        'product_code' => $product->code,
        'product_name' => $product->name,
        'description' => 'M9 exit line',
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

    return $invoice->load('lines');
}

function m9ExitProgress(Company $company, PurchaseOrderLine $line): object
{
    $row = DB::table('purchase_order_line_progress')
        ->where('company_id', $company->getKey())
        ->where('purchase_order_line_id', $line->getKey())
        ->first();
    if ($row === null) {
        throw new RuntimeException('M9 exit purchase order progress row not found.');
    }

    return $row;
}
