<?php

use App\Actions\Finance\ParseBankStatementFile;

it('v2 bank statement CSV interprets Turkish headers, UTF-8 BOM and decimal comma without float drift', function () {
    $path = tempnam(sys_get_temp_dir(), 'mars-bank-v2-');
    try {
        file_put_contents($path, "\xEF\xBB\xBFTarih;Açıklama;Alacak;Borç;Referans\n2026-10-09;Müşteri ödemesi;1.234,56;;R100\n2026-10-09;Tedarikçi; ;18,50;R101\n");
        $rows = (new ParseBankStatementFile)->handle($path, 'csv');

        expect($rows)->toHaveCount(2)
            ->and($rows[0]['date'])->toBe('2026-10-09')
            ->and($rows[0]['amount'])->toBe('1234.5600')
            ->and($rows[0]['direction'])->toBe('in')
            ->and($rows[0]['description'])->toBe('Müşteri ödemesi')
            ->and($rows[0]['reference'])->toBe('R100')
            ->and($rows[1]['amount'])->toBe('18.5000')
            ->and($rows[1]['direction'])->toBe('out');
    } finally {
        @unlink($path);
    }
});

it('v2 bank statement CSV handles signed amount and explicit credit-debit directions', function () {
    $path = tempnam(sys_get_temp_dir(), 'mars-bank-v2-');
    try {
        file_put_contents($path, "Date;Amount;Description\n2026-10-09;-15,20;Debit\n2026-10-09;25,70;Credit\n");
        $rows = (new ParseBankStatementFile)->handle($path, 'csv');

        expect($rows)->toHaveCount(2)
            ->and($rows[0]['direction'])->toBe('out')
            ->and($rows[0]['amount'])->toBe('15.2000')
            ->and($rows[1]['direction'])->toBe('in')
            ->and($rows[1]['amount'])->toBe('25.7000');
    } finally {
        @unlink($path);
    }
});

it('v2 bank statement refuses unknown input formats before parsing', function () {
    $path = tempnam(sys_get_temp_dir(), 'mars-bank-v2-');
    try {
        file_put_contents($path, 'data');
        expect(fn () => (new ParseBankStatementFile)->handle($path, 'binary'))
            ->toThrow(DomainException::class, 'Desteklenmeyen ekstre formatı.');
    } finally {
        @unlink($path);
    }
});

it('v2 bank statement refuses missing files and empty CSV movement lists', function () {
    expect(fn () => (new ParseBankStatementFile)->handle(sys_get_temp_dir().'/mars-nonexistent-'.uniqid(), 'csv'))
        ->toThrow(DomainException::class, 'Banka ekstre dosyası bulunamadı.');

    $path = tempnam(sys_get_temp_dir(), 'mars-bank-v2-');
    try {
        file_put_contents($path, "Date;Amount;Description\n;0;Empty\n");
        expect(fn () => (new ParseBankStatementFile)->handle($path, 'csv'))
            ->toThrow(DomainException::class, 'aktarılabilir hareket bulunamadı');
    } finally {
        @unlink($path);
    }
});

it('v2 MT940 parser preserves entry direction, amount, reference and closing balance', function () {
    $path = tempnam(sys_get_temp_dir(), 'mars-bank-v2-');
    try {
        file_put_contents($path, ":61:261009D123,45NTRFfoo//TX123\n:86:Supplier payment\n:62F:D261009TRY123,45\n");
        $rows = (new ParseBankStatementFile)->handle($path, 'mt940');

        expect($rows)->toHaveCount(1)
            ->and($rows[0]['date'])->toBe('2026-10-09')
            ->and($rows[0]['direction'])->toBe('out')
            ->and($rows[0]['amount'])->toBe('123.4500')
            ->and($rows[0]['reference'])->toBe('TX123')
            ->and($rows[0]['description'])->toBe('Supplier payment')
            ->and($rows[0]['balance'])->toBe('-123.4500');
    } finally {
        @unlink($path);
    }
});
