<?php

use App\Actions\Finance\ParseBankStatementFile;

it('normalizes incoming and outgoing CSV bank movements', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'mars-statement-');
    if ($path === false) {
        throw new RuntimeException('Cannot create isolated CSV fixture');
    }
    try {
        file_put_contents($path, "date;description;credit;debit\n2026-10-01;Deposit;100;0\n2026-10-02;Payment;0;22.5\n");
        $rows = (new ParseBankStatementFile)->handle($path, 'csv');
        expect($rows)->toHaveCount(2)
            ->and($rows[0]['direction'])->toBe('in')
            ->and($rows[0]['amount'])->toBe('100.0000')
            ->and($rows[1]['direction'])->toBe('out')
            ->and($rows[1]['amount'])->toBe('22.5000');
    } finally {
        unlink($path);
    }
});

it('rejects missing files and unsupported formats without network or database access', function (): void {
    $parser = new ParseBankStatementFile;
    expect(fn () => $parser->handle('/nonexistent/mars-statement.csv'))->toThrow(DomainException::class);
    $path = tempnam(sys_get_temp_dir(), 'mars-statement-');
    if ($path === false) {
        throw new RuntimeException('Cannot create isolated fixture');
    }
    try {
        file_put_contents($path, 'x');
        expect(fn () => $parser->handle($path, 'zip'))->toThrow(DomainException::class);
    } finally {
        unlink($path);
    }
});
