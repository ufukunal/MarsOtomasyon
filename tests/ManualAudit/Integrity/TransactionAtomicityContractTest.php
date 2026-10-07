<?php

use Tests\ManualAudit\Support\AuditSource;

function transactionContract(string $path, array $required): void
{
    expect(AuditSource::missingStrings([$path], $required))
        ->toBe([], "Transaction contract eksik: {$path}");
}

test('TX-043 document posting idempotent transaction sınırı ve row lock kullanır', fn () => transactionContract('app/Actions/Documents/PostDocument.php', ['IdempotencyKey::run(', 'lockForUpdate()']));

test('TX-044 document posting stock etkisini aynı orchestration içinde üretir', fn () => transactionContract('app/Actions/Documents/PostDocument.php', ['RecordStockMovement', 'VerifyPostedDocument']));

test('TX-045 document posting cari hareketini posting akışının içinde oluşturur', fn () => transactionContract('app/Actions/Documents/PostDocument.php', ['ContactTransaction::query()->create']));

test('TX-046 tahsilat ödeme hareketleri posting akışının transaction sınırı içindedir', fn () => transactionContract('app/Actions/Documents/PostDocument.php', ['CashMovement::query()->create', 'BankMovement::query()->create']));

test('TX-047 reversal idempotent row lock ile orijinali sabitler', fn () => transactionContract('app/Actions/Documents/ReverseDocument.php', ['IdempotencyKey::run(', 'lockForUpdate()']));

test('TX-048 finance transfer source ve target hareketini aynı period transactionında üretir', fn () => transactionContract('app/Actions/Finance/PostFinanceTransfer.php', ["DB::connection('period')->transaction", "'source' =>", "'target' =>"]));

test('TX-049 stock hareketi balance ve cost satırlarını transaction altında günceller', fn () => transactionContract('app/Actions/Stock/RecordStockMovement.php', ["DB::connection('period')->transaction", 'lockForUpdate()']));

test('TX-050 reservation mutationları idempotency ve lock kullanır', function () {
    expect(AuditSource::missingStrings(
        ['app/Actions/Stock/ConsumeReservation.php', 'app/Actions/Stock/ReserveStock.php'],
        ['IdempotencyKey::run(', 'lockForUpdate()'],
    ))->toBe([]);
});

test('TX-051 purchase post akışları ortak document posting orchestrationına bağlanır', function () {
    expect(AuditSource::missingStrings(
        ['app/Actions/Purchases/PostGoodsReceipt.php', 'app/Actions/Purchases/PostSupplierInvoice.php'],
        ['PostDocument'],
    ))->toBe([]);
});

test('TX-052 return post ve reverse akışları idempotent belge lifecycle kullanır', function () {
    expect(AuditSource::missingStrings(
        ['app/Actions/Returns/PostReturnDocument.php', 'app/Actions/Returns/ReverseReturnDocument.php'],
        ['IdempotencyKey::run(', 'lockForUpdate()'],
    ))->toBe([]);
});

test('TX-053 production completion period transaction ve locking taşır', fn () => transactionContract('app/Actions/Production/PostProductionCompletion.php', ["DB::connection('period')->transaction", 'lockForUpdate()']));

test('TX-054 import finalize mutationı explicit period transaction kullanır', function () {
    $paths = [
        'app/Actions/Imports/CloseImportFile.php',
        'app/Actions/Imports/RecalculateImportCosts.php',
        'app/Actions/Imports/ReceiveImportFile.php',
    ];
    $combined = implode("\n", array_map(fn ($p) => AuditSource::read($p), $paths));

    expect($combined)->toContain("DB::connection('period')->transaction");
});

test('TX-055 channel order import transaction ve writable guard kullanır', fn () => transactionContract('app/Actions/Channels/ImportChannelOrder.php', ['PeriodContext::ensureWritable()', "DB::connection('period')->transaction"]));

test('TX-056 cross database carry tek DB transactionı varmış gibi davranmaz', function () {
    $source = AuditSource::read('app/Actions/Periods/CarryPeriod.php');

    expect($source)
        ->toContain('IdempotencyKey::runMaster(')
        ->toContain('PeriodContext::withinSystem(')
        ->not->toMatch('/DB::connection\([\'"]master[\'"]\)->transaction\([\s\S]{0,8000}DB::connection\([\'"]period[\'"]\)->transaction/');
});

test('TX-057 carry source periodi final integrity aşamasından önce kapatmaz', function () {
    $source = AuditSource::read('app/Actions/Periods/CarryPeriod.php');

    $integrity = strpos($source, 'integrity');
    $closed = strpos($source, "\$sourceLocked->status = 'closed'");

    expect($integrity)->not->toBeFalse()
        ->and($closed)->not->toBeFalse()
        ->and($closed)->toBeGreaterThan($integrity);
});

test('TX-058 kritik mutations audit kaydını orchestration içinde üretir', function () {
    foreach ([
        'app/Actions/Documents/PostDocument.php',
        'app/Actions/Documents/ReverseDocument.php',
        'app/Actions/Finance/PostFinanceTransfer.php',
        'app/Actions/Production/PostProductionCompletion.php',
    ] as $path) {
        expect(AuditSource::read($path))->toContain('AuditContext::');
    }
});
