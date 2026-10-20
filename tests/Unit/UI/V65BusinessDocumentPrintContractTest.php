<?php

it('restricts all nine corporate document outputs to authenticated period and role', function (): void {
    $routes = file_get_contents(base_path('routes/web.php'));
    $controller = file_get_contents(base_path('app/Http/Controllers/PrintBusinessDocumentController.php'));

    expect($routes)->toContain("->name('documents.print')")
        ->toContain("->where('format', 'html|pdf')")
        ->and($controller)->toContain('PeriodContext::ensure()')
        ->toContain('->findOrFail($document)')
        ->toContain('auth()->user()?->can($definition[1])')
        ->toContain("abort_unless(in_array(\$format, ['html', 'pdf'], true), 404)")
        ->toContain('Cache-Control')
        ->toContain('private, no-store')
        ->toContain('X-Content-Type-Options')
        ->toContain("'sourceReferences' => \$sourceReferences")
        ->toContain('if ($permission === null || ! auth()->user()?->can($permission))');

    foreach ([
        'Quote', 'SalesOrder', 'Dispatch', 'SalesInvoice',
        'PurchaseQuote', 'PurchaseOrder', 'PurchaseDispatch', 'GoodsReceipt', 'SupplierInvoice',
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
        ->toContain('display: table-header-group')
        ->toContain('break-inside: avoid')
        ->and($controller)->toContain('Browsershot::html(')
        ->toContain("->format('A4')")
        ->toContain("'Content-Type' => 'application/pdf'");
});

it('replaces in-editor generic print with server backed preview and PDF links', function (): void {
    $heading = file_get_contents(base_path('resources/views/components/v65/document-heading.blade.php'));

    $prototype = file_get_contents(base_path('resources/views/components/v65/document-prototype-heading.blade.php'));
    $selector = file_get_contents(base_path('resources/views/components/v65/document-print-options.blade.php'));

    expect($heading)->toContain("'dirty' => false")
        ->toContain('<x-v65.document-print-options :document="$document" />')
        ->and($prototype)->toContain('<x-v65.document-print-options :document="$document" />')
        ->and($selector)->toContain("route('documents.print'")
        ->toContain("'format' => 'html'")
        ->toContain("'format' => 'pdf'")
        ->toContain('v65-document-direct-pdf')
        ->toContain('Düz Fiyatlı belgeyi doğrudan PDF indir')
        ->toContain('BusinessDocumentPrintVariant::PlainPriced->value')
        ->toContain('Çıktı yalnız kaydedilmiş belgeyi kullanır.')
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
        ->toContain('$document->vat_amount')
        ->not->toContain('CalculateDocumentTotals');
});

it('prints five totals with any line/document discount, or only VAT and grand total without discount', function (): void {
    $markup = file_get_contents(base_path('resources/views/printing/business-document.blade.php'));
    $controller = file_get_contents(base_path('app/Http/Controllers/PrintBusinessDocumentController.php'));

    preg_match('/<section class="summary"[^>]*>(.*?)<\/section>/s', $markup, $match);
    $summary = $match[0] ?? '';

    expect($summary)->toContain("bccomp(\$totalDiscountAmount, '0', 4) > 0")
        ->toContain('İskontosuz Tutar')
        ->toContain('Toplam İskonto Tutarı')
        ->toContain('İskontolu Tutar')
        ->toContain('KDV Tutarı')
        ->toContain('Toplam KDV')
        ->toContain('GENEL TOPLAM')
        ->toContain('$document->vat_amount')
        ->toContain('$document->grand_total')
        ->not->toContain('Yuvarlama Farkı')
        ->not->toContain('Satır Netleri')
        ->and($controller)->toContain('bcadd($lineDiscountAmount, (string) $record->discount_amount, 4)')
        ->toContain("'totalDiscountAmount' => \$totalDiscountAmount")
        ->toContain("'grossTotal' => \$grossTotal");

    $render = static fn (string $discount): string => \Illuminate\Support\Facades\Blade::render(
        $summary,
        [
            'totalDiscountAmount' => $discount,
            'grossTotal' => '100.0000',
            'document' => (object) [
                'tax_base' => '90.0000',
                'vat_amount' => '18.0000',
                'grand_total' => '108.0000',
            ],
            'money' => \App\Support\Formatting\TrFormatter::class,
            'currency' => 'TRY',
        ],
    );

    $none = $render('0.0000');
    expect(substr_count($none, '<dt>'))->toBe(2)
        ->and($none)->toContain('Toplam KDV')->toContain('GENEL TOPLAM')
        ->not->toContain('İskontosuz Tutar')
        ->not->toContain('Toplam İskonto Tutarı')
        ->not->toContain('İskontolu Tutar')
        ->not->toContain('KDV Tutarı');

    $discounted = $render('10.0000');
    expect(substr_count($discounted, '<dt>'))->toBe(5)
        ->and($discounted)->toContain('İskontosuz Tutar')
        ->toContain('Toplam İskonto Tutarı')
        ->toContain('İskontolu Tutar')
        ->toContain('KDV Tutarı')
        ->toContain('GENEL TOPLAM')
        ->not->toContain('<dt>Toplam KDV</dt>');

    // A four-decimal positive discount is still a real discount even if
    // rounded to 0,00 in the printed two-decimal currency format.
    expect(substr_count($render('0.0001'), '<dt>'))->toBe(5);
});
