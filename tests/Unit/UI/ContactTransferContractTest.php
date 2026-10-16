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
        ->toContain("IdempotencyKey::run(")
        ->and($reversal)->toContain("MutationAuthorizer::authorize('contact_transfers.cancel')")
        ->toContain("'reversal_of_id' => \$entry->id")
        ->and($migration)->toContain('contact_transfers_distinct_contacts')
        ->toContain('contact_transfers_try_only');
});
