<?php

use App\Models\PeriodModel;
use App\Models\Period\Contact;
use App\Models\Period\Location;
use App\Models\Period\Product;
use App\Support\Period\PeriodContext;
use Illuminate\Support\Facades\Schema;

it('Faz 1 kart tablolarını period DBde company_id olmadan tutar', function () {
    [$company, $period] = $this->createCompanyWithPeriod('SCHEMA');
    PeriodContext::useSystem($company->id, $period->id);

    $tables = [
        'locations',
        'units',
        'unit_conversions',
        'product_categories',
        'brands',
        'contacts',
        'contact_addresses',
        'contact_people',
        'contact_banks',
        'products',
        'variant_groups',
        'variant_attributes',
        'product_variant_values',
        'product_sets',
        'config_definitions',
        'config_options',
        'price_lists',
        'price_list_items',
    ];

    foreach ($tables as $table) {
        expect(Schema::connection('period')->hasTable($table))
            ->toBeTrue("{$table} bulunamadı")
            ->and(Schema::connection('period')->hasColumn($table, 'company_id'))
            ->toBeFalse("{$table}.company_id olmamalı");
    }

    expect(new Contact())->toBeInstanceOf(PeriodModel::class)
        ->and(new Product())->toBeInstanceOf(PeriodModel::class)
        ->and(new Location())->toBeInstanceOf(PeriodModel::class);
});

it('Faz 1 period migrationlarında Master companies/users tablolarına cross-DB FK tanımlamaz', function () {
    $files = glob(base_path('database/migrations/period/*.php')) ?: [];

    foreach ($files as $file) {
        $contents = file_get_contents($file) ?: '';

        expect($contents)->not->toMatch(
            "/constrained\(['\"](?:companies|users|periods|roles|permissions)['\"]\)/"
        );
    }
});

it('G-115 sınırı gereği erken purchase_requests veya supplier_quotes tablosu üretmez', function () {
    [$company, $period] = $this->createCompanyWithPeriod('G115');
    PeriodContext::useSystem($company->id, $period->id);

    expect(Schema::connection('period')->hasTable('purchase_requests'))->toBeFalse()
        ->and(Schema::connection('period')->hasTable('supplier_quotes'))->toBeFalse();
});

it('kart modellerinde BelongsToCompany veya company global scope kalmaz', function () {
    $files = [
        base_path('app/Models/Period/Contact.php'),
        base_path('app/Models/Period/Product.php'),
        base_path('app/Models/Period/Location.php'),
    ];

    foreach ($files as $file) {
        $contents = file_get_contents($file) ?: '';

        expect($contents)->not->toContain('BelongsToCompany')
            ->and($contents)->not->toContain('company_id');
    }
});
