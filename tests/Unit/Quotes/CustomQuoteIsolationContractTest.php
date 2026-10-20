<?php

use App\Enums\DocumentType;

it('reserves custom goods for quotes only without creating stock products or movements', function (): void {
    $migration = file_get_contents(base_path('database/migrations/period/2026_10_20_000001_add_custom_quote_document_lines.php'));
    $save = file_get_contents(base_path('app/Actions/Documents/SaveSalesDocumentDraft.php'));
    $editor = file_get_contents(base_path('app/Livewire/Sales/BaseSalesDocumentEditor.php'));
    $post = file_get_contents(base_path('app/Actions/Documents/PostDocument.php'));
    $reserve = file_get_contents(base_path('app/Actions/Sales/ReserveSalesOrderLines.php'));
    $production = file_get_contents(base_path('app/Actions/Production/CreateProductionOrdersForSalesOrder.php'));

    expect($migration)->toContain("'stock','service','custom'")
        ->toContain("line_kind = 'custom' AND product_id IS NULL")
        ->toContain("reserve_stock = false")
        ->toContain('quote_custom_code_seq')
        ->and($save)->toContain("nextval('quote_custom_code_seq')")
        ->toContain("'product_id' => \$product?->id")
        ->toContain("'location_id' => \$kind === 'stock' ? \$locationId : null")
        ->toContain("'reserve_stock' => \$kind === 'stock' &&")
        ->toContain("'custom_code' => \$code")
        ->and($editor)->toContain('public function addCustomLine(): void')
        ->toContain("DocumentType::Quote")
        ->toContain("in:stock,service,custom")
        ->and($post)->toContain("if (\$line->line_kind !== 'stock')")
        ->and($reserve)->toContain("if (\$line->line_kind !== 'stock')")
        ->and($production)->toContain("if (\$line->line_kind !== 'stock'");
});

it('converts custom quote lines to sales documents without warehouse allocation', function (): void {
    foreach ([
        'app/Actions/Sales/CreateDispatchFromOrder.php',
        'app/Actions/Sales/CreateInvoiceFromOrder.php',
    ] as $path) {
        expect(file_get_contents(base_path($path)))
            ->toContain("if (\$source->line_kind !== 'stock')");
    }

    foreach ([
        'app/Actions/Sales/ConvertQuoteToSalesOrder.php',
        'app/Actions/Sales/CreateQuoteRevision.php',
        'app/Actions/Sales/CreateDispatchFromOrder.php',
        'app/Actions/Sales/CreateInvoiceFromOrder.php',
        'app/Actions/Sales/CreateInvoiceFromDispatches.php',
    ] as $path) {
        expect(file_get_contents(base_path($path)))->toContain("'configuration'");
    }
});

it('scopes quote screenshots to stored custom codes, draft lifecycle and trusted private media', function (): void {
    $quote = file_get_contents(base_path('app/Livewire/Sales/QuoteEditor.php'));
    $controller = file_get_contents(base_path('app/Http/Controllers/QuoteCustomImageController.php'));
    $copy = file_get_contents(base_path('app/Support/Quotes/CopyCustomQuoteImages.php'));

    expect($quote)->toContain('use WithFileUploads;')
        ->toContain('public function uploadCustomImages(')
        ->toContain('quotes.update')
        ->toContain("status === 'draft'")
        ->toContain('quote_custom_')
        ->toContain('uploadCustomImages')
        ->toContain('setCustomCover')
        ->toContain('deleteCustomImage')
        ->and($controller)->toContain('PeriodContext::ensure()')
        ->toContain("can('quotes.view')")
        ->toContain("->whereIn('collection', \$codes)")
        ->toContain("'Cache-Control' => 'private, no-store'")
        ->and($copy)->toContain('Storage::disk($image->disk)->copy(')
        ->and(file_get_contents(base_path('app/Actions/Sales/CreateQuoteRevision.php')))
        ->toContain('CopyCustomQuoteImages::class');
});

it('supports clipboard paste, drag-drop, and custom cover photo on both illustrated print modes', function (): void {
    $form = file_get_contents(base_path('resources/views/livewire/sales/document-editor.blade.php'));
    $toolbar = file_get_contents(base_path('resources/views/components/v65/document-prototype-toolbar.blade.php'));
    $resolver = file_get_contents(base_path('app/Support/Printing/BusinessDocumentImageResolver.php'));
    $print = file_get_contents(base_path('resources/views/printing/business-document.blade.php'));

    expect($toolbar)->toContain('Özel Üretim Ekle')
        ->and($form)->toContain('x-on:paste=')
        ->toContain('x-on:drop.prevent')
        ->toContain("uploadMultiple('customImageUploads'")
        ->toContain('selectedCustomImages()')
        ->toContain('setCustomCover(')
        ->toContain('Teknik Özellikler')
        ->and($resolver)->toContain('forCustomDocument')
        ->toContain('sourceLine.document')
        ->and($print)->toContain("isset(\$customImages[")
        ->toContain('@if($showImages)')
        ->toContain('@if($showPrices)');
});

it('keeps the Fresh quote-schema readiness psql arguments in one continued command', function (): void {
    $release = file_get_contents(base_path('ops/uat-fresh-web-update.sh'));
    $deploy = file_get_contents(base_path('ops/uat-fresh-custom-quote-deploy.sh'));

    expect($release)
        ->toContain('CUSTOM_QUOTE_SCHEMA_READY="$(sudo docker exec "$PG" \\')
        ->toContain('psql -w -U mars_uat_owner -d mars_uat_demo_2026 -XAtq')
        ->toContain("FRESH_CUSTOM_QUOTE_SCHEMA=READY")
        ->not->toContain("psql -w -U mars_uat_owner\n  -d ")
        ->and($deploy)->toContain('CUSTOM_QUOTE_SCHEMA=PASS')
        ->toContain('OFFLINE_RESTORE=PASS');
});



it('opens custom quote detail on demand in the existing native dialog instead of an always visible panel', function (): void {
    $editor = file_get_contents(base_path('app/Livewire/Sales/QuoteEditor.php'));
    $form = file_get_contents(base_path('resources/views/livewire/sales/document-editor.blade.php'));
    $modal = file_get_contents(base_path('resources/views/components/v65/modal.blade.php'));
    $browser = file_get_contents(base_path('resources/js/app.js'));
    $css = file_get_contents(base_path('resources/css/v65-shell.css'));

    expect($editor)->toContain("dispatch('v65-custom-quote-open')")
        ->and($form)->toContain('data-v65-custom-quote-launch')
        ->toContain('<x-v65.modal id="v65-custom-quote-dialog"')
        ->toContain('data-v65-modal-close>İşlemi Tamamla / Kapat')
        ->toContain('id="v65-custom-quote-readonly-dialog"')
        ->toContain('data-v65-modal-open="v65-custom-quote-readonly-dialog"')
        ->toContain('x-on:paste=')
        ->toContain('x-on:drop.prevent')
        ->not->toContain('@if($customSelection)'."\n                        <section class=\"v65-custom-quote-panel\"")
        ->and($modal)->toContain('data-v65-modal')
        ->toContain('wire:ignore.self')
        ->and($browser)->toContain("document.addEventListener('v65-custom-quote-open'")
        ->toContain('dialog.showModal()')
        ->toContain("pasteTarget.focus({ preventScroll: true })")
        ->and($css)->toContain('.v65-modal.v65-custom-quote-dialog');
});
