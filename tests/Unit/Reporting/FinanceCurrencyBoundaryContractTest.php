<?php

it('groups report subtotals by currency and retains numeric TRY-filtered dashboard values', function (): void {
    foreach ([
        'AbstractDocumentReportQuery.php' => "groupBy('d.currency')",
        'ContactTransactionReportQuery.php' => "groupBy('ct.currency')",
        'AbstractFinanceMovementReportQuery.php' => "groupBy('a.currency')",
        'ChannelOrderReportQuery.php' => "groupBy('d.currency')",
    ] as $filename => $grouping) {
        $source = file_get_contents(base_path('app/Support/Reporting/Queries/'.$filename));
        expect($source)->toContain($grouping)->toContain('CurrencySeparatedTotals::fromGroups');
    }
    $formatter = file_get_contents(base_path('app/Support/Reporting/CurrencySeparatedTotals.php'));
    expect($formatter)->toContain('$selectedCurrency !== null')
        ->toContain("implode(' / ', $parts)");
});

it('retains carry-forward contacts whose USD and EUR balances numerically cancel', function (): void {
    $code = file_get_contents(base_path('app/Actions/Periods/CopyPeriodCards.php'));
    expect($code)->toContain("->groupBy('contact_id', 'currency')")
        ->toContain("SUM(CASE WHEN direction = 'debit' THEN amount ELSE -amount END) <> 0");
});

it('reports carry preview monetary totals by original currency and account currency', function (): void {
    $preview = file_get_contents(base_path('app/Actions/Periods/PreviewPeriodCarry.php'));
    expect($preview)->toContain("currencyMovementTotal('contact_transactions', 'debit')")
        ->toContain("currencyMovementTotal('bank_movements', 'out', 'bank_accounts', 'bank_account_id')")
        ->toContain("currencyMovementTotal('cash_movements', 'in', 'cash_accounts', 'cash_account_id')")
        ->toContain('CurrencySeparatedTotals::fromGroups');
});

it('keeps demo ledger data intact while clarifying the document current status on screen', function (): void {
    $detail = file_get_contents(base_path('app/Livewire/Pages/Contacts/ContactDetail.php'));
    $blade = file_get_contents(base_path('resources/views/livewire/pages/contacts/contact-detail.blade.php'));
    expect($detail)->toContain('movementDisplayDescription(')
        ->toContain("document?->status === 'posted'")
        ->toContain('Demo hazırlık notu; güncel belge durumu: Kesinleşti.')
        ->and($blade)->toContain('movementDisplayDescription($move)');
});
