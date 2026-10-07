<?php

use Tests\ManualAudit\Support\AuditSource;

test('RACE-059 number series unique constraint ve row lock ile korunur', function () {
    expect(AuditSource::read('database/migrations/period/0001_01_01_000010_create_number_series_table.php'))
        ->toContain("\$table->unique(['document_type', 'year']");
    expect(AuditSource::read('app/Actions/Numbering/GenerateDocumentNumber.php'))
        ->toContain('lockForUpdate()');
});

test('RACE-060 document posting orijinal document satırını lock eder', function () {
    expect(AuditSource::read('app/Actions/Documents/PostDocument.php'))->toContain('lockForUpdate()');
});

test('RACE-061 document reversal aynı documenti lock eder ve existing reversal kontrol eder', function () {
    expect(AuditSource::read('app/Actions/Documents/ReverseDocument.php'))
        ->toContain('lockForUpdate()')
        ->toContain("'relation_type', 'reversal_of'");
});

test('RACE-062 stock balance update row lock altında yapılır', function () {
    expect(AuditSource::read('app/Actions/Stock/RecordStockMovement.php'))->toContain('lockForUpdate()');
});

test('RACE-063 reservation consume row lock kullanır', function () {
    expect(AuditSource::read('app/Actions/Stock/ConsumeReservation.php'))->toContain('lockForUpdate()');
});

test('RACE-064 reservation release row lock kullanır', function () {
    expect(AuditSource::read('app/Actions/Stock/ReleaseReservation.php'))->toContain('lockForUpdate()');
});

test('RACE-065 sales order remaining mutation lock ile serialize edilir', function () {
    $combined = AuditSource::read('app/Actions/Sales/CancelSalesOrderRemaining.php')
        .AuditSource::read('app/Actions/Sales/ReserveSalesOrderLines.php');

    expect($combined)->toContain('lockForUpdate()');
});

test('RACE-066 purchase order remaining mutation lock ile serialize edilir', function () {
    $combined = AuditSource::read('app/Actions/Purchases/CreateGoodsReceiptFromOrder.php')
        .AuditSource::read('app/Actions/Purchases/PurchaseLineAvailability.php');

    expect($combined)->toContain('lockForUpdate()');
});

test('RACE-067 dispatch to invoice lineage aynı source line üzerinde lock veya availability guard kullanır', function () {
    $combined = AuditSource::read('app/Actions/Sales/CreateInvoiceFromDispatches.php')
        .AuditSource::read('app/Actions/Documents/SourceLineAvailability.php');

    expect($combined)->toMatch('/lockForUpdate\(\)|available|remaining/i');
});

test('RACE-068 finance transfer hesapları lock öncesi deterministik sıraya sokulur', function () {
    $source = AuditSource::read('app/Actions/Finance/PostFinanceTransfer.php');

    expect($source)
        ->toContain('$sourceFirst = strcmp(')
        ->toContain('$sourceId < $targetId')
        ->toContain('if ($sourceFirst)');
});

test('RACE-069 finance transfer source target hesaplarını lock eder', function () {
    expect(AuditSource::read('app/Actions/Finance/PostFinanceTransfer.php'))
        ->toContain('lockForUpdate()');
});

test('RACE-070 production completion aynı orderı lock eder', function () {
    expect(AuditSource::read('app/Actions/Production/PostProductionCompletion.php'))
        ->toContain('lockForUpdate()');
});

test('RACE-071 channel external event registry unique claim ile duplicate eventi engeller', function () {
    expect(AuditSource::read('database/migrations/master/0001_10_01_000010_create_channel_master_tables.php'))
        ->toContain('channel_external_event_unique');
    expect(AuditSource::read('app/Support/Channels/ChannelExternalEventRegistryService.php'))
        ->toMatch('/lockForUpdate\(\)|insertOrIgnore|firstOrCreate/');
});

test('RACE-072 period carry target claim master row lock altında yapılır', function () {
    expect(AuditSource::read('app/Actions/Periods/CarryPeriod.php'))->toContain('lockForUpdate()');
});

test('RACE-073 period carry logical request master idempotency ile tekilleştirilir', function () {
    expect(AuditSource::read('app/Actions/Periods/CarryPeriod.php'))->toContain('IdempotencyKey::runMaster(');
});

test('RACE-074 period create duplicate company year metadata kontrolü taşır', function () {
    $source = AuditSource::read('app/Actions/Periods/CreatePeriod.php');

    expect($source)
        ->toContain("'company_id'")
        ->toContain("'year'")
        ->toContain('CREATE DATABASE');
});

test('RACE-075 queue ve operational claim modellerinde unique veya lock koruması bulunur', function () {
    $combined = AuditSource::read('app/Support/Printing/PrintJobTracker.php')
        .AuditSource::read('app/Support/Operations/DeploymentService.php');

    expect($combined)->toMatch('/lockForUpdate\(\)|unique|Idempotency|status/i');
});
