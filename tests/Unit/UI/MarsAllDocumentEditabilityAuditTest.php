<?php

use App\Enums\DocumentType;

it('accounts for all eighteen persisted document types', function (): void {
    expect(array_map(fn (DocumentType $type) => $type->value, DocumentType::cases()))
        ->toBe([
            'quote', 'sales_order', 'dispatch', 'sales_invoice', 'proforma',
            'collection', 'contact_debit_credit', 'purchase_quote',
            'purchase_order', 'purchase_dispatch', 'goods_receipt',
            'supplier_invoice', 'payment', 'expense', 'advance',
            'advance_return', 'sales_return', 'purchase_return',
        ]);
});

it('keeps posted documents immutable and offers safe eight-screen editing', function (): void {
    $model = file_get_contents(base_path('app/Models/Period/Document.php'));
    $copy = file_get_contents(base_path('app/Actions/Documents/CreateEditableDocumentCopy.php'));
    $heading = file_get_contents(base_path('resources/views/components/v65/document-prototype-heading.blade.php'));

    expect($model)->toContain("getOriginal('status') === 'posted'")
        ->and($copy)->toContain("MutationAuthorizer::authorize(\$permission.'.create')")
        ->toContain("->where('relation_type', 'reversal_of')")
        ->toContain("->where('relation_type', 'edit_copy_of')")
        ->and($heading)->toContain('Düzenle')
        ->toContain('wire:click="startEditableCopy"')
        ->toContain('wire:click="editInvoice"')
        ->not->toContain('Ters Kayıt ile Düzelt');
});

it('updates unposted returns with source-line and version protections', function (): void {
    $action = file_get_contents(base_path('app/Actions/Returns/UpdateReturnDraft.php'));
    $center = file_get_contents(base_path('app/Livewire/Returns/ReturnCenter.php'));
    $view = file_get_contents(base_path('resources/views/livewire/returns/return-center.blade.php'));

    expect($action)->toContain('PeriodContext::ensureWritable()')
        ->toContain("MutationAuthorizer::authorize('returns.update')")
        ->toContain("->lockForUpdate()")
        ->toContain("->where('relation_type', 'reversal_of')")
        ->toContain('updateWithVersion(')
        ->toContain('return_draft_updated')
        ->toContain('source_line_id')
        ->and($center)->toContain('public function editDraft(')
        ->toContain('public function saveDraft(')
        ->toContain('abort_if($this->editingReturnDraft')
        ->and($view)->toContain('wire:click="saveDraft"')
        ->toContain('wire:click="editDraft(')
        ->toContain('wire:click="deleteReturn(')
        ->not->toContain('AppEnumsDocumentType');
});

it('supports historical correction for contact debit credit and contact transfers', function (): void {
    $debit = file_get_contents(base_path('app/Livewire/Finance/ContactDebitCreditForm.php'));
    $transfer = file_get_contents(base_path('app/Livewire/Finance/ContactTransferForm.php'));
    $debitView = file_get_contents(base_path('resources/views/livewire/finance/contact-debit-credit-form.blade.php'));
    $transferView = file_get_contents(base_path('resources/views/livewire/finance/contact-transfer-form.blade.php'));

    expect($debit)->toContain('public function prepareCorrection(')
        ->toContain('public function reverseForCorrection(')
        ->toContain('public bool $correctionReady = false')
        ->toContain("DocumentType::ContactDebitCredit->value")
        ->and($debitView)->toContain('wire:click="deleteRecord(')
        ->toContain('wire:click="post"')
        ->and($transfer)->toContain('public function prepareCorrection(')
        ->toContain('public function canViewContactBalances(): bool')
        ->toContain('private function sourceLedgerBalance(): string')
        ->toContain('public function confirmReverse(')
        ->and($transferView)->toContain('wire:click="prepareCorrection(')
        ->toContain('Görüntüleme yetkisi yok');
});

it('prefills real finance movements and enforces reversal before posting corrections', function (): void {
    $center = file_get_contents(base_path('app/Livewire/Finance/FinanceOperationCenter.php'));
    $view = file_get_contents(base_path('resources/views/livewire/finance/finance-operation-center.blade.php'));

    foreach ([
        'prepareDocumentCorrection(', 'prepareManualCorrection(',
        'prepareTransferCorrection(', 'cancelCorrection(',
        'correctionDocumentReady', 'correctionMovementReady',
        'correctionTransferReady', 'correctionDocumentId',
        'correctionMovementId', 'correctionTransferGroup',
    ] as $marker) {
        expect($center)->toContain($marker);
    }

    expect($center)->toContain("whereNull('reversal_of_id')")
        ->toContain("whereDoesntHave('incomingRelations'")
        ->toContain("\$mayViewManual ? CashMovement")
        ->and($view)->toContain('wire:click="prepareDocumentCorrection(')
        ->toContain('wire:click="prepareManualCorrection(')
        ->toContain('wire:click="prepareTransferCorrection(')
        ->toContain('wire:click="deleteManualRecord(')
        ->toContain('wire:click="deleteTransferRecord(')
        ->not->toContain('v65-finance-reversal');
});

it('locks correction state against browser tampering', function (): void {
    foreach ([
        'app/Livewire/Returns/ReturnCenter.php' => ['selectedReturnId', 'selectedReturnVersion', 'editingReturnDraft'],
        'app/Livewire/Finance/ContactDebitCreditForm.php' => ['correctionSourceId', 'correctionReady'],
        'app/Livewire/Finance/ContactTransferForm.php' => ['pendingReversalId', 'correctionSourceId', 'correctionReady'],
        'app/Livewire/Finance/FinanceOperationCenter.php' => ['correctionDocumentId', 'correctionDocumentReady',
            'correctionMovementId', 'correctionMovementReady', 'correctionTransferGroup', 'correctionTransferReady'],
    ] as $file => $fields) {
        $source = file_get_contents(base_path($file));
        expect($source)->toContain('use Livewire\Attributes\Locked;');
        expect(substr_count($source, '#[Locked]'))->toBeGreaterThanOrEqual(count($fields));
        foreach ($fields as $field) {
            expect($source)->toContain('$'.$field.' = ');
        }
    }
});

it('provides route-based safe correction navigation for source-linked and stock records', function (): void {
    $proforma = file_get_contents(base_path('resources/views/livewire/sales/proforma-detail.blade.php'));
    $receipt = file_get_contents(base_path('resources/views/livewire/purchases/document-editor.blade.php'));
    $stockSlip = file_get_contents(base_path('resources/views/livewire/pages/stock/warehouse-slip-detail.blade.php'));
    $stockCount = file_get_contents(base_path('resources/views/livewire/pages/stock/stock-count-detail.blade.php'));
    $stockTransfer = file_get_contents(base_path('resources/views/livewire/pages/stock/transfer-detail.blade.php'));
    $production = file_get_contents(base_path('resources/views/livewire/production/production-order-center.blade.php'));
    $securities = file_get_contents(base_path('resources/views/livewire/finance/securities-center.blade.php'));
    $transport = file_get_contents(base_path('resources/views/livewire/pages/warehouse/transport-slip-editor.blade.php'));

    expect($proforma)->toContain('Düzenle / Yeni Proforma İçin Kaynağı Aç')
        ->and($receipt)->toContain('Yeni Mal Kabul')
        ->and($stockSlip)->toContain('Yeni Düzeltme Fişi Aç')
        ->and($stockCount)->toContain('Düzenleme İçin Yeni Sayım Aç')
        ->and($stockTransfer)->toContain('Yeni Transferle Düzelt')
        ->and($production)->toContain('Düzeltme için Yeni Emir')
        ->and($securities)->toContain('Mali Düzelt')
        ->and($transport)->toContain("warehouse.transport.edit");
});
