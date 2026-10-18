<?php

it('offers a safe edit or revision path on all eight document editors', function (): void {
    $heading = file_get_contents(base_path('resources/views/components/v65/document-prototype-heading.blade.php'));

    expect($heading)->toContain('Düzenle</a>')
        ->toContain('wire:click="editInvoice"')
        ->not->toContain('Ters Kayıt ile Düzelt')
        ->not->toContain('wire:confirm=')
        ->toContain('wire:click="startEditableCopy"');

    foreach (['sales', 'purchases'] as $side) {
        $view = file_get_contents(base_path("resources/views/livewire/{$side}/document-editor.blade.php"));

        expect($view)->toContain(':can-copy="$this->canCreateEditableCopy()"')
            ->toContain(':edit-permission="$this->permissionPrefix()"')
            ->toContain('v65-reference-edit-notice')
            ->toContain('Yeni bir düzenleme taslağı açabilirsiniz')
            ->toContain('<x-v65.document-readonly');
    }
});

it('requires view, edit, create and writable period before opening an immutable source copy', function (): void {
    $action = file_get_contents(base_path('app/Actions/Documents/CreateEditableDocumentCopy.php'));

    expect($action)->toContain("MutationAuthorizer::authorize(\$permission.'.view')")
        ->toContain("MutationAuthorizer::authorize(\$permission.'.update')")
        ->toContain("MutationAuthorizer::authorize(\$permission.'.create')")
        ->toContain('PeriodContext::ensureWritable()')
        ->toContain('$this->period->handle($today)')
        ->toContain("->lockForUpdate()")
        ->toContain("IdempotencyKey::run(")
        ->toContain("DB::connection('period')->transaction(");
});

it('keeps finalized accounting documents untouched and requires proven reversal', function (): void {
    $action = file_get_contents(base_path('app/Actions/Documents/CreateEditableDocumentCopy.php'));
    $model = file_get_contents(base_path('app/Models/Period/Document.php'));

    expect($action)->toContain("if (\$locked->status === 'posted' && ! DocumentRelation::query()")
        ->toContain("->where('relation_type', 'reversal_of')")
        ->toContain("->where('target_document_id', \$locked->id)")
        ->toContain('Kesinleşmiş belge önce Ters Kayıt')
        ->toContain('Ters kayıt fişi yeni düzenleme kopyasının kaynağı olamaz')
        ->toContain("'original_status' => \$locked->status")
        ->toContain("'new_document_is_independent' => true")
        ->and($model)->toContain("getOriginal('status') === 'posted'");
});

it('creates a new numbered-on-approval draft linked to its historical source without stock posting', function (): void {
    $action = file_get_contents(base_path('app/Actions/Documents/CreateEditableDocumentCopy.php'));
    $references = file_get_contents(base_path('resources/views/components/v65/document-sources.blade.php'));

    expect($action)->toContain("'number' => null")
        ->toContain("'status' => 'draft'")
        ->toContain("'document_date' => \$today->toDateString()")
        ->toContain("'cancelled_quantity' => '0.000'")
        ->toContain("'relation_type' => 'edit_copy_of'")
        ->toContain("'ui_meta', 'notes'")
        ->toContain("'source_line_id'")
        ->toContain("'requirements_snapshot'")
        ->toContain("whereHas('targetDocument'")
        ->toContain('document_edit_copy_created')
        ->toContain("return \$copy->load('lines')")
        ->and($references)->toContain('sourceDocument');
});

it('reopens an unfulfilled purchase dispatch in place instead of reserving its quantities twice', function (): void {
    $trait = file_get_contents(base_path('app/Livewire/Concerns/WithPrototypeDocumentControls.php'));
    $purchase = file_get_contents(base_path('resources/views/livewire/purchases/document-editor.blade.php'));
    $reopen = file_get_contents(base_path('app/Actions/Purchases/ReopenPurchaseDispatchForEditing.php'));
    $editor = file_get_contents(base_path('app/Livewire/Purchases/PurchaseDispatchEditor.php'));
    $confirm = file_get_contents(base_path('app/Actions/Purchases/ConfirmPurchaseDispatch.php'));
    $copy = file_get_contents(base_path('app/Actions/Documents/CreateEditableDocumentCopy.php'));

    $copyGuard = substr($trait, strpos($trait, 'public function canCreateEditableCopy()'),
        strpos($trait, 'public function startEditableCopy(') - strpos($trait, 'public function canCreateEditableCopy()'));

    expect($copyGuard)->not->toContain('DocumentType::PurchaseDispatch')
        ->not->toContain('DocumentType::GoodsReceipt')
        ->and($copy)->not->toContain("DocumentType::PurchaseDispatch->value =>")
        ->and($purchase)->toContain('canReopenForEditing()')
        ->toContain('Mal Kabul Kalemleri')
        ->toContain('<x-v65.document-heading')
        ->and($reopen)->toContain("->where('source_document_id', \$dispatch->id)")
        ->toContain("->whereIn('source_line_id', \$dispatch->lines()->select('id'))")
        ->toContain("->lockForUpdate()")
        ->toContain("MutationAuthorizer::authorize('purchase_dispatches.update')")
        ->toContain("PeriodContext::ensureWritable()")
        ->toContain("'purchase_dispatch_reopened'")
        ->and($editor)->toContain("public function reopenForEditing(")
        ->toContain("'reopenEdit'")
        ->and($confirm)->toContain('if ($locked->number === null)');
});

it('preserves quote revision metadata and uses the existing revision action', function (): void {
    $revision = file_get_contents(base_path('app/Actions/Sales/CreateQuoteRevision.php'));
    $trait = file_get_contents(base_path('app/Livewire/Concerns/WithPrototypeDocumentControls.php'));

    expect($revision)->toContain("'ui_meta' => \$latest->ui_meta")
        ->toContain("'relation_type' => 'revision_of'")
        ->and($trait)->toContain('public function startEditableCopy(')
        ->toContain('public function canCreateEditableCopy(): bool')
        ->toContain('DocumentType::Quote && $document->number !== null');

    foreach ([
        'app/Livewire/Sales/BaseSalesDocumentEditor.php',
        'app/Livewire/Purchases/BasePurchaseDocumentEditor.php',
    ] as $file) {
        $source = file_get_contents(base_path($file));
        expect($source)->toContain("'save', 'editCopy', ...\$this->extraMutationNames()");
    }
});

it('makes edit and correction navigation discoverable in the shared sales and purchase lists', function (): void {
    $list = file_get_contents(base_path('resources/views/livewire/sales/document-list.blade.php'));
    $listTrait = file_get_contents(base_path('app/Livewire/Concerns/WithV65DocumentList.php'));

    expect($list)->toContain('Düzenleme / İncele')
        ->toContain('Düzenle')
        ->and($listTrait)->toContain('closed')
        ->toContain('converted');
});
