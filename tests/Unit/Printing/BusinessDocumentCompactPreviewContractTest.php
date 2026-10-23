<?php

use App\Enums\BusinessDocumentPrintVariant;

it('keeps the established print variants and adds exactly two compact slip outputs', function (): void {
    expect(BusinessDocumentPrintVariant::cases())->toHaveCount(6)
        ->and(BusinessDocumentPrintVariant::SlipPriced->isSlip())->toBeTrue()
        ->and(BusinessDocumentPrintVariant::SlipPriced->showPrices())->toBeTrue()
        ->and(BusinessDocumentPrintVariant::SlipPriced->showImages())->toBeFalse()
        ->and(BusinessDocumentPrintVariant::SlipUnpriced->isSlip())->toBeTrue()
        ->and(BusinessDocumentPrintVariant::SlipUnpriced->showPrices())->toBeFalse()
        ->and(BusinessDocumentPrintVariant::SlipUnpriced->showImages())->toBeFalse()
        ->and(BusinessDocumentPrintVariant::PlainPriced->isSlip())->toBeFalse();
});

it('renders the compact A4 slip with persisted document amounts only when priced', function (): void {
    $slip = file_get_contents(base_path('resources/views/printing/business-document-slip.blade.php'));

    expect($slip)->toContain('@page { size: A4 portrait;')
        ->toContain('width: 210mm')
        ->toContain('min-height: 297mm')
        ->toContain('$document->contact?->title')
        ->toContain('$document->lines as $line')
        ->toContain('@if($showPrices)')
        ->toContain('$document->tax_base')
        ->toContain('$document->vat_amount')
        ->toContain('$document->grand_total')
        ->toContain("bccomp(\$totalDiscountAmount, '0', 4) > 0")
        ->not->toContain('CalculateDocumentTotals');
});

it('opens one A4 preview and reuses authorization for pdf printing and png copying', function (): void {
    $selector = file_get_contents(base_path('resources/views/components/v65/document-print-options.blade.php'));
    $script = file_get_contents(base_path('resources/js/document-print-preview.js'));
    $controller = file_get_contents(base_path('app/Http/Controllers/PrintBusinessDocumentController.php'));
    $routes = file_get_contents(base_path('routes/web.php'));

    expect($selector)->toContain('BusinessDocumentPrintVariant::cases()')
        ->toContain('data-v65-preview-open')
        ->toContain('data-v65-preview-frame')
        ->toContain('data-v65-preview-print')
        ->toContain('data-v65-preview-copy')
        ->toContain('Resim Olarak Kopyala')
        ->toContain("'format' => 'png'")
        ->and($script)->toContain('dialog.showModal()')
        ->toContain('frame.contentWindow.print()')
        ->toContain('navigator.clipboard.write')
        ->toContain('ClipboardItem')
        ->toContain('fitPreview')
        ->and($routes)->toContain("->where('format', 'html|pdf|png')")
        ->and($controller)->toContain('PeriodContext::ensure()')
        ->toContain('auth()->user()?->can($definition[1])')
        ->toContain('BusinessDocumentPrintVariant::tryFrom($variantValue)')
        ->toContain("if (\$format === 'png')")
        ->toContain("'--screenshot='")
        ->toContain("'Content-Type' => 'image/png'")
        ->toContain("'Cache-Control' => 'private, no-store'");
});
