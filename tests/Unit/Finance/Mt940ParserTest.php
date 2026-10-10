<?php

use App\Actions\Finance\ParseBankStatementFile;

it('parses incoming MT940 payment reference and balance', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'mars-mt940-');
    if ($file === false) {
        throw new RuntimeException('Cannot create fixture');
    }
    try {
        file_put_contents($file, ":61:261010C100,00NTRF//PAY123\n:86:Incoming payment\n:62F:C261010TRY100,00\n");
        $rows = (new ParseBankStatementFile)->handle($file, 'mt940');
        expect($rows)->toHaveCount(1)
            ->and($rows[0]['date'])->toBe('2026-10-10')
            ->and($rows[0]['direction'])->toBe('in')
            ->and($rows[0]['amount'])->toBe('100.0000')
            ->and($rows[0]['reference'])->toBe('PAY123')
            ->and($rows[0]['balance'])->toBe('100.0000');
    } finally {
        unlink($file);
    }
});

it('rejects empty MT940 files', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'mars-mt940-');
    if ($file === false) {
        throw new RuntimeException('Cannot create fixture');
    }
    try {
        file_put_contents($file, ':20:EMPTY');
        expect(fn () => (new ParseBankStatementFile)->handle($file, 'mt940'))->toThrow(DomainException::class);
    } finally {
        unlink($file);
    }
});
