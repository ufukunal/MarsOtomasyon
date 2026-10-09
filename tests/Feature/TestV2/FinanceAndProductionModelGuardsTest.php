<?php

use App\Models\Period\BankAccount;
use App\Models\Period\BankMovement;
use App\Models\Period\CashAccount;
use App\Models\Period\Contact;
use App\Models\Period\ImportFile;
use App\Models\Period\ProductionOrder;

beforeEach(function () {
    $this->createCompanyWithPeriod('V2GUARDMODELS');
});

it('v2 production order remaining quantities preserve fractional precision', function () {
    $order = new ProductionOrder([
        'planned_quantity' => '12.625',
        'completed_quantity' => '4.125',
        'cancelled_quantity' => '1.500',
    ]);
    expect($order->remainingQuantity())->toBe('7.000');
});

it('v2 finance account is soft-disabled rather than physically deleted', function () {
    $cash = CashAccount::query()->create([
        'code' => 'V2CASHLOCK', 'name' => 'Cash Account',
        'currency' => 'TRY', 'is_active' => true,
    ]);
    expect(fn () => $cash->delete())->toThrow(LogicException::class);
    expect(CashAccount::query()->whereKey($cash->id)->exists())->toBeTrue();
});

it('v2 bank ledger book movement cannot be overwritten with a new amount', function () {
    $bank = BankAccount::query()->create([
        'code' => 'V2BKIMM', 'bank_name' => 'Bank', 'account_name' => 'Account',
        'currency' => 'TRY', 'is_active' => true,
    ]);
    $book = BankMovement::query()->create([
        'bank_account_id' => $bank->id, 'movement_date' => '2026-09-01',
        'direction' => 'in', 'movement_type' => 'manual',
        'amount' => '10.0000', 'origin' => 'book',
    ]);
    expect(fn () => $book->update(['amount' => '100000.0000']))->toThrow(LogicException::class);
    expect((string) $book->fresh()->amount)->toBe('10.0000');
});

it('v2 bank statement movement permits only reconciliation metadata updates', function () {
    $bank = BankAccount::query()->create([
        'code' => 'V2BKSTM', 'bank_name' => 'Bank', 'account_name' => 'Account',
        'currency' => 'TRY', 'is_active' => true,
    ]);
    $row = BankMovement::query()->create([
        'bank_account_id' => $bank->id, 'movement_date' => '2026-09-01',
        'direction' => 'out', 'movement_type' => 'statement', 'origin' => 'statement',
        'amount' => '25.0000', 'statement_fingerprint' => str_repeat('f', 64),
    ]);
    expect(fn () => $row->update(['amount' => '26.0000']))->toThrow(LogicException::class);
    expect((string) $row->fresh()->amount)->toBe('25.0000');
});

it('v2 closed import file cannot be modified to increase or reopen the exchange rate', function () {
    $supplier = Contact::query()->create(['title' => 'V2 Import Supplier', 'type' => 'legal']);
    $import = ImportFile::query()->create([
        'number' => 'V2-IMP-LOCK', 'supplier_contact_id' => $supplier->id,
        'currency' => 'USD', 'exchange_rate' => '40.000000', 'status' => 'closed',
    ]);
    expect(fn () => $import->update(['exchange_rate' => '41.000000']))->toThrow(LogicException::class);
    expect($import->fresh()->exchange_rate)->toBe('40.000000');
});