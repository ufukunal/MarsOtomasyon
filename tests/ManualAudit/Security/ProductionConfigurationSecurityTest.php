<?php

use Tests\ManualAudit\Support\AuditSource;

test('CFG-260 production APP_DEBUG default false olur', function () {
    expect(AuditSource::read('config/app.php'))
        ->toContain("'debug' => (bool) env('APP_DEBUG', false)");
});

test('CFG-261 demo admin password hardcoded default taşımaz', function () {
    expect(AuditSource::read('config/demo.php'))
        ->toContain("'admin_password' => env('DEMO_ADMIN_PASSWORD')")
        ->not->toMatch('/DEMO_ADMIN_PASSWORD[\'"],\s*[\'"][^\'"]+/');
});

test('CFG-262 demo password boşsa production kodu default parola üretmez', function () {
    expect(AuditSource::grep(
        '/DEMO_ADMIN_PASSWORD[^;\n]*(?:password|secret)[\'"]\s*=>\s*[\'"][^\'"]+/i',
        ['app', 'database/seeders', 'config'],
    ))->toBe([]);
});

test('CFG-263 backup notification sentinel production readiness tarafından doğrulanabilir olmalıdır', function () {
    $combined = AuditSource::read('config/backup.php')
        .implode("\n", array_map(
            fn ($p) => AuditSource::read($p),
            AuditSource::files(['app/Support/Operations']),
        ));

    expect($combined)->toContain('BACKUP_NOTIFICATION_EMAIL')
        ->toMatch('/backup@mars\.test|notification/i');
});

test('CFG-264 runtime DB rolü ayrı operations configurationdan alınır', function () {
    expect(AuditSource::read('app/Actions/Periods/CreatePeriod.php'))
        ->toContain("config('operations.database.runtime_username')");
});

test('CFG-265 runtime role grant SQL SUPERUSER yetkisi vermez', function () {
    expect(AuditSource::read('app/Actions/Periods/CreatePeriod.php'))
        ->not->toMatch('/GRANT\s+SUPERUSER|ALTER\s+ROLE[\s\S]*SUPERUSER/i');
});

test('CFG-266 runtime role grant SQL CREATEDB yetkisi vermez', function () {
    expect(AuditSource::read('app/Actions/Periods/CreatePeriod.php'))
        ->not->toMatch('/ALTER\s+ROLE[\s\S]*CREATEDB|GRANT\s+CREATEDB/i');
});

test('CFG-267 runtime role grant SQL CREATEROLE yetkisi vermez', function () {
    expect(AuditSource::read('app/Actions/Periods/CreatePeriod.php'))
        ->not->toMatch('/ALTER\s+ROLE[\s\S]*CREATEROLE|GRANT\s+CREATEROLE/i');
});

test('CFG-268 runtime role grant SQL REPLICATION yetkisi vermez', function () {
    expect(AuditSource::read('app/Actions/Periods/CreatePeriod.php'))
        ->not->toMatch('/ALTER\s+ROLE[\s\S]*REPLICATION|GRANT\s+REPLICATION/i');
});

test('CFG-269 period schema public CREATE yetkisi runtime grant öncesi kaldırılır', function () {
    expect(AuditSource::read('app/Actions/Periods/CreatePeriod.php'))
        ->toContain('REVOKE CREATE ON SCHEMA public FROM PUBLIC');
});

test('CFG-270 production secrets yalnız env config üzerinden okunur kaynak koda literal gömülmez', function () {
    $offenders = AuditSource::grep(
        '/[\'"](?:password|secret|token|api_key|consumer_secret)[\'"]\s*=>\s*[\'"][A-Za-z0-9_\-]{8,}[\'"]/i',
        ['app', 'config'],
    );

    expect($offenders)->toBe([]);
});
