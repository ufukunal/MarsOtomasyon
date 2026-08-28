<?php

use App\Foundation\Clock\Clock;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountTransaction;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\DocumentType;
use App\Modules\Core\Enums\PostingPeriodStatus;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\DocumentSequence;
use App\Modules\Core\Models\PostingPeriod;
use App\Modules\Core\Models\Tax;
use App\Modules\GoodsReceipts\Actions\FinalizeGoodsReceipt;
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
use App\Modules\PurchaseReturns\Actions\CreatePurchaseReturn;
use App\Modules\PurchaseReturns\Actions\FinalizePurchaseReturn;
use App\Modules\PurchaseReturns\Actions\PurchaseReturnDraftData;
use App\Modules\PurchaseReturns\Actions\PurchaseReturnLineData;
use App\Modules\PurchaseReturns\Enums\PurchaseReturnStatus;
use App\Modules\Quotes\Pricing\PriceBasis;
use App\Modules\SupplierInvoices\Actions\FinalizeSupplierInvoice;
use App\Modules\SupplierInvoices\Enums\SupplierInvoiceStatus;
use App\Modules\SupplierInvoices\Models\SupplierInvoice;
use Illuminate\Database\QueryException;
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

it('finalizes a partial purchase return into exactly one stock out and positive supplier account correction', function (): void {
    [$company, $order, $receipt, $invoice] = purchaseReturn95Fixture('PRET95-A');
    $receiptLine = $receipt->lines()->firstOrFail();
    $invoiceLine = $invoice->lines()->firstOrFail();

    $return = app(CreatePurchaseReturn::class)->handle(new PurchaseReturnDraftData(
        purchaseOrderId: (int) $order->getKey(),
        returnDate: '2026-08-27',
        note: 'Kısmi iade',
        lines: [new PurchaseReturnLineData((int) $receiptLine->getKey(), (int) $invoiceLine->getKey(), '2')],
    ));
    $returnLine = $return->lines()->firstOrFail();

    expect($return->statusEnum())->toBe(PurchaseReturnStatus::Draft)
        ->and((string) $return->gross_total)->toBe('240.000000');

    $finalized = app(FinalizePurchaseReturn::class)->handle((int) $return->getKey());
    $accountEffect = AccountTransaction::query()
        ->where('company_id', $company->getKey())
        ->where('source_type', 'purchase_return')
        ->where('source_id', (string) $return->getKey())
        ->where('effect_type', 'account.purchase_return')
        ->firstOrFail();
    $movement = DB::table('stock_movements')
        ->where('company_id', $company->getKey())
        ->where('source_type', 'purchase_return_line')
        ->where('source_id', (string) $returnLine->getKey())
        ->where('effect_type', 'stock.out')
        ->first();
    $balance = DB::table('stock_balances')
        ->where('company_id', $company->getKey())
        ->where('product_id', $returnLine->product_id)
        ->where('warehouse_id', $returnLine->warehouse_id)
        ->where('location_id', $returnLine->location_id)
        ->first();

    expect($finalized->statusEnum())->toBe(PurchaseReturnStatus::Finalized)
        ->and((string) $accountEffect->signed_amount)->toBe('240.000000')
        ->and($movement)->not->toBeNull()
        ->and((string) $movement->movement_type)->toBe('purchase_return_out')
        ->and((string) $movement->quantity_delta)->toBe('-2.000000')
        ->and((string) $movement->value_delta)->toBe('-200.000000')
        ->and((string) $balance->quantity)->toBe('3.000000')
        ->and((string) $balance->inventory_value)->toBe('300.000000')
        ->and(DB::table('purchase_order_line_progress_effects')->where('source_type', 'purchase_return_line')->count())->toBe(2);

    app(FinalizePurchaseReturn::class)->handle((int) $return->getKey());
    expect(AccountTransaction::query()->where('source_type', 'purchase_return')->count())->toBe(1)
        ->and(DB::table('stock_movements')->where('source_type', 'purchase_return_line')->count())->toBe(1)
        ->and(DB::table('purchase_order_line_progress_effects')->where('source_type', 'purchase_return_line')->count())->toBe(2);
});

it('rechecks physical and financial return eligibility atomically at finalization', function (): void {
    [$company, $order, $receipt, $invoice] = purchaseReturn95Fixture('PRET95-B');
    $receiptLine = $receipt->lines()->firstOrFail();
    $invoiceLine = $invoice->lines()->firstOrFail();
    $draftData = fn (): PurchaseReturnDraftData => new PurchaseReturnDraftData(
        purchaseOrderId: (int) $order->getKey(),
        returnDate: '2026-08-27',
        note: null,
        lines: [new PurchaseReturnLineData((int) $receiptLine->getKey(), (int) $invoiceLine->getKey(), '4')],
    );

    $first = app(CreatePurchaseReturn::class)->handle($draftData());
    $second = app(CreatePurchaseReturn::class)->handle($draftData());
    app(FinalizePurchaseReturn::class)->handle((int) $first->getKey());

    expect(fn () => app(FinalizePurchaseReturn::class)->handle((int) $second->getKey()))
        ->toThrow(ValidationException::class);

    expect($second->refresh()->statusEnum())->toBe(PurchaseReturnStatus::Draft)
        ->and(AccountTransaction::query()->where('source_type', 'purchase_return')->count())->toBe(1)
        ->and(DB::table('stock_movements')->where('source_type', 'purchase_return_line')->count())->toBe(1);
});

it('rejects raw purchase return finalization without exact supplier and stock effects', function (): void {
    [, $order, $receipt, $invoice] = purchaseReturn95Fixture('PRET95-C');
    $return = app(CreatePurchaseReturn::class)->handle(new PurchaseReturnDraftData(
        purchaseOrderId: (int) $order->getKey(),
        returnDate: '2026-08-27',
        note: null,
        lines: [new PurchaseReturnLineData(
            (int) $receipt->lines()->firstOrFail()->getKey(),
            (int) $invoice->lines()->firstOrFail()->getKey(),
            '1',
        )],
    ));

    expect(fn () => DB::table('purchase_returns')->where('id', $return->getKey())->update([
        'status' => 'finalized',
        'finalized_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    expect($return->refresh()->statusEnum())->toBe(PurchaseReturnStatus::Draft)
        ->and(AccountTransaction::query()->where('source_type', 'purchase_return')->count())->toBe(0)
        ->and(DB::table('stock_movements')->where('source_type', 'purchase_return_line')->count())->toBe(0);
});

it('freezes finalized purchase return header and lineage lines at the database boundary', function (): void {
    [, $order, $receipt, $invoice] = purchaseReturn95Fixture('PRET95-D');
    $return = app(CreatePurchaseReturn::class)->handle(new PurchaseReturnDraftData(
        purchaseOrderId: (int) $order->getKey(),
        returnDate: '2026-08-27',
        note: null,
        lines: [new PurchaseReturnLineData(
            (int) $receipt->lines()->firstOrFail()->getKey(),
            (int) $invoice->lines()->firstOrFail()->getKey(),
            '1',
        )],
    ));
    $line = $return->lines()->firstOrFail();
    app(FinalizePurchaseReturn::class)->handle((int) $return->getKey());

    expect(fn () => DB::table('purchase_returns')->where('id', $return->getKey())->update(['note' => 'raw tamper']))
        ->toThrow(QueryException::class);
    expect(fn () => DB::table('purchase_return_lines')->where('id', $line->getKey())->update(['quantity' => '0.500000']))
        ->toThrow(QueryException::class);
});

it('reverses the source supplier invoice discount snapshot instead of later purchase order terms', function (): void {
    [, $order, $receipt, $invoice] = purchaseReturn95Fixture('PRET95-E', true);

    $receiptLine = $receipt->lines()->firstOrFail();
    $invoiceLine = $invoice->lines()->firstOrFail();
    $return = app(CreatePurchaseReturn::class)->handle(new PurchaseReturnDraftData(
        purchaseOrderId: (int) $order->getKey(),
        returnDate: '2026-08-27',
        note: 'Snapshot provenance regression',
        lines: [new PurchaseReturnLineData((int) $receiptLine->getKey(), (int) $invoiceLine->getKey(), '2')],
    ));

    expect((string) $order->document_discount_rate)->toBe('10.000000')
        ->and((string) $invoice->document_discount_rate)->toBe('0.000000')
        ->and((string) $return->document_discount_rate)->toBe('0.000000')
        ->and((string) $return->document_discount_total)->toBe('0.000000')
        ->and((string) $return->net_total)->toBe('200.000000')
        ->and((string) $return->tax_total)->toBe('40.000000')
        ->and((string) $return->gross_total)->toBe('240.000000');
});

/** @return array{Company,PurchaseOrder,GoodsReceipt,SupplierInvoice} */
function purchaseReturn95Fixture(string $code, bool $changeOrderTermsBeforeFinalize = false): array
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
        'company_id' => $company->getKey(), 'warehouse_id' => $warehouse->getKey(),
        'code' => 'A1', 'name' => 'A1', 'is_active' => true,
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
        'number' => 'PO-'.$code,
        'series_code' => 'default',
        'sequence_value' => 1,
        'status' => PurchaseOrderStatus::Draft,
        'order_date' => '2026-08-27',
        'currency_code' => 'TRY',
        'document_discount_rate' => '0.000000',
        'base_net_total' => '500.000000',
        'line_discount_total' => '0.000000',
        'document_discount_total' => '0.000000',
        'net_total' => '500.000000',
        'tax_total' => '100.000000',
        'gross_total' => '600.000000',
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
        'description' => 'Satınalma satırı',
        'quantity' => '5.000000',
        'price_basis' => PriceBasis::Net,
        'unit_price' => '100.000000',
        'line_discount_rate' => '0.000000',
        'tax_id' => $tax->getKey(),
        'tax_code' => $tax->code,
        'tax_rate' => '20.000000',
        'tax_is_zeroed' => false,
        'tax_zero_reason_id' => null,
        'tax_zero_reason_code' => null,
        'base_net' => '500.000000',
        'line_discount_net' => '0.000000',
        'document_discount_net' => '0.000000',
        'net_total' => '500.000000',
        'tax_total' => '100.000000',
        'gross_total' => '600.000000',
    ]);

    $receipt = GoodsReceipt::query()->create([
        'company_id' => $company->getKey(),
        'purchase_order_id' => $order->getKey(),
        'account_id' => $supplier->getKey(),
        'number' => 'GR-'.$code,
        'series_code' => 'default',
        'sequence_value' => 1,
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
        'received_quantity' => '5.000000',
        'accepted_quantity' => '5.000000',
        'pending_quantity' => '0.000000',
        'rejected_quantity' => '0.000000',
        'provisional_unit_cost' => '100.000000',
        'note' => null,
    ]);

    $invoice = SupplierInvoice::query()->create([
        'company_id' => $company->getKey(),
        'purchase_order_id' => $order->getKey(),
        'account_id' => $supplier->getKey(),
        'number' => 'PINV-'.$code,
        'series_code' => 'default',
        'sequence_value' => 1,
        'status' => SupplierInvoiceStatus::Draft,
        'finalized_at' => null,
        'invoice_date' => '2026-08-27',
        'currency_code' => 'TRY',
        'document_discount_rate' => '0.000000',
        'base_net_total' => '500.000000',
        'line_discount_total' => '0.000000',
        'document_discount_total' => '0.000000',
        'net_total' => '500.000000',
        'tax_total' => '100.000000',
        'gross_total' => '600.000000',
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
        'description' => 'Satınalma satırı',
        'quantity' => '5.000000',
        'price_basis' => PriceBasis::Net,
        'unit_price' => '100.000000',
        'line_discount_rate' => '0.000000',
        'tax_id' => $tax->getKey(),
        'tax_code' => $tax->code,
        'tax_rate' => '20.000000',
        'tax_is_zeroed' => false,
        'tax_zero_reason_id' => null,
        'tax_zero_reason_code' => null,
        'base_net' => '500.000000',
        'line_discount_net' => '0.000000',
        'document_discount_net' => '0.000000',
        'net_total' => '500.000000',
        'tax_total' => '100.000000',
        'gross_total' => '600.000000',
    ]);

    if ($changeOrderTermsBeforeFinalize) {
        $order->forceFill([
            'document_discount_rate' => '10.000000',
            'document_discount_total' => '50.000000',
            'net_total' => '450.000000',
            'tax_total' => '90.000000',
            'gross_total' => '540.000000',
        ])->save();
        $orderLine->forceFill([
            'document_discount_net' => '50.000000',
            'net_total' => '450.000000',
            'tax_total' => '90.000000',
            'gross_total' => '540.000000',
        ])->save();
    }

    app(ActiveCompanyContext::class)->set($company);
    app(FinalizeGoodsReceipt::class)->handle((int) $receipt->getKey());
    app(FinalizeSupplierInvoice::class)->handle((int) $invoice->getKey());

    return [$company, $order->refresh(), $receipt->refresh(), $invoice->refresh()];
}
