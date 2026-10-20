<?php

use App\Enums\BusinessDocumentPrintVariant;

it('exposes exactly four price/image combinations with correct flags', function (): void {
    expect(array_map(fn (BusinessDocumentPrintVariant $style) => $style->value, BusinessDocumentPrintVariant::cases()))
        ->toBe(['plain_priced', 'image_priced', 'plain_unpriced', 'image_unpriced']);

    foreach (BusinessDocumentPrintVariant::cases() as $style) {
        expect($style->showImages())->toBe(str_starts_with($style->value, 'image_'))
            ->and($style->showPrices())->toBe(str_ends_with($style->value, '_priced'));
    }
});

it('selects print layout only after source-document permission check and disallows unknown modes', function (): void {
    $controller = file_get_contents(base_path('app/Http/Controllers/PrintBusinessDocumentController.php'));
    $selector = file_get_contents(base_path('resources/views/components/v65/document-print-options.blade.php'));
    $generic = file_get_contents(base_path('resources/views/components/v65/document-heading.blade.php'));
    $prototype = file_get_contents(base_path('resources/views/components/v65/document-prototype-heading.blade.php'));

    expect($controller)->toContain('auth()->user()?->can($definition[1])')
        ->toContain('BusinessDocumentPrintVariant::tryFrom($variantValue)')
        ->toContain('is_string($variantValue)')
        ->toContain('abort_unless($printVariant !== null, 422')
        ->toContain('$printVariant->showPrices()')
        ->toContain('$printVariant->showImages()')
        ->toContain("'productImages' => \$productImages")
        ->and($selector)->toContain('BusinessDocumentPrintVariant::cases()')
        ->toContain('variant')
        ->toContain('Önizle')
        ->toContain('PDF İndir')
        ->toContain('v65-document-print-actions')
        ->toContain('Çıktı Tasarımını Seçin')
        ->toContain("'variant' => \$printVariant->value")
        ->toContain('v65-document-direct-pdf')
        ->toContain('@if($document->document_type !== \\App\\Enums\\DocumentType::Quote)')
        ->and($generic)->toContain('x-v65.document-print-options')
        ->and($prototype)->toContain('x-v65.document-print-options');
});

it('hides every monetary figure in both unpriced layouts and preserves stored priced totals', function (): void {
    $view = file_get_contents(base_path('resources/views/printing/business-document.blade.php'));

    expect($view)->toContain('@if($showPrices)')
        ->toContain('@if($showImages)')
        ->toContain('Birim Fiyat')
        ->toContain('İskonto')
        ->toContain('KDV %')
        ->toContain('Net Tutar')
        ->toContain('$document->grand_total')
        ->toContain('Toplam İskonto Tutarı')
        ->toContain('İskontolu Tutar')
        ->toContain('Toplam Kalem:')
        ->toContain('$document->lines as $line')
        ->toContain('Görsel yok')
        ->toContain('$productImages[$line->product_id]')
        ->toContain('Fatura Adresi')
        ->toContain('Sevk / Teslimat Adresi')
        ->toContain('OpaqueQrSvg::render($documentQrToken)')
        ->not->toContain('CalculateDocumentTotals');
});

it('embeds only verified, bounded, locally owned raster product images for PDF', function (): void {
    $resolver = file_get_contents(base_path('app/Support/Printing/BusinessDocumentImageResolver.php'));

    expect($resolver)->toContain("loadMissing('lines.product.attachments')")
        ->toContain("can('view', \$product)")
        ->toContain("config('product_images.fallback'")
        ->toContain("['image/jpeg', 'image/png', 'image/webp']")
        ->toContain('1536000')
        ->toContain('$maxBytes = 12 * 1024 * 1024')
        ->toContain('Storage::disk($attachment->disk)->get($attachment->path)')
        ->toContain('new \finfo(FILEINFO_MIME_TYPE)')
        ->toContain('base64_encode($bytes)')
        ->not->toContain('file_get_contents($attachment->path)');
});
