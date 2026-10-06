<?php

use Tests\ManualAudit\Support\AuditSource;

test('CI-001 MasterModel master connection tanımlar', function () {
    expect(AuditSource::read('app/Models/MasterModel.php'))
        ->toContain("protected \$connection = 'master';");
});

test('CI-002 PeriodModel period connection tanımlar', function () {
    expect(AuditSource::read('app/Models/PeriodModel.php'))
        ->toContain("protected \$connection = 'period';");
});

test('CI-003 PeriodModel sorgu öncesi aktif period context ister', function () {
    expect(AuditSource::read('app/Models/PeriodModel.php'))
        ->toContain('PeriodContext::ensure()');
});

test('CI-004 period model klasöründeki somut modeller PeriodModel tabanını kullanır', function () {
    $offenders = [];

    foreach (AuditSource::files(['app/Models/Period']) as $path) {
        $source = AuditSource::read($path);

        if (! str_contains($source, 'extends PeriodModel')) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([]);
});

test('CI-005 period modelleri master connection override etmez', function () {
    $offenders = AuditSource::grep(
        '/\$connection\s*=\s*[\'"]master[\'"]/',
        ['app/Models/Period'],
    );

    expect($offenders)->toBe([]);
});

test('CI-006 period migrationları company_id kolonu oluşturmaz', function () {
    $offenders = [];

    foreach (AuditSource::periodMigrationFiles() as $path) {
        $source = AuditSource::read($path);

        if (preg_match('/(?:foreignId|unsignedBigInteger|bigInteger|integer|string)\(\s*[\'"]company_id[\'"]/', $source)) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([]);
});

test('CI-007 period migrationları Master tablolarına fiziksel FK kurmaz', function () {
    $masterTables = ['users', 'companies', 'periods', 'roles', 'permissions', 'sales_channel_accounts'];
    $offenders = [];

    foreach (AuditSource::periodMigrationFiles() as $path) {
        $source = AuditSource::read($path);

        foreach ($masterTables as $table) {
            if (preg_match('/constrained\(\s*[\'"]'.preg_quote($table, '/').'[\'"]\s*\)/', $source)) {
                $offenders[] = "{$path}:{$table}";
            }
        }
    }

    expect($offenders)->toBe([]);
});

test('CI-008 master migrationları period tablolarına fiziksel FK kurmaz', function () {
    $periodTables = [
        'products', 'documents', 'document_lines', 'stock_movements', 'stock_balances',
        'contacts', 'locations', 'production_orders', 'channel_order_snapshots',
    ];
    $offenders = [];

    foreach (AuditSource::masterMigrationFiles() as $path) {
        $source = AuditSource::read($path);

        foreach ($periodTables as $table) {
            if (preg_match('/constrained\(\s*[\'"]'.preg_quote($table, '/').'[\'"]\s*\)/', $source)) {
                $offenders[] = "{$path}:{$table}";
            }
        }
    }

    expect($offenders)->toBe([]);
});

test('CI-009 period migrationları period connection kullanır', function () {
    $offenders = [];

    foreach (AuditSource::periodMigrationFiles() as $path) {
        $source = AuditSource::read($path);

        if (! str_contains($source, "connection('period')")) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([]);
});

test('CI-010 master migrationları master connection kullanır', function () {
    $offenders = [];

    foreach (AuditSource::masterMigrationFiles() as $path) {
        $source = AuditSource::read($path);

        if (! str_contains($source, "connection('master')")) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([]);
});

test('CI-011 critical action kodunda default DB table connection kullanılmaz', function () {
    $offenders = AuditSource::grep(
        '/\bDB::table\s*\(/',
        ['app/Actions', 'app/Support'],
    );

    expect($offenders)->toBe([]);
});

test('CI-012 web route App class importları gerçek dosyalara karşılık gelir', function () {
    $source = AuditSource::read('routes/web.php');
    preg_match_all('/^use\s+(App\\\\[^;]+);$/m', $source, $matches);
    $missing = [];

    foreach ($matches[1] as $class) {
        $path = str_replace('\\', '/', preg_replace('/^App\\\\/', 'app/', $class)).'.php';

        if (! is_file(AuditSource::root().'/'.$path)) {
            $missing[] = "{$class} -> {$path}";
        }
    }

    expect($missing)->toBe([]);
});

test('CI-013 integrity checker sınıfları ortak IntegrityCheck sözleşmesini uygular', function () {
    $offenders = [];

    foreach (AuditSource::files(['app/Support/Integrity/Checks']) as $path) {
        $source = AuditSource::read($path);

        if (! str_contains($source, 'implements IntegrityCheck')) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([]);
});

test('CI-014 channel adapter resolver yalnız var olan adapter sınıflarını referanslar', function () {
    $source = AuditSource::read('app/Support/Channels/ChannelAdapterResolver.php');
    preg_match_all('/([A-Z][A-Za-z0-9_]+Adapter)::class/', $source, $matches);
    $all = implode("\n", array_map(
        static fn (string $path): string => AuditSource::read($path),
        AuditSource::files(['app/Support/Channels']),
    ));
    $missing = [];

    foreach (array_unique($matches[1]) as $class) {
        if (! str_contains($all, "class {$class}")) {
            $missing[] = $class;
        }
    }

    expect($missing)->toBe([]);
});

test('CI-015 production kodunda unresolved merge marker bulunmaz', function () {
    $offenders = AuditSource::grep(
        '/^(?:<{7}|={7}|>{7})/m',
        ['app', 'config', 'routes', 'database/migrations', 'resources/views'],
    );

    expect($offenders)->toBe([]);
});

test('CI-016 production kodunda kritik TODO marker bırakılmaz', function () {
    $offenders = AuditSource::grep(
        '/\b(?:TODO|FIXME|HACK|XXX)\b/i',
        ['app', 'config', 'routes', 'database/migrations'],
    );

    expect($offenders)->toBe([]);
});

test('CI-017 production kodunda NotImplementedException benzeri stub bulunmaz', function () {
    $offenders = AuditSource::grep(
        '/NotImplemented(?:Exception)?|throw new \\\\?LogicException\([\'"][^\'"]*(?:not implemented|uygulanmadı)/i',
        ['app'],
    );

    expect($offenders)->toBe([]);
});

test('CI-018 test fake ve mock namespace kodu production app altına sızmaz', function () {
    $offenders = AuditSource::grep(
        '/namespace\s+App\\\\(?:Fake|Fakes|Mock|Mocks|Stub|Stubs)\\\\/',
        ['app'],
    );

    expect($offenders)->toBe([]);
});

test('CI-019 period model dosyaları doğrudan MasterModel extend etmez', function () {
    $offenders = AuditSource::grep(
        '/extends\s+MasterModel\b/',
        ['app/Models/Period'],
    );

    expect($offenders)->toBe([]);
});

test('CI-020 MasterModel doğrudan period context bağımlılığı taşımaz', function () {
    expect(AuditSource::read('app/Models/MasterModel.php'))
        ->not->toContain('PeriodContext');
});

test('CI-021 period migration adı ile klasör ayrımı korunur', function () {
    foreach (AuditSource::periodMigrationFiles() as $path) {
        expect($path)->toStartWith('database/migrations/period/');
    }
});

test('CI-022 master migration adı ile klasör ayrımı korunur', function () {
    foreach (AuditSource::masterMigrationFiles() as $path) {
        expect($path)->toStartWith('database/migrations/master/');
    }
});

test('CI-023 action sınıflarında test-only environment branch kullanılmaz', function () {
    $offenders = AuditSource::grep(
        '/app\(\)->environment\(\s*[\'"]testing[\'"]\s*\)|APP_ENV\s*===?\s*[\'"]testing[\'"]/',
        ['app/Actions'],
    );

    expect($offenders)->toBe([]);
});

test('CI-024 production kodu tests namespace sınıflarına bağımlı değildir', function () {
    $offenders = AuditSource::grep(
        '/\bTests\\\\/',
        ['app', 'config', 'routes'],
    );

    expect($offenders)->toBe([]);
});

test('CI-025 source dosyalarında duplicate fully-qualified class adı bulunmaz', function () {
    $classes = [];
    $duplicates = [];

    foreach (AuditSource::files(['app']) as $path) {
        $source = AuditSource::read($path);
        preg_match('/namespace\s+([^;]+);/', $source, $ns);
        preg_match('/\b(?:final\s+|abstract\s+)?class\s+([A-Za-z_][A-Za-z0-9_]*)/', $source, $class);

        if (! isset($ns[1], $class[1])) {
            continue;
        }

        $fqcn = trim($ns[1]).'\\'.$class[1];

        if (isset($classes[$fqcn])) {
            $duplicates[] = [$fqcn, $classes[$fqcn], $path];
        } else {
            $classes[$fqcn] = $path;
        }
    }

    expect($duplicates)->toBe([]);
});
