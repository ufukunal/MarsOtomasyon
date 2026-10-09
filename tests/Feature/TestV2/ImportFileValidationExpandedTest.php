<?php

use App\Actions\Imports\SaveImportFile;
use App\Models\Period\Contact;
use App\Models\Period\ImportFile;

beforeEach(function () {
    [$company, $period] = $this->createCompanyWithPeriod('V2IMPVAL');
    $admin = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($admin, $company, $period);
});

it('v2 import file rejects missing supplier before any record creation', function () {
    expect(fn () => app(SaveImportFile::class)->handle(['currency' => 'TRY']))
        ->toThrow(DomainException::class);
    expect(ImportFile::query()->count())->toBe(0);
});

it('v2 import file rejects malformed currency without persisting a record', function () {
    expect(fn () => app(SaveImportFile::class)->handle(['supplier_contact_id' => 1, 'currency' => 'T']))
        ->toThrow(DomainException::class);
    expect(ImportFile::query()->count())->toBe(0);
});

it('v2 import file rejects nonpositive FX rate before writing records', function (string $rate) {
    expect(fn () => app(SaveImportFile::class)->handle([
        'supplier_contact_id' => 1, 'currency' => 'USD', 'exchange_rate' => $rate,
    ]))->toThrow(DomainException::class);
    expect(ImportFile::query()->count())->toBe(0);
})->with(['0', '-1']);

it('v2 import file forces TRY exchange rate to one even if supplied differently', function () {
    $supplier = Contact::query()->create(['title' => 'V2 Foreign Supplier', 'type' => 'legal']);
    $file = app(SaveImportFile::class)->handle([
        'supplier_contact_id' => $supplier->id,
        'currency' => 'TRY', 'exchange_rate' => '32.1000', 'status' => 'draft',
    ]);
    expect($file->currency)->toBe('TRY')
        ->and($file->exchange_rate)->toBe('1.000000')
        ->and(ImportFile::query()->count())->toBe(1);
});

it('v2 import file rejects invalid status transitions on initial save', function () {
    expect(fn () => app(SaveImportFile::class)->handle([
        'supplier_contact_id' => 1, 'currency' => 'USD', 'exchange_rate' => '36',
        'status' => 'received',
    ]))->toThrow(DomainException::class);
    expect(ImportFile::query()->count())->toBe(0);
});