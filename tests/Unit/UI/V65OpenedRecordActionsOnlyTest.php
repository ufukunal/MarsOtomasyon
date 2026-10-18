<?php

use Illuminate\Support\Str;

it('opens documents and returns from their lists without exposing Edit or Delete there', function (): void {
    $documents = file_get_contents(base_path('resources/views/livewire/sales/document-list.blade.php'));
    $returns = file_get_contents(base_path('resources/views/livewire/returns/return-center.blade.php'));
    $heading = file_get_contents(base_path('resources/views/components/v65/document-prototype-heading.blade.php'));

    expect($documents)->toContain('>Aç</x-v65.button>')
        ->toContain("route(\$detailRoute, ['id' => \$document->id])")
        ->not->toContain('wire:click="editInvoiceFromList(')
        ->not->toContain('wire:click="deleteInvoiceFromList(')
        ->and($heading)->toContain('wire:click="editInvoice"')
        ->toContain('wire:click="deleteInvoice"');

    $list = Str::between($returns, '@forelse($returns as $return)', '@endforelse');
    expect($list)->toContain('wire:click="selectReturn(')
        ->not->toContain('wire:click="editDraft(')
        ->not->toContain('wire:click="deleteReturn(')
        ->and($returns)->toContain('aria-label="Seçili iade"')
        ->toContain('wire:click="editDraft(')
        ->toContain('wire:click="deleteReturn(');
});

it('places voucher record mutations in an opened detail rather than history rows', function (): void {
    foreach (['finance/collection-form.blade.php', 'purchases/payment-form.blade.php'] as $file) {
        $view = file_get_contents(base_path('resources/views/livewire/'.$file));
        $history = Str::after($view, '@forelse($recentRecords as $record)');
        $history = Str::before($history, '@endforelse');

        expect($history)->toContain('wire:click="openFinancialRecord(')
            ->not->toContain('wire:click="deleteRecord(')
            ->not->toContain('wire:click="selectForCorrection(')
            ->and($view)->toContain('id="v65-finance-selected-record"')
            ->toContain('wire:click="deleteRecord(')
            ->toContain('wire:click="selectForCorrection(');
    }
    $trait = file_get_contents(base_path('app/Livewire/Concerns/WithFinancialRecordCorrection.php'));
    expect($trait)->toContain('public function openFinancialRecord(int $id): void')
        ->toContain('public ?int $selectedFinancialRecordId = null;')
        ->toContain('public function selectedFinancialRecordIsReversed(): bool');
});

it('shows cari fiş and virman actions after opening a record, not within their tables', function (): void {
    foreach ([
        'finance/contact-debit-credit-form.blade.php' => '@forelse($recentDocuments as $record)',
        'finance/contact-transfer-form.blade.php' => '@forelse($transfers as $transfer)',
    ] as $path => $marker) {
        $view = file_get_contents(base_path('resources/views/livewire/'.$path));
        $rows = Str::between($view, $marker, '@endforelse');
        expect($rows)->toContain('wire:click="openRecord(')
            ->not->toContain('wire:click="prepareCorrection(')
            ->not->toContain('wire:click="deleteRecord(')
            ->and($view)->toContain('v65-record-detail')
            ->toContain('wire:click="deleteRecord(')
            ->toContain('wire:click="prepareCorrection(');
    }
});

it('keeps finance and cheque-note list operations in record details', function (): void {
    $finance = file_get_contents(base_path('resources/views/livewire/finance/finance-operation-center.blade.php'));
    foreach ([
        '@forelse($financeDocuments as $record)' => 'wire:click="openFinanceDocument(',
        '@forelse($manualCorrectable as $record)' => 'wire:click="openManualRecord(',
        '@forelse($transferCorrectable as $group)' => 'wire:click="openTransferRecord(',
    ] as $start => $open) {
        $list = Str::between($finance, $start, '@endforelse');
        expect($list)->toContain($open)
            ->not->toContain('>Düzenle</button>')
            ->not->toContain('>Sil</button>');
    }
    expect($finance)->toContain('id="v65-finance-record-detail"')
        ->toContain('wire:click="deleteFinanceDocument(')
        ->toContain('wire:click="deleteManualRecord(')
        ->toContain('wire:click="deleteTransferRecord(');

    $security = file_get_contents(base_path('resources/views/livewire/finance/securities-center.blade.php'));
    $rows = Str::between($security, '@foreach($securities as $security)', '@endforeach');
    $payrolls = Str::between($security, '@foreach($payrolls as $payroll)', '@endforeach');
    expect($rows)->toContain('wire:click="openSecurityRecord(')
        ->not->toContain('wire:click="prepareSecurityEdit(')
        ->and($payrolls)->toContain('wire:click="openPayrollRecord(')
        ->not->toContain('wire:click="deletePayroll(')
        ->and($security)->toContain('id="v65-security-selected-record"')
        ->toContain('id="v65-payroll-selected-record"')
        ->toContain('wire:click="prepareSecurityEdit(')
        ->toContain('wire:click="deletePayroll(');
});
