<?php

it('edits only nonfinancial cheque and senet fields before any payroll action', function (): void {
    $source = file_get_contents(base_path('app/Actions/Finance/UpdateSecurityDetails.php'));
    $screen = file_get_contents(base_path('app/Livewire/Finance/SecuritiesCenter.php'));

    expect($source)->toContain("MutationAuthorizer::authorize('securities.update')")
        ->toContain("['portfolio', 'issued']")
        ->toContain("'due_date' =>")
        ->toContain("'notes' =>")
        ->not->toContain("'amount' =>")
        ->not->toContain("'contact_id' =>")
        ->and($screen)->toContain('public function prepareSecurityEdit(')
        ->toContain('public function saveSecurityEdit(');
});

it('shows only authorized editable rows and a confirmation-style details panel', function (): void {
    $view = file_get_contents(base_path('resources/views/livewire/finance/securities-center.blade.php'));

    expect($view)->toContain('prepareSecurityEdit(')
        ->toContain('Çek / Senet Belge Bilgileri')
        ->toContain('wire:click="saveSecurityEdit"')
        ->toContain('auth()->user()?->can(');
});
