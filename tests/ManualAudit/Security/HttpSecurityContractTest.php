<?php

use Tests\ManualAudit\Support\AuditSource;

test('HTTP-170 state changing webhook route GET değildir', function () {
    $source = AuditSource::read('routes/web.php');

    expect($source)
        ->toContain("Route::post('/hooks/channel/{account}'")
        ->toContain("Route::put('/hooks/channel/hepsiburada/{account}/{event}'")
        ->toContain("Route::post('/hooks/channel/woocommerce/{account}'");
});

test('HTTP-171 logout state changing GET değil POST routeudur', function () {
    expect(AuditSource::read('routes/web.php'))->toContain("Route::post('/cikis'");
});

test('HTTP-172 channel asset route signed middleware kullanır', function () {
    expect(AuditSource::read('routes/web.php'))
        ->toMatch('/channel-assets[\s\S]{0,300}->middleware\([\'"]signed[\'"]\)/');
});

test('HTTP-173 signed asset route framework signature expiry doğrulamasına bağlıdır', function () {
    expect(AuditSource::read('routes/web.php'))->toContain("->middleware('signed')");
});

test('HTTP-174 private report export URL ayrıca kullanıcı ownership kontrol eder', function () {
    expect(AuditSource::read('app/Http/Controllers/ReportExportDownloadController.php'))
        ->toContain('$export->user_id !== $actor->id');
});

test('HTTP-175 controllerlar request path parametresini doğrudan filesystem pathe eklemez', function () {
    expect(AuditSource::grep(
        '/Storage::(?:disk\([^)]*\)->)?(?:get|download|path)\([^;]*\$request->(?:input|query|route)/s',
        ['app/Http/Controllers'],
    ))->toBe([]);
});

test('HTTP-176 controllerlarda absolute path request injection paterni bulunmaz', function () {
    expect(AuditSource::grep(
        '/(?:file_get_contents|fopen|readfile)\(\s*\$request->/',
        ['app/Http/Controllers'],
    ))->toBe([]);
});

test('HTTP-177 null byte veya encoded traversalı decode edip filesysteme geçiren controller yoktur', function () {
    expect(AuditSource::grep(
        '/urldecode\([^;]+\)[\s\S]{0,300}(?:Storage::|file_get_contents|fopen)/',
        ['app/Http/Controllers'],
    ))->toBe([]);
});

test('HTTP-178 download controllerları storage kaydını DB ownership veya permission ile çözer', function () {
    foreach ([
        'app/Http/Controllers/ProductImageController.php',
        'app/Http/Controllers/ImportErrorReportController.php',
        'app/Http/Controllers/ReportExportDownloadController.php',
    ] as $path) {
        expect(AuditSource::read($path))->toMatch('/authorize|can\(|AuthorizationException|user_id|attachments\(\)/');
    }
});
