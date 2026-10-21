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
        ->and($controller)->toContain("new Process([")
        ->toContain("'--headless'")
        ->toContain("'--no-pdf-header-footer'")
        ->toContain("str_starts_with(\$pdf, '%PDF-')")
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
        ->toContain('Çıktı Tasarımını Seçin')
        ->toContain("'variant' => \$printVariant->value")
        ->toContain('PDF İndir')
        ->toContain('v65-document-direct-pdf')
        ->toContain('@if($document->document_type !== \\App\\Enums\\DocumentType::Quote)')
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

it('prints five totals with discount, or subtotal VAT and grand total without discount', function (): void {
    $markup = file_get_contents(base_path('resources/views/printing/business-document.blade.php'));
    $controller = file_get_contents(base_path('app/Http/Controllers/PrintBusinessDocumentController.php'));

    preg_match('/<section class="summary"[^>]*>(.*?)<\/section>/s', $markup, $match);
    $summary = $match[0] ?? '';

    expect($summary)->toContain("bccomp(\$totalDiscountAmount, '0', 4) > 0")
        ->toContain('İskontosuz Tutar')
        ->toContain('Toplam İskonto Tutarı')
        ->toContain('İskontolu Tutar')
        ->toContain('KDV Tutarı')
        ->toContain('<dt>Ara Toplam</dt>')
        ->toContain('<dt>Toplam KDV</dt>')
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
    expect(substr_count($none, '<dt>'))->toBe(3)
        ->and($none)->toContain('<dt>Ara Toplam</dt>')
        ->toContain('<dt>Toplam KDV</dt>')
        ->toContain('GENEL TOPLAM')
        ->toContain(\App\Support\Formatting\TrFormatter::money('90.0000'))
        ->toContain(\App\Support\Formatting\TrFormatter::money('18.0000'))
        ->toContain(\App\Support\Formatting\TrFormatter::money('108.0000'))
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
        ->not->toContain('<dt>Ara Toplam</dt>')
        ->not->toContain('<dt>Toplam KDV</dt>');

    // A four-decimal positive discount is still a real discount even if
    // rounded to 0,00 in the printed two-decimal currency format.
    expect(substr_count($render('0.0001'), '<dt>'))->toBe(5);
});

it('verifies PDF runtime before switching UAT to a new image', function (): void {
    $runtime = file_get_contents(base_path('Dockerfile.uat'));
    $release = file_get_contents(base_path('ops/uat-fresh-web-update.sh'));
    $controller = file_get_contents(base_path('app/Http/Controllers/PrintBusinessDocumentController.php'));

    expect($runtime)->toContain('postgresql-client chromium fonts-dejavu-core')
        ->and($release)->toContain("command -v chromium")
        ->toContain("timeout 40s chromium --headless")
        ->toContain("IMAGE_PDF_RENDER=PASS")
        ->toContain('PDF_RUNTIME_FAIL invalid PDF')
        ->and($controller)->toContain("is_executable('/usr/bin/chromium')")
        ->toContain("file_put_contents(\$htmlFile, \$html)")
        ->toContain("str_starts_with(\$pdf, '%PDF-')")
        ->toContain('File::deleteDirectory($workDir)');
});

it('prints the persisted discount percentage above the discount amount in each document line', function (): void {
    $markup = file_get_contents(base_path('resources/views/printing/business-document.blade.php'));

    preg_match('/<td class="numeric line-discount">(.*?)<\/td>/s', $markup, $matches);
    $cell = $matches[0] ?? '';

    expect($cell)->toContain('line-discount-rate')
        ->toContain('line-discount-amount')
        ->toContain('$line->line_discount_rate')
        ->toContain('$line->line_discount_amount');

    $render = static fn (string $rate, string $amount): string => \Illuminate\Support\Facades\Blade::render(
        $cell,
        [
            'line' => (object) [
                'line_discount_rate' => $rate,
                'line_discount_amount' => $amount,
            ],
            'money' => \App\Support\Formatting\TrFormatter::class,
        ],
    );

    $discounted = $render('10.0000', '1520.0000');
    expect($discounted)->toContain('10,00%')
        ->toContain('1.520,00')
        ->and(strpos($discounted, '10,00%'))->toBeLessThan(strpos($discounted, '1.520,00'));

    $noDiscount = $render('0.0000', '0.0000');
    expect($noDiscount)->toContain('0,00%')
        ->toContain('0,00');
});

it('compacts the quote header while retaining customer and document information', function (): void {
    $markup = file_get_contents(base_path('resources/views/printing/business-document.blade.php'));

    expect($markup)->toContain("\$isQuote ? 'v65-quote-print' : ''")
        ->toContain('.v65-quote-print .header')
        ->toContain('.v65-quote-print .document-qr { width: 20mm; flex-basis: 20mm; }')
        ->toContain('.v65-quote-print .details { gap: 9px; margin: 9px 0 7px; }')
        ->toContain('class="document-qr"')
        ->toContain('<section class="details">')
        ->toContain('Müşteri / Cari')
        ->toContain('Belge Bilgileri')
        ->toContain('<th>Durum</th>');

    preg_match_all('/^\s*\.v65-quote-print[^\n]*/m', $markup, $cssLines);
    expect(count($cssLines[0] ?? []))->toBeGreaterThanOrEqual(14);
});

it('omits redundant quote print labels and both address cards without changing other documents', function (): void {
    $markup = file_get_contents(base_path('resources/views/printing/business-document.blade.php'));

    preg_match('/@unless\(\$isQuote\)\s*<span class="print-kind".*?@endunless/s', $markup, $headingMatch);
    preg_match('/@unless\(\$isQuote\)\s*<section class="address-panels".*?<\/section>\s*@endunless/s', $markup, $addressMatch);
    $heading = $headingMatch[0] ?? '';
    $addresses = $addressMatch[0] ?? '';

    expect($heading)->toContain('$printVariant->label()')
        ->toContain('Taslak · Kesinleşmemiş Belge')
        ->and($addresses)->toContain('Fatura Adresi')->toContain('Sevk / Teslimat Adresi');

    $printVariant = new class {
        public function label(): string
        {
            return 'Resimli Fiyatlı';
        }
    };

    $renderHeading = static fn (bool $isQuote): string => \Illuminate\Support\Facades\Blade::render(
        $heading, compact('isQuote', 'printVariant') + ['draft' => true]
    );
    expect(trim($renderHeading(true)))->toBe('')
        ->and($renderHeading(false))->toContain('Resimli Fiyatlı')->toContain('Taslak · Kesinleşmemiş Belge');

    $renderAddresses = static fn (bool $isQuote): string => \Illuminate\Support\Facades\Blade::render(
        $addresses,
        [
            'isQuote' => $isQuote,
            'document' => (object) ['contact' => (object) ['title' => 'Cari Test']],
            'billing' => ['address' => 'Fatura Test Sokak', 'district' => 'Merkez', 'city' => 'İstanbul'],
            'delivery' => [
                'company' => 'Sevk Test', 'address' => 'Sevk Test Sokak',
                'district' => 'Merkez', 'city' => 'İstanbul', 'person' => '', 'phone' => '',
            ],
        ],
    );
    expect(trim($renderAddresses(true)))->toBe('')
        ->and($renderAddresses(false))->toContain('Fatura Adresi')->toContain('Sevk / Teslimat Adresi');
});

it('hides signature boxes and creation metadata only in quote print and PDF', function (): void {
    $markup = file_get_contents(base_path('resources/views/printing/business-document.blade.php'));
    preg_match('/@unless\(\$isQuote\)\s*<section class="signatures".*?<\/footer>\s*@endunless/s', $markup, $matches);
    $block = $matches[0] ?? '';

    expect($block)->toContain('Düzenleyen')
        ->toContain('Kontrol')
        ->toContain('Onay / Kaşe')
        ->toContain('Teslim Eden')
        ->toContain('Belge kayıt numarası')
        ->toContain('$generatedAt->format(')
        ->and($markup)->toContain('<th>Durum</th>')
        ->toContain('class="document-qr"');

    $render = static fn (bool $isQuote): string => \Illuminate\Support\Facades\Blade::render(
        $block,
        [
            'isQuote' => $isQuote,
            'document' => (object) [
                'id' => 1227,
                'document_type' => \App\Enums\DocumentType::SalesOrder,
            ],
            'generatedAt' => new \DateTimeImmutable('2026-10-21 22:00:00'),
        ],
    );

    expect(trim($render(true)))->toBe('')
        ->and($render(false))->toContain('Düzenleyen')
        ->toContain('Kontrol')
        ->toContain('Onay / Kaşe')
        ->toContain('Belge kayıt numarası: 1227');
});
