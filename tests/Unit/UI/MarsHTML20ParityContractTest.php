<?php

it('maps the approved twenty operation routes without replacing the ERP navigation', function (): void {
    $routes = file_get_contents(base_path('routes/web.php'));
    $nav = file_get_contents(base_path('config/navigation.php'));
    foreach ([
        'sales.quotes.edit', 'sales.orders.edit', 'sales.dispatches.edit', 'sales.invoices.edit',
        'purchases.quotes.edit', 'purchases.orders.edit', 'purchases.dispatches.edit',
        'purchases.receipts.edit', 'purchases.invoices.edit', 'finance.collections.create',
        'purchases.payments.create', 'finance.contact-transfers',
        'finance.checks.index', 'finance.checks.incoming', 'finance.checks.outgoing',
        'finance.checks.endorsement', 'finance.notes.index', 'finance.notes.incoming',
        'finance.notes.outgoing', 'finance.notes.endorsement',
    ] as $route) {
        expect($routes)->toContain("->name('".$route."')");
    }
    expect($nav)->toContain("'label' => 'Üretim'")->toContain("'label' => 'E-Ticaret'");
});

it('keeps exactly ten original HTML document columns without losing financial details', function (): void {
    foreach (['sales', 'purchases'] as $section) {
        $view = file_get_contents(base_path("resources/views/livewire/{$section}/document-editor.blade.php"));
        expect(preg_match('/<table class="data-table v65-doc-table v65-prototype-lines">\s*<thead><tr>(.*?)<\/tr><\/thead>/s', $view, $matches))->toBe(1);
        expect(substr_count($matches[1], '<th'))->toBe(10);
        expect($view)->toContain('data-v65-fast-product-search')
            ->toContain('data-v65-fast-quantity')
            ->toContain('KDV Dahil*')
            ->toContain('Satır detayı')
            ->toContain('wire:click="addServiceLine"')
            ->toContain('wire:model.live="uiMeta.default_location_id"')
            ->toContain('Ürün bulunamadı');
    }
});

it('persists additional document header fields in a separate period JSON column', function (): void {
    $migration = file_get_contents(base_path('database/migrations/period/0001_12_02_000030_add_document_ui_meta.php'));
    $document = file_get_contents(base_path('app/Models/Period/Document.php'));
    expect($migration)->toContain("jsonb('ui_meta')")
        ->and($document)->toContain("'ui_meta' => 'array'");
    foreach ([
        'app/Actions/Documents/SaveSalesDocumentDraft.php',
        'app/Actions/Purchases/SavePurchaseDocumentDraft.php',
    ] as $file) {
        expect(file_get_contents(base_path($file)))->toContain("'ui_meta' => \$header['ui_meta'] ?? (\$document?->ui_meta ?? [])");
    }
    foreach ([
        'app/Livewire/Sales/BaseSalesDocumentEditor.php',
        'app/Livewire/Purchases/BasePurchaseDocumentEditor.php',
    ] as $file) {
        $source = file_get_contents(base_path($file));
        expect($source)->toContain('public array $uiMeta = []')
            ->toContain("'ui_meta' => \$this->uiMeta")
            ->toContain('$document->ui_meta')
            ->toContain('public function addServiceLine(): void');
    }
});

it('filters all cheque and note records on SQL before paginating', function (): void {
    $class = file_get_contents(base_path('app/Livewire/Finance/SecuritiesCenter.php'));
    $view = file_get_contents(base_path('resources/views/livewire/finance/securities-center.blade.php'));
    expect($class)->toContain('use WithPagination;')
        ->toContain("->paginate(25)")
        ->toContain("->orWhereHas('contact'")
        ->toContain('updatedListSearch')
        ->toContain('selectedPayrollTotal')
        ->and($view)->toContain('wire:model.live.debounce.300ms="listSearch"')
        ->toContain('wire:model.live="listDirection"')
        ->toContain('wire:model.live="listStatus"')
        ->toContain('Cari Etkisi (Kayıtta)')
        ->toContain('$securities->links()');
});

it('previews balances without allowing a browser estimate to replace the authoritative ledger', function (): void {
    $trait = file_get_contents(base_path('app/Livewire/Concerns/WithFinancialRecordCorrection.php'));
    expect($trait)->toContain('projectedFinancialContactBalance')
        ->toContain('projectedFinancialAccountBalance')
        ->toContain('financialAccountCurrencyMatches');
    foreach ([
        'finance/collection-form.blade.php',
        'purchases/payment-form.blade.php',
    ] as $file) {
        $view = file_get_contents(base_path('resources/views/livewire/'.$file));
        expect($view)->toContain('v65-finance-impact')
            ->toContain('Cari Sonra (Tahmini)')
            ->toContain('Kasa / Banka Sonra (Tahmini)');
    }
});

it('replaces eligible unprocessed securities only by ledger reversal and fresh issue', function (): void {
    $action = file_get_contents(base_path('app/Actions/Finance/ReplaceSecurity.php'));
    $class = file_get_contents(base_path('app/Livewire/Finance/SecuritiesCenter.php'));
    expect($action)->toContain("MutationAuthorizer::authorize('securities.cancel')")
        ->toContain("MutationAuthorizer::authorize('securities.create')")
        ->toContain("->where('reversal_of_id',")
        ->toContain("'reversal_of_id' => \$original->id")
        ->toContain("'status' => 'cancelled'")
        ->toContain('updateWithVersion(')
        ->toContain('IdempotencyKey::run(')
        ->toContain('$this->creator->handle(')
        ->toContain("DB::connection('period')->transaction")
        ->and($class)->toContain('public function confirmFinancialCorrection(')
        ->toContain('prepareFinancialCorrection(');
});

it('preserves receipt references and generated virman numbers as different fields', function (): void {
    $collection = file_get_contents(base_path('app/Actions/Finance/PostCollection.php'));
    $payment = file_get_contents(base_path('app/Actions/Purchases/PostPayment.php'));
    $transfer = file_get_contents(base_path('app/Actions/Finance/PostContactTransfer.php'));
    $migration = file_get_contents(base_path('database/migrations/period/0001_12_02_000040_add_contact_transfer_reference.php'));
    expect($collection)->toContain("'receipt_reference'")
        ->and($payment)->toContain("'receipt_reference'")
        ->and($transfer)->toContain("'reference' => trim((string) \$reference)")
        ->toContain("'number' => \$number")
        ->and($migration)->toContain("'reference', 100");
});

it('uses a type-safe currency hook for payment, while collection stays TRY', function (): void {
    $trait = file_get_contents(base_path('app/Livewire/Concerns/WithFinancialRecordCorrection.php'));
    $payment = file_get_contents(base_path('app/Livewire/Purchases/PaymentForm.php'));
    expect($trait)->toContain('protected function financialCurrency(): string')
        ->toContain('protected function financialExchangeRate(): string')
        ->toContain('$this->financialCurrency()')
        ->toContain('$this->financialExchangeRate()')
        ->not->toContain('$this->currency')
        ->not->toContain('$this->exchangeRate')
        ->and($payment)->toContain('protected function financialCurrency(): string')
        ->toContain('protected function financialExchangeRate(): string');
});

it('hides the balance preview from users without the matching account and contact permissions', function (): void {
    $trait = file_get_contents(base_path('app/Livewire/Concerns/WithFinancialRecordCorrection.php'));
    $center = file_get_contents(base_path('app/Livewire/Finance/SecuritiesCenter.php'));
    $view = file_get_contents(base_path('resources/views/livewire/finance/securities-center.blade.php'));
    expect($trait)->toContain('cash_accounts.view')
        ->toContain('bank_accounts.view')
        ->toContain('canViewFinancialAccountBalance')
        ->and($center)->toContain("can('contacts.view')")
        ->and($view)->toContain('canViewSecurityContactBalance');
});


it('never exposes a contact ledger balance through public financial preview methods without contacts.view', function (): void {
    $trait = file_get_contents(base_path('app/Livewire/Concerns/WithFinancialRecordCorrection.php'));

    expect($trait)->toContain("public function canViewFinancialContactBalance(): bool")
        ->toContain("auth()->check()")
        ->toContain("can('contacts.view')")
        ->toContain("if (! \$this->canViewFinancialContactBalance())")
        ->toContain("return '0.0000';");

    foreach ([
        'resources/views/livewire/finance/collection-form.blade.php',
        'resources/views/livewire/purchases/payment-form.blade.php',
    ] as $file) {
        $view = file_get_contents(base_path($file));

        expect(substr_count($view, 'canViewFinancialContactBalance()'))->toBeGreaterThanOrEqual(3)
            ->and($view)->toContain('Görüntüleme yetkisi yok')
            ->toContain('Hesap seçin');
    }
});

it('guards cheque and note preview permissions with explicit authentication checks', function (): void {
    $center = file_get_contents(base_path('app/Livewire/Finance/SecuritiesCenter.php'));
    $finance = file_get_contents(base_path('app/Livewire/Concerns/WithFinancialRecordCorrection.php'));

    expect($center)->toContain("return auth()->check() && auth()->user()->can('contacts.view')")
        ->toContain("abort_unless(auth()->user()?->can('securities.view'), 403);")
        ->and($finance)->toContain("return auth()->check() && auth()->user()->can('contacts.view')");
});
