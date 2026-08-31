<?php

use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountB2BPolicy;
use App\Modules\B2B\Enums\B2BPermission;
use App\Modules\B2B\Enums\B2BRiskBehavior;
use App\Modules\B2B\Enums\B2BRole;
use App\Modules\B2B\Enums\B2BUserStatus;
use App\Modules\B2B\Models\B2BUser;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Tax;
use App\Modules\Products\Enums\ProductStatus;
use App\Modules\Products\Models\Barcode;
use App\Modules\Products\Models\Category;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Unit;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('searches the B2B catalog by brand category and barcode while enforcing account visibility', function (): void {
    [$company, $account, $user, $product] = m19GapFixture('SEARCH');
    Barcode::query()->create([
        'company_id' => $company->getKey(),
        'product_id' => $product->getKey(),
        'barcode' => '8690000000019',
        'is_primary' => true,
    ]);
    $session = ['b2b_auth_version' => $user->auth_version];

    $this->actingAs($user, 'b2b')->withSession($session)->get('/b2b/catalog?q=MarsTech')->assertOk()->assertSee((string) $product->code);
    $this->actingAs($user, 'b2b')->withSession($session)->get('/b2b/catalog?q=Montaj')->assertOk()->assertSee((string) $product->code);
    $this->actingAs($user, 'b2b')->withSession($session)->get('/b2b/catalog?q=8690000000019')->assertOk()->assertSee((string) $product->code);

    DB::table('account_b2b_product_visibilities')->insert([
        'company_id' => $company->getKey(), 'account_id' => $account->getKey(), 'product_id' => $product->getKey(),
        'is_visible' => false, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->actingAs($user, 'b2b')->withSession($session)->get('/b2b/catalog?q=MarsTech')->assertOk()->assertDontSee((string) $product->code);
});

it('blocks hidden products both at cart add and order submit boundaries', function (): void {
    [$company, $account, $user, $product] = m19GapFixture('HIDDEN');
    DB::table('account_b2b_product_visibilities')->insert([
        'company_id' => $company->getKey(), 'account_id' => $account->getKey(), 'product_id' => $product->getKey(),
        'is_visible' => false, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $session = ['b2b_auth_version' => $user->auth_version];

    $this->actingAs($user, 'b2b')->withSession($session)
        ->post('/b2b/cart', ['product_code' => $product->code, 'quantity' => '1'])
        ->assertNotFound();

    $this->actingAs($user, 'b2b')->withSession($session + ['b2b_cart' => [(string) $product->code => '1.000000']])
        ->post('/b2b/orders', ['idempotency_key' => (string) Str::ulid()])
        ->assertSessionHasErrors('cart');
});

it('keeps one default address per account and address type from the B2B portal', function (): void {
    [, , $user] = m19GapFixture('ADDRESS');
    $session = ['b2b_auth_version' => $user->auth_version];
    $payload = [
        'type' => 'shipping', 'label' => 'Depo A', 'recipient_name' => 'Bayi', 'line1' => 'Test Cad. 1',
        'line2' => '', 'district' => 'Merkez', 'city' => 'İstanbul', 'postal_code' => '34000', 'country_code' => 'TR',
        'is_default' => '1',
    ];

    $this->actingAs($user, 'b2b')->withSession($session)->post('/b2b/addresses', $payload)->assertRedirect();
    $this->actingAs($user, 'b2b')->withSession($session)->post('/b2b/addresses', $payload + ['label' => 'Depo B'])->assertRedirect();

    $addresses = DB::table('account_addresses')->where('account_id', $user->account_id)->where('type', 'shipping')->orderBy('id')->get();
    expect($addresses)->toHaveCount(2)
        ->and((bool) $addresses[0]->is_default)->toBeFalse()
        ->and((bool) $addresses[1]->is_default)->toBeTrue();
});

/** @return array{Company, Account, B2BUser, Product} */
function m19GapFixture(string $suffix): array
{
    $company = Company::query()->create(['code' => 'M19-GAP-'.$suffix, 'name' => 'M19 Gap '.$suffix]);
    $account = Account::query()->create([
        'company_id' => $company->getKey(), 'code' => 'GAP-'.$suffix, 'type' => AccountType::Customer,
        'status' => AccountStatus::Active, 'legal_name' => 'Gap Bayi '.$suffix, 'trade_name' => null,
        'tax_identity_type' => TaxIdentityType::None, 'tax_number' => null, 'tax_office' => null,
        'book_currency_code' => 'TRY', 'due_days' => 0, 'discount_rate' => '5.000000', 'risk_limit' => '10000.000000',
    ]);
    $category = Category::query()->create(['company_id' => $company->getKey(), 'code' => 'MONTAJ', 'name' => 'Montaj Ekipmanı', 'is_active' => true]);
    $unit = Unit::query()->create(['company_id' => $company->getKey(), 'code' => 'ADET', 'name' => 'Adet', 'is_active' => true]);
    $tax = Tax::query()->create(['company_id' => $company->getKey(), 'code' => 'KDV20', 'name' => 'KDV %20', 'rate' => '20.000000', 'is_active' => true]);
    $product = Product::query()->create([
        'company_id' => $company->getKey(), 'code' => 'GAP-SKU-'.$suffix, 'status' => ProductStatus::Active,
        'name' => 'Gap Ürün '.$suffix, 'brand' => 'MarsTech', 'category_id' => $category->getKey(), 'unit_id' => $unit->getKey(),
        'tax_id' => $tax->getKey(), 'sale_price_net' => '100.000000', 'purchase_price_net' => '60.000000',
    ]);
    AccountB2BPolicy::query()->create([
        'company_id' => $company->getKey(), 'account_id' => $account->getKey(), 'is_enabled' => true,
        'allow_orders' => true, 'show_price' => true, 'show_stock' => false, 'show_balance' => true,
        'show_invoices' => true, 'show_statement' => true, 'allow_address_management' => true,
        'default_warehouse_id' => null, 'risk_behavior' => B2BRiskBehavior::Block,
    ]);
    $user = B2BUser::query()->create([
        'company_id' => $company->getKey(), 'account_id' => $account->getKey(), 'name' => 'Gap B2B '.$suffix,
        'email' => mb_strtolower($suffix).'@gap-b2b.test', 'password' => 'Correct-Password-19', 'status' => B2BUserStatus::Active,
        'role' => B2BRole::Admin, 'permissions' => B2BPermission::values(),
    ])->refresh();

    return [$company, $account, $user, $product];
}
