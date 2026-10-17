<?php

use App\Enums\DocumentType;
use App\Support\Documents\DocumentEditCatalog;

it('covers every DocumentType with its real edit or correction screen', function (): void {
    $defined = array_map(static fn (DocumentType $type): string => $type->value, DocumentType::cases());
    $configured = array_keys(DocumentEditCatalog::entries());
    sort($defined);
    sort($configured);
    expect($configured)->toBe($defined);

    $routes = file_get_contents(base_path('routes/web.php'));
    foreach (DocumentEditCatalog::entries() as $entry) {
        expect($entry['permission'])->toBeString()->not->toBe('')
            ->and($entry['route'])->toBeString()->not->toBe('')
            ->and($routes)->toContain("->name('".$entry['route']."')");
    }

    expect($routes)->toContain("->name('documents.edit-center')");
});

it('only lists authorized document types and never mutates a record in the shared index', function (): void {
    $index = file_get_contents(base_path('app/Livewire/Pages/Documents/DocumentEditCenter.php'));
    $blade = file_get_contents(base_path('resources/views/livewire/pages/documents/document-edit-center.blade.php'));
    $catalog = file_get_contents(base_path('app/Support/Documents/DocumentEditCatalog.php'));

    expect($index)->toContain('DocumentEditCatalog::permitted(')
        ->toContain("->whereIn('document_type', array_keys(\$allowed))")
        ->toContain('->paginate(25)')
        ->toContain("->with(['contact', 'incomingRelations', 'outgoingRelations'])")
        ->and($blade)->toContain('DocumentEditCatalog::url(')
        ->toContain('Belge Düzenleme Merkezi')
        ->toContain('$documents->links()')
        ->and($catalog)->toContain('canCorrect(')
        ->toContain('hasReversal(')
        ->toContain("'Geçmişi İncele'")
        ->toContain("'İncele'");
});

it('passes deep links only to existing permission and document-type checked corrections', function (): void {
    $link = file_get_contents(base_path('app/Livewire/Concerns/WithDocumentEditDeepLink.php'));

    expect($link)->toContain("request()->query('belge')")
        ->toContain("preg_match('/^[1-9][0-9]{0,17}$/D'")
        ->not->toContain('Document::query()->update');

    foreach ([
        'app/Livewire/Finance/CollectionForm.php' => 'selectForCorrection($id)',
        'app/Livewire/Purchases/PaymentForm.php' => 'selectForCorrection($id)',
        'app/Livewire/Finance/ContactDebitCreditForm.php' => 'prepareCorrection($id)',
        'app/Livewire/Finance/FinanceOperationCenter.php' => 'prepareDocumentCorrection($id)',
        'app/Livewire/Returns/ReturnCenter.php' => 'editDraft($id)',
    ] as $path => $method) {
        $source = file_get_contents(base_path($path));

        expect($source)->toContain('use WithDocumentEditDeepLink;')
            ->toContain('$this->linkedDocumentId()')
            ->toContain($method);
    }
});

it('also links stock, warehouse, security, finance and production records via real routes', function (): void {
    $routes = file_get_contents(base_path('routes/web.php'));
    $modules = DocumentEditCatalog::relatedModules();
    expect(count($modules))->toBeGreaterThanOrEqual(10);
    foreach ($modules as $entry) {
        expect($entry['note'])->toBeString()->not->toBe('')
            ->and($entry['permission'])->toBeString()->not->toBe('')
            ->and($routes)->toContain("->name('".$entry['route']."')");
    }
});

it('preserves audit history and the guarded correction policy for posted documents', function (): void {
    $action = file_get_contents(base_path('app/Actions/Documents/CreateEditableDocumentCopy.php'));
    $reopen = file_get_contents(base_path('app/Actions/Purchases/ReopenPurchaseDispatchForEditing.php'));
    $reverse = file_get_contents(base_path('app/Actions/Documents/ReverseDocument.php'));

    expect($action)->toContain("->lockForUpdate()")
        ->toContain("'relation_type' => 'edit_copy_of'")
        ->toContain("'status' => 'draft'")
        ->toContain('Kesinleşmiş belge önce Ters Kayıt')
        ->and($reopen)->toContain('ReopenPurchaseDispatchForEditing')
        ->and($reverse)->toContain("'relation_type' => 'reversal_of'");
});
