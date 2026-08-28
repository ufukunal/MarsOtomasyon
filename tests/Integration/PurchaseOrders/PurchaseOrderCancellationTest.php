<?php

use App\Foundation\Identity\SourceEffectIdentity;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Authorization\AssignRoleToMembership;
use App\Modules\Core\Authorization\GrantPermissionToRole;
use App\Modules\Core\Enums\PermissionKey;
use App\Modules\Core\Enums\UserStatus;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\Tax;
use App\Modules\Core\Models\User;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use App\Modules\PurchaseOrders\Actions\PurchaseOrderLifecycle;
use App\Modules\PurchaseOrders\Enums\PurchaseOrderProgressType;
use App\Modules\PurchaseOrders\Enums\PurchaseOrderStatus;
use App\Modules\PurchaseOrders\Models\PurchaseOrder;
use App\Modules\PurchaseOrders\Progress\PurchaseOrderProgressService;
use App\Modules\Quotes\Pricing\PriceBasis;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('lets an authorized user cancel open purchase quantity exactly once and reduces both remaining capacities', function (): void {
    [$company, $order, $manager] = purchaseOrderCancellationFixture('PO-CANCEL-A');
    $line = $order->lines()->firstOrFail();
    $progress = app(PurchaseOrderProgressService::class);

    DB::transaction(function () use ($company, $line, $progress): void {
        $progress->record(
            new SourceEffectIdentity((int) $company->getKey(), 'test_receipt', 'receipt-1', 'progress.receive'),
            (int) $line->getKey(),
            PurchaseOrderProgressType::Received,
            '3',
        );
        $progress->record(
            new SourceEffectIdentity((int) $company->getKey(), 'test_invoice', 'invoice-1', 'progress.invoice'),
            (int) $line->getKey(),
            PurchaseOrderProgressType::Invoiced,
            '2',
        );
    });

    $operationId = (string) Str::uuid();
    $url = route('purchase-orders.lines.cancel', [$order->getKey(), $line->getKey()]);

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post($url, ['quantity' => '4', 'operation_id' => $operationId])
        ->assertRedirect(route('purchase-orders.show', $order->getKey()));

    $projection = DB::table('purchase_order_line_progress')
        ->where('purchase_order_line_id', $line->getKey())
        ->first();

    expect($projection)->not->toBeNull()
        ->and((string) $projection->cancelled_quantity)->toBe('4.000000')
        ->and((string) $projection->receive_remaining_quantity)->toBe('3.000000')
        ->and((string) $projection->invoice_remaining_quantity)->toBe('4.000000')
        ->and(DB::table('purchase_order_line_progress_effects')
            ->where('source_type', 'purchase_order_line_cancellation')
            ->where('source_id', $operationId)
            ->count())->toBe(1);

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->post($url, ['quantity' => '4.000000', 'operation_id' => $operationId])
        ->assertRedirect();

    expect(DB::table('purchase_order_line_progress_effects')
        ->where('source_type', 'purchase_order_line_cancellation')
        ->where('source_id', $operationId)
        ->count())->toBe(1);
});

it('blocks cancellation beyond the stricter received or invoiced remaining capacity', function (): void {
    [$company, $order, $manager] = purchaseOrderCancellationFixture('PO-CANCEL-B');
    $line = $order->lines()->firstOrFail();
    $progress = app(PurchaseOrderProgressService::class);

    DB::transaction(function () use ($company, $line, $progress): void {
        $progress->record(
            new SourceEffectIdentity((int) $company->getKey(), 'test_receipt', 'receipt-2', 'progress.receive'),
            (int) $line->getKey(),
            PurchaseOrderProgressType::Received,
            '7',
        );
        $progress->record(
            new SourceEffectIdentity((int) $company->getKey(), 'test_invoice', 'invoice-2', 'progress.invoice'),
            (int) $line->getKey(),
            PurchaseOrderProgressType::Invoiced,
            '5',
        );
    });

    $this->actingAs($manager)
        ->withSession(['active_company_id' => $company->getKey()])
        ->from(route('purchase-orders.show', $order->getKey()))
        ->post(route('purchase-orders.lines.cancel', [$order->getKey(), $line->getKey()]), [
            'quantity' => '4',
            'operation_id' => (string) Str::uuid(),
        ])
        ->assertRedirect(route('purchase-orders.show', $order->getKey()))
        ->assertSessionHasErrors('quantity_delta');

    expect(DB::table('purchase_order_line_progress_effects')
        ->where('source_type', 'purchase_order_line_cancellation')
        ->count())->toBe(0);
});

/** @return array{Company,PurchaseOrder,User} */
function purchaseOrderCancellationFixture(string $code): array
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
        'base_net_total' => '1000.000000',
        'line_discount_total' => '0.000000',
        'document_discount_total' => '0.000000',
        'net_total' => '1000.000000',
        'tax_total' => '200.000000',
        'gross_total' => '1200.000000',
        'note' => null,
    ]);
    $order->lines()->create([
        'company_id' => $company->getKey(),
        'logical_line_key' => (string) Str::uuid(),
        'position' => 1,
        'product_id' => $product->getKey(),
        'warehouse_id' => null,
        'location_id' => null,
        'product_code' => $product->code,
        'product_name' => $product->name,
        'description' => 'İptal testi',
        'quantity' => '10.000000',
        'price_basis' => PriceBasis::Net,
        'unit_price' => '100.000000',
        'line_discount_rate' => '0.000000',
        'tax_id' => $tax->getKey(),
        'tax_code' => $tax->code,
        'tax_rate' => '20.000000',
        'tax_is_zeroed' => false,
        'tax_zero_reason_id' => null,
        'tax_zero_reason_code' => null,
        'base_net' => '1000.000000',
        'line_discount_net' => '0.000000',
        'document_discount_net' => '0.000000',
        'net_total' => '1000.000000',
        'tax_total' => '200.000000',
        'gross_total' => '1200.000000',
    ]);

    $user = User::query()->create([
        'name' => 'Purchase Manager',
        'email' => strtolower($code).'@purchase-cancel.test',
        'password' => 'correct-password',
        'status' => UserStatus::Active,
    ]);
    $membership = CompanyMembership::query()->create([
        'company_id' => $company->getKey(),
        'user_id' => $user->getKey(),
        'is_active' => true,
        'joined_at' => now(),
    ]);
    $role = Role::query()->create([
        'company_id' => $company->getKey(),
        'code' => 'purchase-manager',
        'name' => 'Purchase Manager',
        'is_active' => true,
    ]);
    app(GrantPermissionToRole::class)->handle($role, PermissionKey::PurchaseOrderView);
    app(GrantPermissionToRole::class)->handle($role, PermissionKey::PurchaseOrderManage);
    app(AssignRoleToMembership::class)->handle($membership, $role);

    app(PurchaseOrderLifecycle::class)->open(
        (int) $company->getKey(),
        (int) $order->getKey(),
        (int) $user->getKey(),
    );

    return [$company, $order->refresh()->load('lines.progress'), $user];
}
