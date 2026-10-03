<?php

use App\Actions\Products\SaveProduct;
use App\Actions\Products\SaveSetComponent;
use App\Actions\Products\SaveVariantValues;
use App\Livewire\Pages\Products\ProductList;
use App\Models\Period\Product;
use App\Models\Period\Unit;
use App\Models\Period\VariantAttribute;
use App\Models\Period\VariantGroup;
use App\Support\Products\SetAvailabilityCalculator;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('KDV dahil girilen ürün fiyatını KDV hariç saklar ve barkodla arar', function () {
    [$company, $period] = $this->createCompanyWithPeriod('PROD');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $unit = Unit::query()->where('code', 'ADET')->firstOrFail();

    $product = app(SaveProduct::class)->handle([
        'code' => 'VAT-1',
        'name' => 'KDV Ürünü',
        'unit_id' => $unit->id,
        'barcode' => '869000000001',
        'vat_rate' => '20',
        'list_price' => '120',
        'price_vat_included' => true,
        'currency' => 'TRY',
        'kind' => 'normal',
        'allow_negative_stock' => false,
        'min_stock' => '0',
        'channel_stock_mode' => 'stock',
        'is_active' => true,
    ]);

    expect($product->list_price)->toBe('100.0000')
        ->and(Product::query()->search('869000000001')->pluck('id')->all())
        ->toContain($product->id);
});

it('cost.view olmayan kullanıcı ürün listesinde maliyet kolonu görmez', function () {
    [$company, $period] = $this->createCompanyWithPeriod('PRODLIST');
    $sales = $this->createUserWithPeriodAccess($company, $period, 'Satış');
    $this->loginToPeriod($sales, $company, $period);

    Livewire::test(ProductList::class)
        ->assertDontSee('Maliyet');
});

it('ürünü ikinci varyant grubuna taşırken eski grup değerlerini temizler', function () {
    [$company, $period] = $this->createCompanyWithPeriod('VARIANT');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $groupA = VariantGroup::query()->create(['name' => 'Grup A', 'is_active' => true]);
    $groupB = VariantGroup::query()->create(['name' => 'Grup B', 'is_active' => true]);
    $attrA = VariantAttribute::query()->create(['variant_group_id' => $groupA->id, 'name' => 'Renk']);
    $attrB = VariantAttribute::query()->create(['variant_group_id' => $groupB->id, 'name' => 'Boy']);

    $product = $this->createTestProduct();

    app(SaveVariantValues::class)->handle($product, $groupA->id, [$attrA->id => 'Gold']);
    app(SaveVariantValues::class)->handle($product->refresh(), $groupB->id, [$attrB->id => '80']);

    $product->refresh();

    expect($product->variant_group_id)->toBe($groupB->id)
        ->and($product->variantValues()->count())->toBe(1)
        ->and($product->variantValues()->first()->variant_attribute_id)->toBe($attrB->id);
});

it('aynı varyant kombinasyonunda uyarı üretir', function () {
    [$company, $period] = $this->createCompanyWithPeriod('VARDUP');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $group = VariantGroup::query()->create(['name' => 'Renk', 'is_active' => true]);
    $attr = VariantAttribute::query()->create(['variant_group_id' => $group->id, 'name' => 'Renk']);

    $one = $this->createTestProduct(['code' => 'V-1']);
    $two = $this->createTestProduct(['code' => 'V-2']);

    app(SaveVariantValues::class)->handle($one, $group->id, [$attr->id => 'Gold']);
    $warnings = app(SaveVariantValues::class)->handle($two, $group->id, [$attr->id => 'Gold']);

    expect($warnings)->not->toBeEmpty()
        ->and($warnings[0])->toContain('V-1');
});

it('set satılabilir adedini minimum bileşen oranından hesaplar ve sıfıra düşürür', function () {
    [$company, $period] = $this->createCompanyWithPeriod('SET');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $set = $this->createTestProduct(['code' => 'SET-1', 'kind' => 'set']);
    $a = $this->createTestProduct(['code' => 'CMP-A']);
    $b = $this->createTestProduct(['code' => 'CMP-B']);

    app(SaveSetComponent::class)->handle($set, $a, '2');
    app(SaveSetComponent::class)->handle($set, $b, '3');

    $this->setAvailableStock($a, '10.000');
    $this->setAvailableStock($b, '7.000');

    expect(app(SetAvailabilityCalculator::class)->forProduct($set))->toBe('2');

    $this->setAvailableStock($b, '0.000');

    expect(app(SetAvailabilityCalculator::class)->forProduct($set))->toBe('0');
});

it('set ürünün başka setin bileşeni olmasını reddeder', function () {
    [$company, $period] = $this->createCompanyWithPeriod('NESTSET');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $outer = $this->createTestProduct(['code' => 'SET-O', 'kind' => 'set']);
    $inner = $this->createTestProduct(['code' => 'SET-I', 'kind' => 'set']);

    expect(fn () => app(SaveSetComponent::class)->handle($outer, $inner, '1'))
        ->toThrow(ValidationException::class);
});
