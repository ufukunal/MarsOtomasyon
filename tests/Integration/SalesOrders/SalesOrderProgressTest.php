<?php

use App\Foundation\Idempotency\IdempotencyConflict;
use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Tax;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\Quotes\Pricing\PriceBasis;
use App\Modules\SalesOrders\Enums\SalesOrderProgressType;
use App\Modules\SalesOrders\Enums\SalesOrderStatus;
use App\Modules\SalesOrders\Models\SalesOrder;
use App\Modules\SalesOrders\Models\SalesOrderLine;
use App\Modules\SalesOrders\Models\SalesOrderLineProgressEffect;
use App\Modules\SalesOrders\Progress\SalesOrderProgressService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

uses(DatabaseMigrations::class);

it('derives ordered dispatched invoiced cancelled and remaining quantities from immutable effects', function (): void {
    [$company, $line] = m64Fixture('M64-PROJECTION', '10');
    $service = app(SalesOrderProgressService::class);

    DB::transaction(fn (): SalesOrderLineProgressEffect => $service->record(
        m64Identity($company, 'dispatch-1', 'progress.dispatch'),
        (int) $line->getKey(),
        SalesOrderProgressType::Dispatched,
        '4',
    ));
    DB::transaction(fn (): SalesOrderLineProgressEffect => $service->record(
        m64Identity($company, 'invoice-1', 'progress.invoice'),
        (int) $line->getKey(),
        SalesOrderProgressType::Invoiced,
        '3',
    ));
    DB::transaction(fn (): SalesOrderLineProgressEffect => $service->record(
        m64Identity($company, 'cancel-1', 'progress.cancel'),
        (int) $line->getKey(),
        SalesOrderProgressType::Cancelled,
        '2',
    ));

    $progress = $line->fresh()->progress()->firstOrFail();
    expect((string) $progress->ordered_quantity)->toBe('10.000000')
        ->and((string) $progress->net_dispatched_quantity)->toBe('4.000000')
        ->and((string) $progress->net_invoiced_quantity)->toBe('3.000000')
        ->and((string) $progress->cancelled_quantity)->toBe('2.000000')
        ->and((string) $progress->dispatch_remaining_quantity)->toBe('4.000000')
        ->and((string) $progress->invoice_remaining_quantity)->toBe('5.000000')
        ->and((string) $progress->remaining_quantity)->toBe('4.000000')
        ->and(SalesOrderLineProgressEffect::query()->count())->toBe(3);

    expect(fn () => DB::table('sales_order_lines')->where('id', $line->getKey())->update(['quantity' => '11.000000']))
        ->toThrow(QueryException::class);
    expect(fn () => DB::table('sales_order_lines')->where('id', $line->getKey())->delete())
        ->toThrow(QueryException::class);

    $firstEffect = SalesOrderLineProgressEffect::query()->firstOrFail();
    expect(fn () => DB::table('sales_order_line_progress_effects')->where('id', $firstEffect->getKey())->update(['quantity_delta' => '1.000000']))
        ->toThrow(QueryException::class);
    expect(fn () => DB::table('sales_order_line_progress_effects')->where('id', $firstEffect->getKey())->delete())
        ->toThrow(QueryException::class);
});

it('is replay safe rejects payload drift and requires the owning business transaction', function (): void {
    [$company, $line] = m64Fixture('M64-IDEMPOTENCY', '5');
    $service = app(SalesOrderProgressService::class);
    $identity = m64Identity($company, 'dispatch-replay', 'progress.dispatch');

    expect(fn () => $service->record(
        $identity,
        (int) $line->getKey(),
        SalesOrderProgressType::Dispatched,
        '2',
    ))->toThrow(LogicException::class);

    $first = DB::transaction(fn (): SalesOrderLineProgressEffect => $service->record(
        $identity,
        (int) $line->getKey(),
        SalesOrderProgressType::Dispatched,
        '2',
    ));
    $replay = DB::transaction(fn (): SalesOrderLineProgressEffect => $service->record(
        $identity,
        (int) $line->getKey(),
        SalesOrderProgressType::Dispatched,
        '2.000000',
    ));

    expect($replay->getKey())->toBe($first->getKey())
        ->and(SalesOrderLineProgressEffect::query()->count())->toBe(1);

    expect(fn () => DB::transaction(fn (): SalesOrderLineProgressEffect => $service->record(
        $identity,
        (int) $line->getKey(),
        SalesOrderProgressType::Dispatched,
        '3',
    )))->toThrow(IdempotencyConflict::class);
});

it('blocks over operation and requires explicit reversal lineage', function (): void {
    [$company, $line] = m64Fixture('M64-BOUNDS', '5');
    $service = app(SalesOrderProgressService::class);

    $dispatch = DB::transaction(fn (): SalesOrderLineProgressEffect => $service->record(
        m64Identity($company, 'dispatch-4', 'progress.dispatch'),
        (int) $line->getKey(),
        SalesOrderProgressType::Dispatched,
        '4',
    ));
    DB::transaction(fn (): SalesOrderLineProgressEffect => $service->record(
        m64Identity($company, 'cancel-1', 'progress.cancel'),
        (int) $line->getKey(),
        SalesOrderProgressType::Cancelled,
        '1',
    ));

    expect(fn () => DB::transaction(fn (): SalesOrderLineProgressEffect => $service->record(
        m64Identity($company, 'cancel-over', 'progress.cancel'),
        (int) $line->getKey(),
        SalesOrderProgressType::Cancelled,
        '1',
    )))->toThrow(ValidationException::class);

    expect(fn () => DB::transaction(fn (): SalesOrderLineProgressEffect => $service->record(
        m64Identity($company, 'invoice-over', 'progress.invoice'),
        (int) $line->getKey(),
        SalesOrderProgressType::Invoiced,
        '5',
    )))->toThrow(ValidationException::class);

    expect(fn () => DB::transaction(fn (): SalesOrderLineProgressEffect => $service->record(
        m64Identity($company, 'dispatch-negative', 'progress.dispatch_reversal'),
        (int) $line->getKey(),
        SalesOrderProgressType::Dispatched,
        '-1',
    )))->toThrow(ValidationException::class);

    $reversal = DB::transaction(fn (): SalesOrderLineProgressEffect => $service->reverse(
        m64Identity($company, 'dispatch-reversal', 'progress.dispatch_reversal'),
        (int) $dispatch->getKey(),
    ));

    $progress = $line->fresh()->progress()->firstOrFail();
    expect((string) $reversal->quantity_delta)->toBe('-4.000000')
        ->and($reversal->reversal_of_progress_effect_id)->toBe((int) $dispatch->getKey())
        ->and((string) $progress->net_dispatched_quantity)->toBe('0.000000')
        ->and((string) $progress->cancelled_quantity)->toBe('1.000000')
        ->and((string) $progress->remaining_quantity)->toBe('4.000000');

    expect(fn () => DB::table('sales_order_line_progress_effects')->insert(m64RawEffect(
        $company,
        $line,
        'dispatched',
        '5.000000',
        'raw-over',
    )))->toThrow(QueryException::class);
});

it('serializes competing line progress and rejects the waiter after the lock clears', function (): void {
    [$company, $line] = m64Fixture('M64-CONCURRENT', '5');

    config(['database.connections.pgsql_m64_concurrent' => config('database.connections.pgsql')]);
    DB::purge('pgsql_m64_concurrent');
    $concurrent = DB::connection('pgsql_m64_concurrent');
    $concurrent->statement("SET lock_timeout TO '150ms'");

    DB::beginTransaction();

    try {
        DB::table('sales_order_line_progress_effects')->insert(m64RawEffect(
            $company,
            $line,
            'dispatched',
            '4.000000',
            'lock-holder',
        ));

        expect(fn () => $concurrent->table('sales_order_line_progress_effects')->insert(m64RawEffect(
            $company,
            $line,
            'dispatched',
            '2.000000',
            'lock-waiter',
        )))->toThrow(QueryException::class);

        DB::commit();
    } finally {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }

    $concurrent->statement("SET lock_timeout TO '0'");
    expect(fn () => $concurrent->table('sales_order_line_progress_effects')->insert(m64RawEffect(
        $company,
        $line,
        'dispatched',
        '2.000000',
        'lock-waiter',
    )))->toThrow(QueryException::class);

    $progress = $line->fresh()->progress()->firstOrFail();
    expect((string) $progress->net_dispatched_quantity)->toBe('4.000000')
        ->and((string) $progress->dispatch_remaining_quantity)->toBe('1.000000')
        ->and(SalesOrderLineProgressEffect::query()->count())->toBe(1);

    DB::disconnect('pgsql_m64_concurrent');
});

it('supports partial progress cancellation and reversal-safe reopen', function (): void {
    [$company, $line] = m64Fixture('M65-PARTIAL', '10');
    $service = app(SalesOrderProgressService::class);

    DB::transaction(fn (): SalesOrderLineProgressEffect => $service->record(
        m64Identity($company, 'dispatch-a', 'progress.dispatch'),
        (int) $line->getKey(),
        SalesOrderProgressType::Dispatched,
        '2',
    ));
    DB::transaction(fn (): SalesOrderLineProgressEffect => $service->record(
        m64Identity($company, 'dispatch-b', 'progress.dispatch'),
        (int) $line->getKey(),
        SalesOrderProgressType::Dispatched,
        '3',
    ));
    DB::transaction(fn (): SalesOrderLineProgressEffect => $service->record(
        m64Identity($company, 'invoice-a', 'progress.invoice'),
        (int) $line->getKey(),
        SalesOrderProgressType::Invoiced,
        '4',
    ));
    $cancel = DB::transaction(fn (): SalesOrderLineProgressEffect => $service->record(
        m64Identity($company, 'cancel-a', 'progress.cancel'),
        (int) $line->getKey(),
        SalesOrderProgressType::Cancelled,
        '2',
    ));

    $before = $line->fresh()->progress()->firstOrFail();
    expect((string) $before->net_dispatched_quantity)->toBe('5.000000')
        ->and((string) $before->net_invoiced_quantity)->toBe('4.000000')
        ->and((string) $before->cancelled_quantity)->toBe('2.000000')
        ->and((string) $before->remaining_quantity)->toBe('3.000000');

    DB::transaction(fn (): SalesOrderLineProgressEffect => $service->reverse(
        m64Identity($company, 'cancel-reopen', 'progress.cancel_reversal'),
        (int) $cancel->getKey(),
    ));

    $reopened = $line->fresh()->progress()->firstOrFail();
    expect((string) $reopened->cancelled_quantity)->toBe('0.000000')
        ->and((string) $reopened->dispatch_remaining_quantity)->toBe('5.000000')
        ->and((string) $reopened->invoice_remaining_quantity)->toBe('6.000000')
        ->and((string) $reopened->remaining_quantity)->toBe('5.000000');

    DB::transaction(fn (): SalesOrderLineProgressEffect => $service->record(
        m64Identity($company, 'cancel-b', 'progress.cancel'),
        (int) $line->getKey(),
        SalesOrderProgressType::Cancelled,
        '1',
    ));

    expect((string) $line->fresh()->progress()->firstOrFail()->remaining_quantity)->toBe('4.000000');
});

it('enforces exact one-time reversal lineage with replay and payload-drift protection', function (): void {
    [$company, $line] = m64Fixture('M65-REVERSAL', '5');
    $service = app(SalesOrderProgressService::class);

    $first = DB::transaction(fn (): SalesOrderLineProgressEffect => $service->record(
        m64Identity($company, 'dispatch-first', 'progress.dispatch'),
        (int) $line->getKey(),
        SalesOrderProgressType::Dispatched,
        '2',
    ));
    $second = DB::transaction(fn (): SalesOrderLineProgressEffect => $service->record(
        m64Identity($company, 'dispatch-second', 'progress.dispatch'),
        (int) $line->getKey(),
        SalesOrderProgressType::Dispatched,
        '1',
    ));
    $identity = m64Identity($company, 'reverse-first', 'progress.dispatch_reversal');

    expect(fn () => DB::table('sales_order_line_progress_effects')->insert(m64RawEffect(
        $company,
        $line,
        'dispatched',
        '-1.000000',
        'raw-partial-reversal',
        (int) $first->getKey(),
    )))->toThrow(QueryException::class);

    expect(fn () => DB::table('sales_order_line_progress_effects')->insert(m64RawEffect(
        $company,
        $line,
        'dispatched',
        '-2.000000',
        'raw-lineage-free-reversal',
    )))->toThrow(QueryException::class);

    $reversal = DB::transaction(fn (): SalesOrderLineProgressEffect => $service->reverse(
        $identity,
        (int) $first->getKey(),
    ));
    $replay = DB::transaction(fn (): SalesOrderLineProgressEffect => $service->reverse(
        $identity,
        (int) $first->getKey(),
    ));

    expect($replay->getKey())->toBe($reversal->getKey())
        ->and((string) $reversal->quantity_delta)->toBe('-2.000000')
        ->and($reversal->progress_type)->toBe(SalesOrderProgressType::Dispatched)
        ->and($reversal->reversal_of_progress_effect_id)->toBe((int) $first->getKey());

    expect(fn () => DB::transaction(fn (): SalesOrderLineProgressEffect => $service->reverse(
        $identity,
        (int) $second->getKey(),
    )))->toThrow(IdempotencyConflict::class);

    expect(fn () => DB::transaction(fn (): SalesOrderLineProgressEffect => $service->reverse(
        m64Identity($company, 'reverse-first-again', 'progress.dispatch_reversal'),
        (int) $first->getKey(),
    )))->toThrow(ValidationException::class);

    expect(fn () => DB::table('sales_order_line_progress_effects')->insert(m64RawEffect(
        $company,
        $line,
        'dispatched',
        '-2.000000',
        'raw-reverse-reversal',
        (int) $reversal->getKey(),
    )))->toThrow(QueryException::class);
});

it('serializes competing reversals and allows only one winner', function (): void {
    [$company, $line] = m64Fixture('M65-CONCURRENT-REVERSAL', '5');
    $service = app(SalesOrderProgressService::class);
    $original = DB::transaction(fn (): SalesOrderLineProgressEffect => $service->record(
        m64Identity($company, 'dispatch-original', 'progress.dispatch'),
        (int) $line->getKey(),
        SalesOrderProgressType::Dispatched,
        '2',
    ));

    config(['database.connections.pgsql_m65_reversal' => config('database.connections.pgsql')]);
    DB::purge('pgsql_m65_reversal');
    $concurrent = DB::connection('pgsql_m65_reversal');
    $concurrent->statement("SET lock_timeout TO '150ms'");

    DB::beginTransaction();

    try {
        DB::table('sales_order_line_progress_effects')->insert(m64RawEffect(
            $company,
            $line,
            'dispatched',
            '-2.000000',
            'reversal-lock-holder',
            (int) $original->getKey(),
        ));

        expect(fn () => $concurrent->table('sales_order_line_progress_effects')->insert(m64RawEffect(
            $company,
            $line,
            'dispatched',
            '-2.000000',
            'reversal-lock-waiter',
            (int) $original->getKey(),
        )))->toThrow(QueryException::class);

        DB::commit();
    } finally {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }

    $concurrent->statement("SET lock_timeout TO '0'");
    expect(fn () => $concurrent->table('sales_order_line_progress_effects')->insert(m64RawEffect(
        $company,
        $line,
        'dispatched',
        '-2.000000',
        'reversal-lock-waiter',
        (int) $original->getKey(),
    )))->toThrow(QueryException::class);

    $progress = $line->fresh()->progress()->firstOrFail();
    expect((string) $progress->net_dispatched_quantity)->toBe('0.000000')
        ->and(SalesOrderLineProgressEffect::query()->count())->toBe(2);

    DB::disconnect('pgsql_m65_reversal');
});

/** @return array{Company, SalesOrderLine} */
function m64Fixture(string $code, string $quantity): array
{
    $company = Company::query()->create(['code' => $code, 'name' => 'Company '.$code]);
    $account = Account::query()->create([
        'company_id' => $company->getKey(), 'code' => 'CUST', 'type' => AccountType::Customer,
        'status' => AccountStatus::Active, 'legal_name' => 'Müşteri '.$code, 'trade_name' => null,
        'tax_identity_type' => TaxIdentityType::None, 'tax_number' => null, 'tax_office' => null,
        'book_currency_code' => 'TRY', 'due_days' => 0, 'discount_rate' => '0.000000', 'risk_limit' => '0.000000',
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
        'company_id' => $company->getKey(), 'code' => 'SKU-'.$code, 'status' => ProductStatus::Active,
        'name' => 'Ürün '.$code, 'category_id' => $category->getKey(), 'unit_id' => $unit->getKey(), 'tax_id' => $tax->getKey(),
        'sale_price_net' => '0.000000', 'purchase_price_net' => '0.000000',
    ]);
    $order = SalesOrder::query()->create([
        'company_id' => $company->getKey(), 'account_id' => $account->getKey(), 'number' => 'SO-'.$code,
        'series_code' => 'default', 'sequence_value' => 1, 'status' => SalesOrderStatus::Draft,
        'source_quote_id' => null, 'source_quote_revision_id' => null, 'order_date' => '2026-08-26',
        'currency_code' => 'TRY', 'document_discount_rate' => '0.000000', 'base_net_total' => '0.000000',
        'line_discount_total' => '0.000000', 'document_discount_total' => '0.000000', 'net_total' => '0.000000',
        'tax_total' => '0.000000', 'gross_total' => '0.000000', 'note' => null,
    ]);
    $line = $order->lines()->create([
        'company_id' => $company->getKey(), 'source_quote_revision_line_id' => null, 'logical_line_key' => null,
        'position' => 1, 'product_id' => $product->getKey(), 'warehouse_id' => null, 'location_id' => null,
        'product_code' => (string) $product->code, 'product_name' => (string) $product->name,
        'description' => (string) $product->name, 'quantity' => $quantity, 'price_basis' => PriceBasis::Net,
        'unit_price' => '0.000000', 'line_discount_rate' => '0.000000', 'tax_id' => $tax->getKey(),
        'tax_code' => (string) $tax->code, 'tax_rate' => '20.000000', 'tax_zero_reason_id' => null,
        'tax_zero_reason_code' => null, 'base_net' => '0.000000', 'line_discount_net' => '0.000000',
        'document_discount_net' => '0.000000', 'net_total' => '0.000000', 'tax_total' => '0.000000',
        'gross_total' => '0.000000',
    ]);

    return [$company, $line];
}

function m64Identity(Company $company, string $sourceId, string $effectType): SourceEffectIdentity
{
    return new SourceEffectIdentity(
        (int) $company->getKey(),
        'sales_order.test',
        $sourceId,
        $effectType,
    );
}

/** @return array<string, mixed> */
function m64RawEffect(
    Company $company,
    SalesOrderLine $line,
    string $progressType,
    string $quantityDelta,
    string $sourceId,
    ?int $reversalOfProgressEffectId = null,
): array {
    $operationKey = hash('sha256', 'm64-operation-'.$company->getKey().'-'.$sourceId);

    return [
        'company_id' => $company->getKey(),
        'sales_order_id' => $line->sales_order_id,
        'sales_order_line_id' => $line->getKey(),
        'progress_type' => $progressType,
        'quantity_delta' => $quantityDelta,
        'reversal_of_progress_effect_id' => $reversalOfProgressEffectId,
        'operation_key' => $operationKey,
        'request_fingerprint' => hash('sha256', 'm64-request-'.$company->getKey().'-'.$sourceId),
        'source_type' => 'sales_order.test',
        'source_id' => $sourceId,
        'effect_type' => 'progress.raw',
        'occurred_at' => now(),
        'created_at' => now(),
    ];
}
