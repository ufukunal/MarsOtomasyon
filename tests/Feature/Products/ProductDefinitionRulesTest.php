<?php

use App\Actions\Catalog\SaveBrand;
use App\Actions\Catalog\SaveProductCategory;
use App\Actions\Products\SaveConfigDefinition;
use App\Exceptions\StaleRecordException;
use App\Models\Period\ConfigDefinition;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

it('kategori ağacını üç seviyede sınırlar ve stale güncellemeyi reddeder', function () {
    [$company, $period] = $this->createCompanyWithPeriod('CATEGORY');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $action = app(SaveProductCategory::class);

    $root = $action->handle(['name' => 'Aydınlatma', 'sort_order' => 0, 'is_active' => true]);
    $levelTwo = $action->handle([
        'parent_id' => $root->id,
        'name' => 'Avize',
        'sort_order' => 0,
        'is_active' => true,
    ]);
    $levelThree = $action->handle([
        'parent_id' => $levelTwo->id,
        'name' => 'Kristal',
        'sort_order' => 0,
        'is_active' => true,
    ]);

    expect(fn () => $action->handle([
        'parent_id' => $levelThree->id,
        'name' => 'Dördüncü Seviye',
        'sort_order' => 0,
        'is_active' => true,
    ]))->toThrow(ValidationException::class);

    $version = (int) $root->version;
    $updated = $action->handle([
        'name' => 'Aydınlatma Güncel',
        'sort_order' => 0,
        'is_active' => true,
    ], $root, $version);

    expect($updated->version)->toBe($version + 1)
        ->and(fn () => $action->handle([
            'name' => 'Bayat Güncelleme',
            'sort_order' => 0,
            'is_active' => true,
        ], $root, $version))->toThrow(StaleRecordException::class);
});

it('marka düzenlemesini optimistic version ile korur', function () {
    [$company, $period] = $this->createCompanyWithPeriod('BRANDLOCK');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $action = app(SaveBrand::class);
    $brand = $action->handle(['name' => 'Mars', 'is_active' => true]);
    $version = (int) $brand->version;

    $updated = $action->handle(['name' => 'Mars Yeni', 'is_active' => true], $brand, $version);

    expect($updated->version)->toBe($version + 1)
        ->and(fn () => $action->handle(
            ['name' => 'Mars Bayat', 'is_active' => true],
            $brand,
            $version,
        ))->toThrow(StaleRecordException::class);
});

it('konfigüratörde üç grup tanımlar ve fiyat alanı taşımadığını doğrular', function () {
    [$company, $period] = $this->createCompanyWithPeriod('CONFIG');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $product = $this->createTestProduct(['code' => 'CFG-1', 'kind' => 'configurable']);
    $component = $this->createTestProduct(['code' => 'CMP-1']);
    $action = app(SaveConfigDefinition::class);

    foreach (['Gövde', 'Kristal', 'Duy'] as $index => $name) {
        $action->handle(
            $product,
            ['name' => $name, 'is_required' => true, 'sort_order' => $index],
            [[
                'label' => 'Standart',
                'component_product_id' => $component->id,
                'is_default' => true,
            ]],
        );
    }

    expect(ConfigDefinition::query()->where('product_id', $product->id)->count())->toBe(3)
        ->and(Schema::connection('period')->hasColumn('config_options', 'price'))->toBeFalse()
        ->and(Schema::connection('period')->hasColumn('config_options', 'price_delta'))->toBeFalse();
});

it('konfigüratör zorunlu grup ve tek varsayılan seçeneği doğrular', function () {
    [$company, $period] = $this->createCompanyWithPeriod('CONFIGVAL');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $product = $this->createTestProduct(['code' => 'CFG-VAL', 'kind' => 'configurable']);
    $action = app(SaveConfigDefinition::class);

    expect(fn () => $action->handle(
        $product,
        ['name' => 'Boş Grup', 'is_required' => true, 'sort_order' => 0],
        [],
    ))->toThrow(ValidationException::class);

    expect(fn () => $action->handle(
        $product,
        ['name' => 'Çift Varsayılan', 'is_required' => false, 'sort_order' => 0],
        [
            ['label' => 'A', 'is_default' => true],
            ['label' => 'B', 'is_default' => true],
        ],
    ))->toThrow(ValidationException::class);
});

it('konfigüratör definition güncellemesini stale version ile reddeder', function () {
    [$company, $period] = $this->createCompanyWithPeriod('CONFIGLOCK');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);

    $product = $this->createTestProduct(['code' => 'CFG-LOCK', 'kind' => 'configurable']);
    $action = app(SaveConfigDefinition::class);

    $definition = $action->handle(
        $product,
        ['name' => 'Gövde', 'is_required' => false, 'sort_order' => 0],
        [['label' => 'Standart', 'is_default' => true]],
    );
    $version = (int) $definition->version;

    $updated = $action->handle(
        $product,
        ['name' => 'Gövde Yeni', 'is_required' => false, 'sort_order' => 0],
        [['label' => 'Standart', 'is_default' => true]],
        $definition,
        $version,
    );

    expect($updated->version)->toBe($version + 1)
        ->and(fn () => $action->handle(
            $product,
            ['name' => 'Gövde Bayat', 'is_required' => false, 'sort_order' => 0],
            [['label' => 'Standart', 'is_default' => true]],
            $definition,
            $version,
        ))->toThrow(StaleRecordException::class);
});
