<?php

use App\Enums\DocumentType;
use App\Support\Documents\DocumentEditCatalog;

it('keeps each of the eighteen persisted ERP document types directly accessible', function (): void {
    $expected = array_map(static fn (DocumentType $type): string => $type->value, DocumentType::cases());
    $actual = array_keys(DocumentEditCatalog::entries());
    sort($expected);
    sort($actual);

    expect($actual)->toBe($expected);
});

it('registers an exact history route inside the existing authorized document area', function (): void {
    $routes = file_get_contents(base_path('routes/web.php'));

    expect($routes)->toContain("use App\\Livewire\\Pages\\Documents\\DocumentHistoryDetail;")
        ->toContain("Route::get('/belgeler/gecmis/{id}', DocumentHistoryDetail::class)")
        ->toContain("->whereNumber('id')->name('documents.history')")
        ->toContain("->name('documents.edit-center')");
});

it('re-checks document type and user permission every time history renders', function (): void {
    $component = file_get_contents(base_path('app/Livewire/Pages/Documents/DocumentHistoryDetail.php'));

    expect($component)->toContain('use Livewire\\Attributes\\Locked;')
        ->toContain('#[Locked]')
        ->toContain('private function authorizedDocument(): Document')
        ->toContain('DocumentEditCatalog::entries()')
        ->toContain("->can(\$entry['permission'])")
        ->toContain('public function render(): View')
        ->toContain('$this->authorizedDocument()')
        ->toContain('DocumentEditCatalog::permitted(')
        ->not->toContain('public function save(')
        ->not->toContain('public function reverse(')
        ->not->toContain('public function update(');
});

it('does not leak unauthorized linked document records or contact details in history', function (): void {
    $component = file_get_contents(base_path('app/Livewire/Pages/Documents/DocumentHistoryDetail.php'));
    $view = file_get_contents(base_path('resources/views/livewire/pages/documents/document-history-detail.blade.php'));

    expect($component)->toContain("isset(\$allowed[\$row['other']->document_type->value])")
        ->toContain("->can('contacts.view')")
        ->and($view)->toContain('@if($showContact)')
        ->toContain('$linkedDocuments')
        ->toContain("route('documents.history', ['id' => \$link['other']->id])")
        ->toContain('Belge Bilgileri')
        ->toContain('Kayıtlı Kalemler')
        ->toContain('Bağlı Belgeler / Revizyonlar / Ters Kayıtlar');
});

it('keeps reversed and view-only financial records linked to their own history', function (): void {
    $catalog = file_get_contents(base_path('app/Support/Documents/DocumentEditCatalog.php'));
    $center = file_get_contents(base_path('resources/views/livewire/pages/documents/document-edit-center.blade.php'));
    $list = file_get_contents(base_path('resources/views/livewire/sales/document-list.blade.php'));

    expect($catalog)->toContain("return route('documents.history', ['id' => \$document->id]);")
        ->toContain('self::hasReversal($document)')
        ->toContain('self::canCorrect(')
        ->and($center)->toContain("route('documents.history', ['id' => \$document->id])")
        ->toContain('Kayıt Geçmişi')
        ->and($list)->toContain('Düzenleme / İncele');
});

it('leaves posted ledger and stock histories immutable rather than changing source records', function (): void {
    $model = file_get_contents(base_path('app/Models/Period/Document.php'));
    $copy = file_get_contents(base_path('app/Actions/Documents/CreateEditableDocumentCopy.php'));
    $history = file_get_contents(base_path('app/Livewire/Pages/Documents/DocumentHistoryDetail.php'));

    expect($model)->toContain("getOriginal('status') === 'posted'")
        ->and($copy)->toContain("->where('relation_type', 'reversal_of')")
        ->toContain("'relation_type' => 'edit_copy_of'")
        ->and($history)->not->toContain("DB::connection('period')->transaction")
        ->not->toContain('MutationAuthorizer::authorize');
});
