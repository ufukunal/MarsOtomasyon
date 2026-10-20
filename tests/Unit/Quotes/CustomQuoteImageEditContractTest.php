<?php

use App\Models\Attachment;
use App\Models\QuoteCustomImageEdit;
use App\Support\Quotes\QuoteCustomImageSource;

it('keeps each edited picture as a separate immutable version of a scoped quote attachment', function (): void {
    $migration = file_get_contents(base_path('database/migrations/period/2026_10_20_000002_create_quote_custom_image_edits.php'));
    $model = file_get_contents(base_path('app/Models/QuoteCustomImageEdit.php'));
    $attachment = file_get_contents(base_path('app/Models/Attachment.php'));
    $action = file_get_contents(base_path('app/Actions/Quotes/SaveCustomQuoteImageEdit.php'));

    expect($migration)->toContain("Schema::connection('period')->create('quote_custom_image_edits'")
        ->toContain("->constrained('attachments')->cascadeOnDelete()")
        ->toContain("->jsonb('instructions')")
        ->and($model)->toContain("protected \$table = 'quote_custom_image_edits'")
        ->and($attachment)->toContain('function editedVersions()')
        ->and($action)->toContain('PeriodContext::ensureWritable()')
        ->toContain('SecureUploadValidator::class')
        ->toContain('getimagesize(')
        ->toContain('10000000')
        ->toContain('Str::uuid()')
        ->toContain('DB::connection(')
        ->toContain('->lockForUpdate()')
        ->toContain('->editedVersions()->create(')
        ->toContain('Storage::disk($disk)->delete($path)')
        ->not->toContain('$image->update([');
});

it('saves edited quote files only after confirming ownership, draft status and file validation', function (): void {
    $editor = file_get_contents(base_path('app/Livewire/Sales/QuoteEditor.php'));
    expect($editor)->toContain('function saveCustomImageEdit(')
        ->toContain('requireEditableCustomPhotoCollection()')
        ->toContain("->attachments()->where('collection', \$collection)")
        ->toContain("max:5120")
        ->toContain('SaveCustomQuoteImageEdit::class')
        ->toContain("dispatch('v65-quote-image-edited'")
        ->toContain("status === 'draft'")
        ->toContain('function saveCustomImageAsNew()')
        ->toContain("dispatch('v65-quote-image-added'");
});

it('serves the last edited image privately, allows viewing the original and uses edited covers in PDFs', function (): void {
    $source = file_get_contents(base_path('app/Support/Quotes/QuoteCustomImageSource.php'));
    $controller = file_get_contents(base_path('app/Http/Controllers/QuoteCustomImageController.php'));
    $resolver = file_get_contents(base_path('app/Support/Printing/BusinessDocumentImageResolver.php'));

    expect($source)->toContain("->editedVersions()->latest('id')->first()")
        ->toContain('new \finfo(FILEINFO_MIME_TYPE)')
        ->toContain('Storage::disk($file->disk)->get($file->path)')
        ->and($controller)->toContain("can('quotes.view')")
        ->toContain("->whereIn('collection', \$codes)")
        ->toContain("request()->boolean('original')")
        ->toContain('QuoteCustomImageSource::class')
        ->toContain("'Cache-Control' => 'private, no-store'")
        ->and($resolver)->toContain("app(QuoteCustomImageSource::class)->read(\$image)")
        ->toContain("base64_encode(\$active['bytes'])");
});

it('copies both original and edited files to isolated future quote revisions and cleans edit files on deletion', function (): void {
    $copy = file_get_contents(base_path('app/Support/Quotes/CopyCustomQuoteImages.php'));
    $observer = file_get_contents(base_path('app/Observers/AttachmentObserver.php'));

    expect($copy)->toContain("->editedVersions()->orderBy('id')->get()")
        ->toContain('->editedVersions()->create([')
        ->toContain("Storage::disk(\$version->disk)->copy(")
        ->toContain('Storage::disk($disk)->delete($path)')
        ->and($observer)->toContain('function deleting(')
        ->toContain("setRelation('deletedQuoteImageVersions'")
        ->toContain('function deleted(')
        ->toContain("Storage::disk(\$version->disk)->delete(\$version->path)");
});

it('embeds a scoped V65 canvas editor with crop, rotate, flip, markup, undo and upload', function (): void {
    $form = file_get_contents(base_path('resources/views/livewire/sales/document-editor.blade.php'));
    $component = file_get_contents(base_path('resources/views/components/v65/quote-image-editor.blade.php'));
    $js = file_get_contents(base_path('resources/js/quote-image-editor.js'));
    $entry = file_get_contents(base_path('resources/js/app.js'));

    expect($form)->toContain('<x-v65.quote-image-editor />')
        ->toContain('data-v65-image-select')
        ->toContain("data-image-original")
        ->toContain('v65-custom-quote-dialog')
        ->and($component)->toContain('wire:ignore')
        ->toContain('data-v65-image-canvas')
        ->toContain('data-v65-image-overlay')
        ->toContain('data-v65-image-status')
        ->toContain('data-v65-image-action="crop-apply"')
        ->toContain('data-v65-image-action="save"')
        ->toContain('data-v65-image-action="save-as"')
        ->toContain('data-v65-image-zoom')
        ->and($js)->toContain('canvas.toBlob(')
        ->toContain("component.upload('customImageEditUpload'")
        ->toContain("component.call('saveCustomImageEdit'")
        ->toContain("document.addEventListener('v65-quote-image-edited'")
        ->toContain("component.call(asNew ? 'saveCustomImageAsNew' : 'saveCustomImageEdit'")
        ->toContain('quoteRestore(')
        ->toContain('quoteDrawShape(')
        ->toContain('quoteTransform(')
        ->toContain('quoteResize(')
        ->toContain('quoteZoom(')
        ->and($entry)->toContain("import './quote-image-editor.js'");
});

it('requires both Fresh period migrations and new image editor source checks before web activation', function (): void {
    $deploy = file_get_contents(base_path('ops/uat-fresh-custom-quote-deploy.sh'));
    $web = file_get_contents(base_path('ops/uat-fresh-web-update.sh'));

    expect($deploy)->toContain('2026_10_20_000001_add_custom_quote_document_lines.php')
        ->toContain('2026_10_20_000002_create_quote_custom_image_edits.php')
        ->toContain('OFFLINE_RESTORE=PASS')
        ->toContain('CUSTOM_QUOTE_SCHEMA=PASS')
        ->and($web)->toContain("to_regclass('public.quote_custom_image_edits')")
        ->toContain('SaveCustomQuoteImageEdit.php')
        ->toContain('QuoteCustomImageSource.php');
});


it('allows staging screenshots and editing them before the first quote is saved', function (): void {
    $editor = file_get_contents(base_path('app/Livewire/Sales/QuoteEditor.php'));
    $blade = file_get_contents(base_path('resources/views/livewire/sales/document-editor.blade.php'));
    $js = file_get_contents(base_path('resources/js/quote-image-editor.js'));

    expect($editor)
        ->toContain('public array $pendingCustomImages = []')
        ->toContain('function selectedPendingCustomImages()')
        ->toContain('function requireEditableCustomRowKey()')
        ->toContain("quotes.create")
        ->toContain('function uploadCustomImages(')
        ->toContain("if (\$this->document === null || \$code === '')")
        ->toContain("'original' => \$file, 'edited' => null")
        ->toContain('function savePendingCustomImageEdit(')
        ->toContain('function savePendingCustomImageAsNew()')
        ->toContain('function removePendingCustomImage(')
        ->toContain('function setPendingCustomCover(')
        ->toContain('function removeLine(int $index)')
        ->and($blade)
        ->toContain('$this->selectedPendingCustomImages()')
        ->toContain("@if(!\$document || \$document->status === 'draft')")
        ->toContain('temporaryUrl()')
        ->toContain('data-image-id="staged-')
        ->toContain('setPendingCustomCover(')
        ->toContain('removePendingCustomImage(')
        ->toContain('<x-v65.quote-image-editor />')
        ->not->toContain('Teklifi kaydedip bir görsel eklediğinizde')
        ->and($js)
        ->toContain("staged-(0|[1-9][0-9]*)")
        ->toContain("'savePendingCustomImageEdit'")
        ->toContain("'savePendingCustomImageAsNew'")
        ->toContain("'v65-quote-image-staged'")
        ->toContain('Livewire temporary URLs are signed');
});

it('promotes only approved custom-line images after a successful quote save, preserving originals and versions', function (): void {
    $editor = file_get_contents(base_path('app/Livewire/Sales/QuoteEditor.php'));

    expect($editor)
        ->toContain('parent::save($action)')
        ->toContain("->where('line_kind', 'custom')->get()")
        ->toContain("newKey = 'saved-'")
        ->toContain('function attachPendingCustomImages(')
        ->toContain("->where('collection', \$collection)")
        ->toContain("'attachment_id'")
        ->toContain("\$store->handle(\$this->document, \$pending['original']")
        ->toContain("\$edit->handle(\$original, \$pending['edited'], \$pending['instructions'])")
        ->toContain('if ($this->pendingCustomImages === [])')
        ->toContain('unset($this->pendingCustomImages[$key][$i])')
        ->toContain('Teklif kaydedildi ancak görsellerden bazıları aktarılamadı')
        ->not->toContain('Product::query()->create(')
        ->not->toContain('StockMovement::query()->create(');
});
