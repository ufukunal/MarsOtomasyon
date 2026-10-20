<?php

it('shows only the latest numbered quote revision in the quote list and exports', function (): void {
    $quotes = file_get_contents(base_path('app/Livewire/Sales/QuoteList.php'));
    $shared = file_get_contents(base_path('app/Livewire/Concerns/WithV65DocumentList.php'));

    expect($quotes)->toContain('protected function listQuery(): Builder')
        ->toContain('parent::listQuery()->where(')
        ->toContain("whereNull('documents.number')")
        ->toContain('orWhereNotExists(')
        ->toContain("->from('documents as later')")
        ->toContain("->where('later.document_type', DocumentType::Quote->value)")
        ->toContain("->whereColumn('later.number', 'documents.number')")
        ->toContain("->whereColumn('later.revision_no', '>', 'documents.revision_no')")
        ->toContain("->whereColumn('later.revision_no', 'documents.revision_no')")
        ->toContain("->whereColumn('later.id', '>', 'documents.id')")
        ->and($shared)->toContain('$this->listQuery()->paginate(')
        ->toContain('$this->listQuery()->chunk(');
});

it('keeps older quote revisions accessible inside the quote editor without changing other document lists', function (): void {
    $editor = file_get_contents(base_path('app/Livewire/Sales/QuoteEditor.php'));
    $quoteView = file_get_contents(base_path('resources/views/livewire/sales/document-editor.blade.php'));
    $heading = file_get_contents(base_path('resources/views/components/v65/document-prototype-heading.blade.php'));
    $listView = file_get_contents(base_path('resources/views/livewire/sales/document-list.blade.php'));
    $css = file_get_contents(base_path('resources/css/v65-shell.css'));

    expect($editor)->toContain('public function quoteRevisions(): Collection')
        ->toContain("where('document_type', DocumentType::Quote->value)")
        ->toContain("where('number', \$this->document->number)")
        ->toContain("orderByDesc('revision_no')")
        ->toContain("orderByDesc('id')")
        ->and($quoteView)->toContain('$this->quoteRevisions()')
        ->toContain(':quote-revisions=')
        ->not->toContain('<section class="v65-quote-revisions"')
        ->and($heading)->toContain('v65-quote-revision-picker')
        ->toContain('Revizyon Geçmişi')
        ->toContain("route('sales.quotes.edit', ['id' => \$revision->id])")
        ->toContain('Bu sürüm')
        ->toContain('$revision->document_date')
        ->toContain('$revision->status')
        ->toContain('$revision->grand_total')
        ->and($listView)->toContain('$this instanceof \\App\\Livewire\\Sales\\QuoteList')
        ->toContain("route('documents.history', ['id' => \$document->id])")
        ->and($css)->toContain('.v65-quote-revision-popover');
});

it('retains the append-only quote revision and document relation history', function (): void {
    $revision = file_get_contents(base_path('app/Actions/Sales/CreateQuoteRevision.php'));
    $editor = file_get_contents(base_path('app/Livewire/Sales/QuoteEditor.php'));

    expect($revision)->toContain("'revision_no' => (int) \$latest->revision_no + 1")
        ->toContain("'relation_type' => 'revision_of'")
        ->toContain('Document::query()->create([')
        ->and($editor)->toContain("return \$this->redirectRoute('sales.quotes.edit', ['id' => \$revision->id]");
});
