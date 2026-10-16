<?php

it('keeps cari virman atomic, balanced and permission-gated at the source', function (): void {
    $posting = file_get_contents(base_path('app/Actions/Finance/PostContactTransfer.php'));
    $reversal = file_get_contents(base_path('app/Actions/Finance/ReverseContactTransfer.php'));
    $migration = file_get_contents(base_path('database/migrations/period/0001_12_02_000020_create_contact_transfers.php'));

    expect($posting)->toContain("MutationAuthorizer::authorize('contact_transfers.create')")
        ->toContain("MutationAuthorizer::authorize('contact_transfers.override_balance')")
        ->toContain('lockForUpdate()')
        ->toContain("'currency' => 'TRY'")
        ->toContain("'direction' => \$sourceDirection")
        ->toContain("'direction' => \$targetDirection")
        ->toContain('IdempotencyKey::run(')
        ->and($reversal)->toContain("MutationAuthorizer::authorize('contact_transfers.cancel')")
        ->toContain("'reversal_of_id' => \$entry->id")
        ->and($migration)->toContain('contact_transfers_distinct_contacts')
        ->toContain('contact_transfers_try_only');
});

it('requires confirmation for reversals and does not offer already reversed documents', function (): void {
    $form = file_get_contents(base_path('app/Livewire/Finance/ContactTransferForm.php'));
    $view = file_get_contents(base_path('resources/views/livewire/finance/contact-transfer-form.blade.php'));

    expect($form)->toContain('public function prepareReverse(')
        ->toContain('public function confirmReverse(')
        ->toContain("whereIn('reversal_of_id'")
        ->and($view)->toContain('Cari Virman — Ters Kayıt Onayı')
        ->toContain('wire:click="confirmReverse"')
        ->toContain('!in_array((int) $transfer->id, $reversedIds, true)');
});


it('clears exceptional overdraft consent even when posting fails', function (): void {
    $form = file_get_contents(base_path('app/Livewire/Finance/ContactTransferForm.php'));
    expect($form)->toMatch('/function confirmOverdraft\([^)]*\): void\s*\{.*?try\s*\{\s*\$this->post\(\$action\);\s*\}\s*finally\s*\{\s*\$this->overdraftConfirmed = false;\s*\$this->overdraftAcknowledged = false;/s');
});


it('requires explicit acknowledgment on direct transfer posting paths', function (): void {
    $form = file_get_contents(base_path('app/Livewire/Finance/ContactTransferForm.php'));
    expect($form)->toContain('if ($this->overdraftConfirmed && ! $this->overdraftAcknowledged)')
        ->toContain("'overdraftAcknowledged' => 'Bakiye aşımını açıkça onaylayın.'");
});
