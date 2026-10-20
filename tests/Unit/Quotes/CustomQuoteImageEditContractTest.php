<?php

use App\Actions\Quotes\SaveCustomQuoteImageEdit;
use App\Models\Attachment;
use App\Models\QuoteCustomImageEdit;
use App\Support\Quotes\QuoteCustomImageSource;
use Illuminate\Validation\ValidationException;

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
        ->toContain('function saveCustomImageAsNew(')
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
        ->toContain('data-v65-image-tool="select"')
        ->toContain('data-v65-image-layers')
        ->toContain('data-v65-image-properties')
        ->and($js)->toContain('canvas.toBlob(')
        ->toContain("await upload('customImageEditUpload'")
        ->toContain("await component.call(method,...args)")
        ->toContain("document.addEventListener('v65-quote-image-edited'")
        ->toContain("asNew?'saveCustomImageAsNew':'saveCustomImageEdit'")
        ->toContain('quoteRestore(')
        ->toContain('quoteDrawShape(')
        ->toContain('quoteTransform(')
        ->toContain('quoteResize(')
        ->toContain('quoteZoom(')
        ->toContain('quoteHit(')
        ->toContain('quoteScaleObject(')
        ->toContain('quoteRender(')
        ->toContain('quoteScene(')
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
        ->toContain('function savePendingCustomImageAsNew(')
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


it('preserves selectable annotation scenes with bounded and validated geometry', function (): void {
    $action = new SaveCustomQuoteImageEdit;
    $scene = [
        'version' => 2,
        'base_index' => -1,
        'ops' => [['kind' => 'rotate-right'], ['kind' => 'resize', 'width' => 640]],
        'objects' => [
            ['id' => 1, 'type' => 'text', 'x' => 25, 'y' => 30, 'text' => 'Ölçü kontrolü',
                'size' => 24, 'bold' => true, 'color' => '#112233', 'width' => 3],
            ['id' => 2, 'type' => 'arrow', 'x' => 10, 'y' => 20, 'w' => 70, 'h' => 40,
                'color' => '#ff0044', 'width' => 4],
            ['id' => 3, 'type' => 'path', 'color' => '#445566', 'width' => 2,
                'points' => [['x' => 1, 'y' => 2], ['x' => 3, 'y' => 4]]],
        ],
    ];

    $saved = $action->normalizeInstructions($scene);
    expect($saved['version'])->toBe(2)
        ->and($saved['ops'])->toHaveCount(2)
        ->and($saved['objects'])->toHaveCount(3)
        ->and($saved['objects'][0]['text'])->toBe('Ölçü kontrolü')
        ->and($saved['objects'][2]['points'])->toHaveCount(2)
        ->and($action->normalizeInstructions(['crop', 'rotate-right']))->toBe(['crop', 'rotate-right']);
});

it('rejects unsafe or oversized editable annotation payloads', function (): void {
    $action = new SaveCustomQuoteImageEdit;
    $valid = [
        'version' => 2, 'base_index' => -1, 'ops' => [],
        'objects' => [['id' => 1, 'type' => 'text', 'x' => 10, 'y' => 20,
            'text' => 'Güvenli', 'size' => 24, 'color' => '#123abc', 'width' => 3]],
    ];

    $unsafe = $valid;
    $unsafe['objects'][0]['color'] = 'javascript:alert(1)';
    expect(fn () => $action->normalizeInstructions($unsafe))->toThrow(ValidationException::class);

    $overflow = $valid;
    $overflow['objects'] = array_map(
        static fn (int $id): array => [...$valid['objects'][0], 'id' => $id],
        range(1, 61),
    );
    expect(fn () => $action->normalizeInstructions($overflow))->toThrow(ValidationException::class);

    $outside = $valid;
    $outside['base_index'] = 999;
    expect(fn () => $action->normalizeInstructions($outside))->toThrow(ValidationException::class);
});

it('retains editable scenes across staged images, saved versions and quote revisions', function (): void {
    $form = file_get_contents(base_path('resources/views/livewire/sales/document-editor.blade.php'));
    $editor = file_get_contents(base_path('app/Livewire/Sales/QuoteEditor.php'));
    $controller = file_get_contents(base_path('app/Http/Controllers/QuoteCustomImageController.php'));
    $source = file_get_contents(base_path('app/Support/Quotes/QuoteCustomImageSource.php'));
    $copy = file_get_contents(base_path('app/Support/Quotes/CopyCustomQuoteImages.php'));
    $js = file_get_contents(base_path('resources/js/quote-image-editor.js'));

    expect($form)->toContain('data-image-scene')
        ->toContain('data-image-base')
        ->toContain('data-image-base-index')
        ->and($editor)->toContain('customImageBaseUpload')
        ->toContain('normalizeInstructions($instructions)')
        ->and($controller)->toContain("request()->boolean('base')")
        ->toContain('readVersion($photo, $index)')
        ->and($source)->toContain('function readVersion(')
        ->and($copy)->toContain("'instructions' => \$version->instructions")
        ->and($js)->toContain('quoteDefaultScene(')
        ->toContain("const scene=quoteScene(s,asNew)")
        ->toContain("await upload('customImageBaseUpload',base)")
        ->toContain("function quoteReplay(s,scene)");
});
