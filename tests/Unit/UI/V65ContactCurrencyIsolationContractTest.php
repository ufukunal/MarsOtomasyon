<?php

it('isolates the scalar cari balance and risk comparison to TRY', function (): void {
    $contact = file_get_contents(base_path('app/Models/Period/Contact.php'));
    $list = file_get_contents(base_path('app/Livewire/Pages/Contacts/ContactList.php'));

    expect($contact)
        ->toContain("->where('currency', 'TRY')")
        ->and($list)
        ->toContain("ct.currency = 'TRY'")
        ->toContain("Bakiye (TRY)")
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
