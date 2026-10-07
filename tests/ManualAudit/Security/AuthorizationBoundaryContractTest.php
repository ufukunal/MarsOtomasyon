<?php

use Tests\ManualAudit\Support\AuditSource;

test('AUTH-153 UI gizlese bile kritik action backend authorization taşır', function () {
    foreach ([
        'app/Actions/Sales/PostSalesInvoice.php',
        'app/Actions/Purchases/PostGoodsReceipt.php',
        'app/Actions/Purchases/PostSupplierInvoice.php',
        'app/Actions/Returns/PostReturnDocument.php',
        'app/Actions/Documents/ReverseDocument.php',
        'app/Actions/Finance/PostFinanceTransfer.php',
        'app/Actions/Periods/CarryPeriod.php',
    ] as $path) {
        expect(AuditSource::read($path))->toMatch('/MutationAuthorizer::authorize|Gate::authorize/');
    }
});

test('AUTH-154 document mutation current period fiziksel sınırında model çözer', function () {
    expect(AuditSource::read('app/Models/Period/Document.php'))->toContain('extends PeriodModel');
});

test('AUTH-155 channel account current company ile eşleştirilir', function () {
    expect(AuditSource::read('app/Support/Channels/ChannelSyncRecorder.php'))
        ->toContain('PeriodContext::companyId()')
        ->toContain('Kanal sync hesabı aktif şirkete ait değil.');
});

test('AUTH-156 başka period modeli current period connection üzerinden çözülemez', function () {
    expect(AuditSource::read('app/Models/PeriodModel.php'))
        ->toContain('PeriodContext::ensure()')
        ->toContain("protected \$connection = 'period';");
});

test('AUTH-157 product image download product policy authorization taşır', function () {
    expect(AuditSource::read('app/Http/Controllers/ProductImageController.php'))
        ->toContain("\$this->authorize('view', \$product)");
});

test('AUTH-158 report export download user company period ve permission kontrol eder', function () {
    $source = AuditSource::read('app/Http/Controllers/ReportExportDownloadController.php');

    foreach (['$export->user_id', 'PeriodContext::companyId()', "table('period_user_access')", 'Gate::forUser'] as $needle) {
        expect($source)->toContain($needle);
    }
});

test('AUTH-159 import error report permission kontrol eder', function () {
    expect(AuditSource::read('app/Http/Controllers/ImportErrorReportController.php'))
        ->toContain("can('imports.view')");
});

test('AUTH-160 attachment ve image download ownership kontrol eder', function () {
    expect(AuditSource::read('app/Http/Controllers/ProductImageController.php'))
        ->toContain('attachments()')
        ->toContain('whereKey(');
});

test('AUTH-161 print template kullanımı aktif company ve period scope ile korunur', function () {
    $source = AuditSource::read('app/Support/Printing/PrintManager.php');

    expect($source)
        ->toContain('PeriodContext::ensure()')
        ->toContain('$template->company_id')
        ->toContain('PeriodContext::companyId()');
});

test('AUTH-162 backup deploy operasyonları authorization sınırı taşır', function () {
    $combined = AuditSource::read('app/Livewire/Pages/Settings/OperationsCenter.php')
        .AuditSource::read('app/Support/Operations/DeploymentService.php');

    expect($combined)->toMatch('/authorize|Gate::|can\(/');
});

test('AUTH-163 period close reopen carry permission kontrolü taşır', function () {
    expect(AuditSource::read('app/Actions/Periods/ClosePeriod.php'))->toContain("Gate::authorize('periods.cancel')");
    expect(AuditSource::read('app/Actions/Periods/ReopenPeriod.php'))->toContain("Gate::authorize('periods.reopen')");
    expect(AuditSource::read('app/Actions/Periods/CarryPeriod.php'))->toContain('Gate::authorize');
});

test('AUTH-164 cross-company copy explicit permission kontrolü taşır', function () {
    $source = AuditSource::read('app/Actions/Companies/CopyRecordsBetweenCompanies.php');

    expect($source)->toMatch('/CheckCompanyCopyPermission|CompanyCopyPermission|authorize/');
});

test('AUTH-165 finance reversal ve cancellation permission guard taşır', function () {
    $combined = AuditSource::read('app/Actions/Finance/ReverseFinanceTransfer.php')
        .AuditSource::read('app/Actions/Finance/ReverseManualFinanceMovement.php');

    expect($combined)->toMatch('/MutationAuthorizer::authorize|Gate::authorize/');
});

test('AUTH-166 sales purchase cancel reverse actionları authorization taşır', function () {
    $combined = AuditSource::read('app/Actions/Sales/CancelSalesOrderRemaining.php')
        .AuditSource::read('app/Actions/Documents/ReverseDocument.php')
        .AuditSource::read('app/Actions/Purchases/PostSupplierInvoice.php');

    expect($combined)->toMatch('/MutationAuthorizer::authorize|Gate::authorize/');
});

test('AUTH-167 channel credential ve account yönetimi permission kontrolü taşır', function () {
    $combined = AuditSource::read('app/Actions/Channels/SaveSalesChannelAccount.php')
        .AuditSource::read('app/Livewire/Channels/ChannelAccountCenter.php');

    expect($combined)->toMatch('/authorize|Gate::|MutationAuthorizer|can\(/');
});

test('AUTH-168 authenticated UI routes auth middleware grubundadır', function () {
    $source = AuditSource::read('routes/web.php');

    expect($source)->toContain("Route::middleware('auth')->group");
});

test('AUTH-169 Livewire mutationlar idempotent wrapper ile backendden korunur', function () {
    expect(AuditSource::read('app/Livewire/Concerns/WithIdempotentMutations.php'))
        ->toContain('runMasterMutation')
        ->toContain('runPeriodMutation');
});
