<?php

use App\Enums\DocumentType;

it('restricts all seven corporate document outputs to authenticated period and role', function (): void {
    $routes = file_get_contents(base_path('routes/web.php'));
    $controller = file_get_contents(base_path('app/Http/Controllers/PrintBusinessDocumentController.php'));

    expect($routes)->toContain("->name('documents.print')")
        ->toContain("->where('format', 'html|pdf')")
        ->and($controller)->toContain('PeriodContext::ensure()')
        ->toContain("->findOrFail(\$document)")
        ->toContain("auth()->user()?->can(\$definition[1])")
        ->toContain("abort_unless(in_array(\$format, ['html', 'pdf'], true), 404)")
        ->toContain('Cache-Control')
        ->toContain('private, no-store')
        ->toContain('X-Content-Type-Options');

    foreach ([
        'Quote', 'SalesOrder', 'Dispatch', 'SalesInvoice',
        'PurchaseOrder', 'GoodsReceipt', 'SupplierInvoice',
    ] as $type) {
        expect($controller)->toContain('DocumentType::'.$type.' =>');
    }
});

it('renders company and contact identity with persisted lines and totals', function (): void {
    $markup = file_get_contents(base_path('resources/views/printing/business-document.blade.php'));
    $controller = file_get_contents(base_path('app/Http/Controllers/PrintBusinessDocumentController.php'));

    expect($markup)->toContain('$company->legal_name')
        ->toContain('$company->tax_number')
        ->toContain('$document->contact?->title')
        ->toContain('$document->lines as $line')
        ->toContain('$line->line_total')
        ->toContain('$document->vat_amount')
        ->toContain('$document->grand_total')
        ->toContain('e-Fatura/e-Arşiv veya UBL')
        ->toContain('display: table-header-group')
        ->toContain('break-inside: avoid')
        ->and($controller)->toContain('Browsershot::html(')
        ->toContain("->format('A4')")
        ->toContain("'Content-Type' => 'application/pdf'");
});

it('replaces in-editor generic print with server backed preview and PDF links', function (): void {
    $heading = file_get_contents(base_path('resources/views/components/v65/document-heading.blade.php'));

    expect($heading)->toContain("'dirty' => false")
        ->toContain("route('documents.print'")
        ->toContain("'format' => 'html'")
        ->toContain("'format' => 'pdf'")
        ->toContain('Çıktı son kaydedilen belgeyi kullanır.')
        ->not->toContain('onclick="window.print()"');

    foreach ([
        'resources/views/livewire/sales/document-editor.blade.php',
        'resources/views/livewire/purchases/document-editor.blade.php',
    ] as $path) {
        expect(file_get_contents(base_path($path)))->toContain(':dirty="$documentDirty"');
    }
});

it('keeps printed draft marked as unposted and does not recompute stored VAT', function (): void {
    $markup = file_get_contents(base_path('resources/views/printing/business-document.blade.php'));

    expect($markup)->toContain("=== 'draft'")
        ->toContain('Taslak · Kesinleşmemiş Belge')
        ->toContain('$document->tax_base')
        ->toContain('$document->discount_amount')
        ->toContain('$document->vat_amount')
        ->not->toContain('CalculateDocumentTotals');
});
