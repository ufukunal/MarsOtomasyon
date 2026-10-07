<?php

use Tests\ManualAudit\Support\AuditSource;

test('PAR-106 migration decimal alanlarının model castları string decimal precision kullanır', function () {
    $offenders = AuditSource::grep(
        '/[\'"](?:quantity|amount|price|cost|total|balance|rate)[^\'"]*[\'"]\s*=>\s*[\'"](?:float|double|real)[\'"]/i',
        ['app/Models'],
    );
    expect($offenders)->toBe([]);
});

test('PAR-107 money alanlarında float cast kullanılmaz', function () {
    expect(AuditSource::grep(
        '/[\'"][^\'"]*(?:amount|price|cost|total|balance)[^\'"]*[\'"]\s*=>\s*[\'"]float[\'"]/i',
        ['app/Models'],
    ))->toBe([]);
});

test('PAR-108 stok quantity migration precisionı 18,3 olarak korunur', function () {
    $source = AuditSource::read('database/migrations/period/0001_03_01_000010_create_stock_core_tables.php');
    expect($source)->toContain("decimal('quantity', 18, 3)");
});

test('PAR-109 stok ve maliyet money precisionı 18,4 olarak korunur', function () {
    $source = AuditSource::read('database/migrations/period/0001_03_01_000010_create_stock_core_tables.php');
    expect($source)->toContain("decimal('unit_cost', 18, 4)")
        ->toContain("decimal('total_cost', 18, 4)");
});

test('PAR-110 optimistic lock kullanılan lifecycle tablolarda version kolonu vardır', function () {
    foreach ([
        'database/migrations/master/0001_01_01_000021_create_periods_table.php',
        'database/migrations/period/0001_10_01_000010_create_channel_tables.php',
        'database/migrations/period/0001_09_01_000010_create_production_tables.php',
    ] as $path) {
        expect(AuditSource::read($path))->toContain("'version'");
    }
});

test('PAR-111 lifecycle enum alanları DB CHECK constraint ile korunur', function () {
    $combined = implode("\n", array_map(
        fn ($p) => AuditSource::read($p),
        AuditSource::periodMigrationFiles(),
    ));
    expect($combined)->toContain('CHECK (status IN');
});

test('PAR-112 nullable external identifier alanları migrationda açıkça nullabledır', function () {
    $source = AuditSource::read('database/migrations/period/0001_10_01_000010_create_channel_tables.php');
    expect($source)->toContain("external_product_id')->nullable()")
        ->toContain("external_listing_id')->nullable()");
});

test('PAR-113 JSON payload alanları jsonb olarak tanımlıdır', function () {
    $combined = implode("\n", array_map(fn ($p) => AuditSource::read($p), AuditSource::periodMigrationFiles()));
    expect($combined)->toContain('->jsonb(');
});

test('PAR-114 Period model tarih alanları date datetime cast taşır', function () {
    $source = AuditSource::read('app/Models/Period.php');
    expect($source)->toContain("'starts_on' => 'date'")
        ->toContain("'ends_on' => 'date'")
        ->toContain("'closed_at' => 'datetime'");
});

test('PAR-115 stock foreign key targetları period tablolarıdır', function () {
    $source = AuditSource::read('database/migrations/period/0001_03_01_000010_create_stock_core_tables.php');
    expect($source)->toContain("constrained('products')")
        ->toContain("constrained('locations')");
});

test('PAR-116 critical stock FK delete davranışı restricttir', function () {
    $source = AuditSource::read('database/migrations/period/0001_03_01_000010_create_stock_core_tables.php');
    expect($source)->toContain('restrictOnDelete()');
});

test('PAR-117 business uniqueness gerektiren kritik tablolarda unique constraint vardır', function () {
    $combined = implode("\n", array_map(fn ($p) => AuditSource::read($p), AuditSource::periodMigrationFiles()));
    foreach (['number_series_unique', 'stock_balances_unique', 'production_recipes_one_active_per_product'] as $needle) {
        expect($combined)->toContain($needle);
    }
});

test('PAR-118 number series document type year unique constraint taşır', function () {
    expect(AuditSource::read('database/migrations/period/0001_01_01_000010_create_number_series_table.php'))
        ->toContain("\$table->unique(['document_type', 'year']");
});

test('PAR-119 stock balance product location unique constraint taşır', function () {
    expect(AuditSource::read('database/migrations/period/0001_03_01_000010_create_stock_core_tables.php'))
        ->toContain("\$table->unique(['product_id', 'location_id'], 'stock_balances_unique')");
});

test('PAR-120 channel external event account type external id unique scope taşır', function () {
    expect(AuditSource::read('database/migrations/master/0001_10_01_000010_create_channel_master_tables.php'))
        ->toContain("['channel_account_id', 'event_type', 'external_id']");
});

test('PAR-121 channel listing local product scope account product unique constraint taşır', function () {
    expect(AuditSource::read('database/migrations/period/0001_10_01_000010_create_channel_tables.php'))
        ->toContain("\$table->unique(['channel_account_id', 'product_id'])");
});

test('PAR-122 master ve period idempotency key unique constraint taşır', function () {
    foreach ([
        'database/migrations/master/0001_03_01_000080_create_idempotency_keys_table.php',
        'database/migrations/period/0001_01_01_000060_create_idempotency_keys_table.php',
    ] as $path) {
        expect(AuditSource::read($path))->toContain("string('key', 120)->unique()");
    }
});

test('PAR-123 production recipe tek aktif revision partial unique index ile korunur', function () {
    expect(AuditSource::read('database/migrations/period/0001_09_01_000010_create_production_tables.php'))
        ->toContain('production_recipes_one_active_per_product')
        ->toContain('WHERE is_active = true');
});

test('PAR-124 kritik partial indexler migration raw SQL ile açıkça isimlendirilir', function () {
    $source = AuditSource::read('database/migrations/period/0001_09_01_000010_create_production_tables.php');
    expect($source)->toContain('CREATE UNIQUE INDEX production_recipes_one_active_per_product');
});

test('PAR-125 fillable lifecycle alanları version alanını istemciden açmaz', function () {
    $offenders = [];

    foreach (AuditSource::files(['app/Models']) as $path) {
        $source = AuditSource::read($path);

        if (preg_match('/protected\\s+\\$fillable\\s*=\\s*\\[([\\s\\S]*?)\\];/', $source, $fillable)
            && preg_match('/[\'"]version[\'"]/', $fillable[1])) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([]);
});
