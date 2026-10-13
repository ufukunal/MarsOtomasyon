<?php

it('isolates the scalar cari balance and risk comparison to TRY', function (): void {
    $contact = file_get_contents(base_path('app/Models/Period/Contact.php'));
    $list = file_get_contents(base_path('app/Livewire/Pages/Contacts/ContactList.php'));

    expect($contact)
        ->toContain("->where('currency', 'TRY')")
        ->and($list)
        ->toContain("ct.currency = 'TRY'")
        ->toContain("Column::make('balance_display', 'Bakiye')")
        ->toContain("->where('currency', 'TRY')");
});

it('preserves opening-only currencies and exports opening and closing balances', function (): void {
    $detail = file_get_contents(base_path('app/Livewire/Pages/Contacts/ContactDetail.php'));

    expect($detail)
        ->toContain("foreach (\$openings as \$currency => \$opening)")
        ->toContain("! \$movementTotals->contains('currency', \$currency)")
        ->toContain("\$movementTotals = \$movementTotals->sortBy('currency')->values()")
        ->toContain("'Devir Bakiyesi'")
        ->toContain("'Son Bakiye'")
        ->toContain("ksort(\$balances)");
});

it('keeps signed monetary values numeric in CSV while escaping formula-like text', function (): void {
    $method = new \ReflectionMethod(\App\Livewire\Pages\Contacts\ContactDetail::class, 'csvSafeRow');
    $row = ['', '=-2+1', 'İşlem', '@SUM(A1)', '-125.5000', '24.0000', '-101.5000', 'TRY'];
    $safe = $method->invoke(null, $row, [4, 5, 6]);

    expect($safe[1])->toBe("'=-2+1")
        ->and($safe[3])->toBe("'@SUM(A1)")
        ->and($safe[4])->toBe('-125.5000')
        ->and($safe[5])->toBe('24.0000')
        ->and($safe[6])->toBe('-101.5000');
});
