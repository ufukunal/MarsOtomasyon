<?php

use App\Livewire\Finance\SecurityScreen;

it('exposes separate routes for the eight cheque and promissory note operations', function (): void {
    $routeSource = file_get_contents(base_path('routes/web.php'));
    $navigation = file_get_contents(base_path('config/navigation.php'));
    $blade = file_get_contents(base_path('resources/views/livewire/finance/securities-center.blade.php'));

    foreach ([
        'finance.checks.index', 'finance.checks.incoming', 'finance.checks.outgoing',
        'finance.checks.endorsement', 'finance.notes.index', 'finance.notes.incoming',
        'finance.notes.outgoing', 'finance.notes.endorsement',
    ] as $name) {
        expect($routeSource)->toContain("->name('".$name."')")
            ->and($navigation)->toContain("'route' => '".$name."'");
    }

    expect(class_exists(SecurityScreen::class))->toBeTrue()
        ->and($blade)->toContain('$screenMode')
        ->toContain('wire:click="postPayroll"')
        ->toContain('wire:click="create"');
});

it('requires server-side scoped kind and direction on cheque/senet creation and endorsement', function (): void {
    $source = file_get_contents(base_path('app/Livewire/Finance/SecuritiesCenter.php'));
    expect($source)->toContain('$this->scopeDirection() ?? $this->direction')
        ->toContain('$this->scopeKind() ?? $this->kind')
        ->toContain("['center', 'create']")
        ->toContain("['center', 'endorse']")
        ->toContain("'endorsement' : \$this->payrollAction")
        ->toContain("->where('status', 'portfolio')");
});

it('restricts standalone creation and endorsement routes by server roles', function (): void {
    $source = file_get_contents(base_path('app/Livewire/Finance/SecurityScreen.php'));

    expect($source)->toContain("can('securities.create')")
        ->toContain("can('security_payrolls.create')");
});


it('rejects cross-direction editing on scoped cheque and note screens', function (): void {
    $source = file_get_contents(base_path('app/Livewire/Finance/SecuritiesCenter.php'));

    expect($source)->toContain('$security->direction !== $this->scopeDirection()')
        ->toContain("'instrumentNo' => ['required', 'string', 'max:100']")
        ->toContain("'contactId' => ['required', 'integer', 'min:1']")
        ->toContain("'amount' => ['required', 'numeric', 'gt:0']");
});


it('isolates instrument edits from issuance and forbids scoped edits and unsafe payroll reversal', function (): void {
    $source = file_get_contents(base_path('app/Livewire/Finance/SecuritiesCenter.php'));
    expect($source)->toContain("['create', 'edit', 'payroll', 'reversePayroll']")
        ->toContain("\$this->childMutationKey('edit', \$id)")
        ->toContain("\$this->completeMutation('edit')")
        ->toContain("abort_unless(auth()->user()?->can('securities.update'), 403)")
        ->toContain("abort_if(\$this->scopeDirection() !== null && \$security->direction !== \$this->scopeDirection(), 404)")
        ->toContain("abort_unless(auth()->user()?->can('security_payrolls.cancel'), 403)")
        ->toContain("'reversalReason' => ['required', 'string', 'min:3', 'max:1000']")
        ->toContain("if (\$this->scopeDirection() !== null) {");
});
