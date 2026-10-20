<?php

namespace App\Livewire\Sales;

use App\Actions\Sales\ApproveQuote;
use App\Actions\Sales\ConvertQuoteToSalesOrder;
use App\Actions\Sales\CreateQuoteRevision;
use App\Actions\Sales\IssueProforma;
use App\Actions\Sales\SendQuoteToCustomerReview;
use App\Actions\Sales\SubmitQuoteForInternalApproval;
use App\Enums\DocumentType;
use App\Actions\Attachments\StoreAttachment;
use App\Actions\Quotes\SaveCustomQuoteImageEdit;
use App\Actions\Documents\SaveSalesDocumentDraft;
use App\Models\Attachment;
use App\Models\Period\Document;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\WithFileUploads;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class QuoteEditor extends BaseSalesDocumentEditor
{
    use WithFileUploads;

    public string $customSelectedRowKey = '';

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $customImageUploads = [];

    /** Current edited upload; stored separately from the immutable original image. */
    public $customImageEditUpload = null;

    /** Original clean background for an independent copy. */
    public $customImageBaseUpload = null;

    /**
     * Unsaved quote images belong to a UI row key, never to a stock product.
     * Livewire holds the temporary uploads; original and edited are separate.
     *
     * @var array<string,list<array{original:TemporaryUploadedFile,edited:?TemporaryUploadedFile,instructions:array<int,string>,attachment_id?:int}>>
     */
    public array $pendingCustomImages = [];

    /** @return list<array{original:TemporaryUploadedFile,edited:?TemporaryUploadedFile,instructions:array<int,string>,attachment_id?:int}> */
    public function selectedPendingCustomImages(): array
    {
        $key = (string) ($this->selectedCustomRow()['line']['row_key'] ?? '');

        return $this->pendingCustomImages[$key] ?? [];
    }

    private function requireEditableCustomRowKey(): string
    {
        abort_unless($this->document === null || $this->document->status === 'draft', 409);
        abort_unless(auth()->user()?->can($this->document ? 'quotes.update' : 'quotes.create'), 403);
        $selected = $this->selectedCustomRow();
        abort_unless($selected !== null && ($selected['line']['line_kind'] ?? '') === 'custom', 404);
        $key = (string) ($selected['line']['row_key'] ?? '');
        abort_unless($key !== '', 422);

        return $key;
    }

    private function validatePendingImageCount(string $key, int $additional): void
    {
        $count = count($this->pendingCustomImages[$key] ?? []);
        if ($count + $additional > 10) {
            throw ValidationException::withMessages([
                'customImageUploads' => 'Bir özel üretim kalemine en fazla 10 görsel eklenebilir.',
            ]);
        }
    }

    /** @return array{index:int,line:array<string,mixed>}|null */
    public function selectedCustomRow(): ?array
    {
        $first = null;
        foreach ($this->lines as $index => $line) {
            if (($line['line_kind'] ?? '') !== 'custom') {
                continue;
            }
            $row = ['index' => $index, 'line' => $line];
            $first ??= $row;
            if (($line['row_key'] ?? '') === $this->customSelectedRowKey) {
                return $row;
            }
        }
        return $first;
    }

    public function selectCustomRow(string $rowKey): void
    {
        if (collect($this->lines)->contains(
            static fn (array $line): bool => ($line['line_kind'] ?? '') === 'custom'
                && ($line['row_key'] ?? '') === $rowKey
        )) {
            $this->customSelectedRowKey = $rowKey;
            // Open only after Livewire has rendered the newly selected row.
            $this->dispatch('v65-custom-quote-open');
        }
    }

    /** @return Collection<int, Attachment> */
    public function selectedCustomImages(): Collection
    {
        $code = (string) ($this->selectedCustomRow()['line']['configuration']['custom_code'] ?? '');
        if (! $this->document || $code === '') {
            return new Collection;
        }
        return $this->document->attachments()
            ->where('collection', 'quote_custom_'.$code)
            ->orderBy('sort_order')->get();
    }

    private function requireEditableCustomPhotoCollection(): string
    {
        abort_unless(auth()->user()?->can('quotes.update'), 403);
        abort_unless($this->document !== null, 409, 'Önce teklifi kaydedin.');
        $quote = Document::query()->findOrFail($this->document->id);
        abort_unless($quote->document_type === DocumentType::Quote && $quote->status === 'draft', 409);

        $code = (string) ($this->selectedCustomRow()['line']['configuration']['custom_code'] ?? '');
        abort_unless(preg_match('/^OZ-[0-9]{4}-[0-9]{6}$/D', $code) === 1, 422);
        $owned = $quote->lines()->where('line_kind', 'custom')->get()
            ->contains(static fn ($line): bool => ($line->configuration['custom_code'] ?? '') === $code);
        abort_unless($owned, 404);

        return 'quote_custom_'.$code;
    }

    public function uploadCustomImages(StoreAttachment $store): void
    {
        $rowKey = $this->requireEditableCustomRowKey();
        $this->validate([
            'customImageUploads' => ['required', 'array', 'min:1', 'max:8'],
            'customImageUploads.*' => ['required', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
        ]);

        // Newly entered special goods have no saved custom code yet.
        // Keep their originals in Livewire temporary storage until a valid quote save.
        $code = (string) ($this->selectedCustomRow()['line']['configuration']['custom_code'] ?? '');
        if ($this->document === null || $code === '') {
            $this->validatePendingImageCount($rowKey, count($this->customImageUploads));
            foreach ($this->customImageUploads as $file) {
                $this->pendingCustomImages[$rowKey][] = [
                    'original' => $file, 'edited' => null, 'instructions' => [],
                ];
            }
            $this->reset('customImageUploads');
            $this->dispatch('v65-quote-image-staged');

            return;
        }

        $collection = $this->requireEditableCustomPhotoCollection();
        $count = $this->document->attachments()->where('collection', $collection)->count();
        if ($count + count($this->customImageUploads) > 10) {
            throw ValidationException::withMessages([
                'customImageUploads' => 'Bir özel üretim kalemine en fazla 10 görsel eklenebilir.',
            ]);
        }
        foreach ($this->customImageUploads as $index => $file) {
            $store->handle($this->document, $file, $collection, $count + $index);
        }
        $this->reset('customImageUploads');
        $this->dispatch('v65-quote-image-staged');
    }

    public function removePendingCustomImage(int $index): void
    {
        $key = $this->requireEditableCustomRowKey();
        abort_unless(isset($this->pendingCustomImages[$key][$index]), 404);
        abort_unless(! isset($this->pendingCustomImages[$key][$index]['attachment_id']), 409);
        array_splice($this->pendingCustomImages[$key], $index, 1);
        if ($this->pendingCustomImages[$key] === []) {
            unset($this->pendingCustomImages[$key]);
        }
    }

    public function setPendingCustomCover(int $index): void
    {
        $key = $this->requireEditableCustomRowKey();
        abort_unless(isset($this->pendingCustomImages[$key][$index]), 404);
        $item = $this->pendingCustomImages[$key][$index];
        array_splice($this->pendingCustomImages[$key], $index, 1);
        array_unshift($this->pendingCustomImages[$key], $item);
    }

    public function savePendingCustomImageEdit(int $index, array $instructions = []): void
    {
        $key = $this->requireEditableCustomRowKey();
        abort_unless(isset($this->pendingCustomImages[$key][$index]), 404);
        $this->validate([
            'customImageEditUpload' => [
                'required', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120',
            ],
        ]);
        $this->pendingCustomImages[$key][$index]['edited'] = $this->customImageEditUpload;
        $scene = app(SaveCustomQuoteImageEdit::class)->normalizeInstructions($instructions);
        if (($scene['version'] ?? null) === 2) {
            $scene['base_index'] = -1; // The unsaved background is always its own original.
        }
        $this->pendingCustomImages[$key][$index]['instructions'] = $scene;
        $this->reset('customImageEditUpload');
        $this->dispatch('v65-quote-image-edited', attachmentId: 'staged-'.$index);
    }

    public function savePendingCustomImageAsNew(array $instructions = []): void
    {
        $key = $this->requireEditableCustomRowKey();
        $this->validate([
            'customImageEditUpload' => ['required', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
            'customImageBaseUpload' => ['required', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
        ]);
        $this->validatePendingImageCount($key, 1);
        $scene = app(SaveCustomQuoteImageEdit::class)->normalizeInstructions($instructions);
        $scene['base_index'] = -1;
        $scene['ops'] = [];
        $this->pendingCustomImages[$key][] = [
            'original' => $this->customImageBaseUpload,
            'edited' => $this->customImageEditUpload,
            'instructions' => $scene,
        ];
        $this->reset('customImageEditUpload', 'customImageBaseUpload');
        $this->dispatch('v65-quote-image-added');
    }
    public function updated(string $property): void
    {
        parent::updated($property);

        if (preg_match('/^lines\.(\d+)\.line_kind$/D', $property, $matches)) {
            $row = $this->lines[(int) $matches[1]] ?? null;
            if (is_array($row) && ($row['line_kind'] ?? '') !== 'custom') {
                unset($this->pendingCustomImages[(string) ($row['row_key'] ?? '')]);
            }
        }
    }

    public function removeLine(int $index): void
    {
        $key = (string) ($this->lines[$index]['row_key'] ?? '');
        parent::removeLine($index);
        if ($key !== '') {
            unset($this->pendingCustomImages[$key]);
        }
    }

    public function save(SaveSalesDocumentDraft $action): void
    {
        $before = collect($this->lines)
            ->filter(static fn (array $line): bool => ($line['line_kind'] ?? '') === 'custom')
            ->pluck('row_key')->values()->all();

        // Do not allow abandoned images to follow a deleted/converted UI row.
        $active = array_fill_keys($before, true);
        foreach (array_keys($this->pendingCustomImages) as $key) {
            if (! isset($active[$key])) {
                unset($this->pendingCustomImages[$key]);
            }
        }

        parent::save($action);

        if ($this->pendingCustomImages === []) {
            return;
        }

        $after = $this->document->lines()->where('line_kind', 'custom')->get();
        if (count($before) !== $after->count()) {
            throw ValidationException::withMessages([
                'customImageUploads' => 'Özel üretim satırları değişti. Fotoğraflar aktarılmadı; yeniden deneyin.',
            ]);
        }

        // Remap the temporary row keys to persistent line identities after save.
        foreach ($before as $i => $oldKey) {
            $newKey = 'saved-'.$after[$i]->id;
            if (isset($this->pendingCustomImages[$oldKey]) && $oldKey !== $newKey) {
                $this->pendingCustomImages[$newKey] = array_merge(
                    $this->pendingCustomImages[$newKey] ?? [],
                    $this->pendingCustomImages[$oldKey]
                );
                unset($this->pendingCustomImages[$oldKey]);
            }
        }
        try {
            $this->attachPendingCustomImages(app(StoreAttachment::class), app(SaveCustomQuoteImageEdit::class));
        } catch (\Throwable $error) {
            throw ValidationException::withMessages([
                'customImageUploads' => 'Teklif kaydedildi ancak görsellerden bazıları aktarılamadı. '
                    .'Teklifi tekrar kaydederek kalan görselleri aktarın. '.$error->getMessage(),
            ]);
        }
    }

    private function attachPendingCustomImages(StoreAttachment $store, SaveCustomQuoteImageEdit $edit): void
    {
        foreach ($this->document->lines()->where('line_kind', 'custom')->get() as $line) {
            $key = 'saved-'.$line->id;
            if (empty($this->pendingCustomImages[$key])) {
                continue;
            }
            $code = (string) ($line->configuration['custom_code'] ?? '');
            abort_unless(preg_match('/^OZ-[0-9]{4}-[0-9]{6}$/D', $code), 422);
            $collection = 'quote_custom_'.$code;

            foreach ($this->pendingCustomImages[$key] as $i => $pending) {
                if (! isset($pending['attachment_id'])) {
                    $sortOrder = $this->document->attachments()->where('collection', $collection)->count();
                    $original = $store->handle($this->document, $pending['original'], $collection, $sortOrder);
                    $this->pendingCustomImages[$key][$i]['attachment_id'] = $original->id;
                } else {
                    $original = $this->document->attachments()->where('collection', $collection)
                        ->findOrFail($pending['attachment_id']);
                }

                if ($pending['edited'] !== null) {
                    $edit->handle($original, $pending['edited'], $pending['instructions']);
                }
                unset($this->pendingCustomImages[$key][$i]);
            }
            unset($this->pendingCustomImages[$key]);
        }
    }

    /** Commit an edited image as a new immutable version; the original attachment is untouched. */
    public function saveCustomImageEdit(int $attachmentId, array $instructions = []): void
    {
        $collection = $this->requireEditableCustomPhotoCollection();
        $this->validate([
            'customImageEditUpload' => [
                'required', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120',
            ],
        ]);

        $image = $this->document->attachments()->where('collection', $collection)
            ->findOrFail($attachmentId);

        app(SaveCustomQuoteImageEdit::class)->handle(
            $image, $this->customImageEditUpload, $instructions
        );
        $this->reset('customImageEditUpload');
        $this->dispatch('v65-quote-image-edited', attachmentId: $attachmentId);
    }

    /** "Farklı Kaydet" is a new independent original, never a replacement. */
    public function saveCustomImageAsNew(array $instructions = []): void
    {
        $collection = $this->requireEditableCustomPhotoCollection();
        $this->validate([
            'customImageEditUpload' => ['required', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
            'customImageBaseUpload' => ['required', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
        ]);
        $count = $this->document->attachments()->where('collection', $collection)->count();
        if ($count >= 10) {
            throw ValidationException::withMessages(['customImageEditUpload' => 'En fazla 10 görsel eklenebilir.']);
        }
        foreach ([$this->customImageEditUpload, $this->customImageBaseUpload] as $upload) {
            $dims = @getimagesize($upload->getRealPath());
            if (! is_array($dims) || $dims[0] < 1 || $dims[1] < 1
                || $dims[0] > 3200 || $dims[1] > 3200 || $dims[0] * $dims[1] > 10000000) {
                throw ValidationException::withMessages(['customImageEditUpload' => 'Görsel ölçüleri geçersiz.']);
            }
        }
        $scene = app(SaveCustomQuoteImageEdit::class)->normalizeInstructions($instructions);
        $scene['base_index'] = -1;
        $scene['ops'] = [];
        $original = app(StoreAttachment::class)->handle(
            $this->document, $this->customImageBaseUpload, $collection, $count
        );
        try {
            app(SaveCustomQuoteImageEdit::class)->handle($original, $this->customImageEditUpload, $scene);
        } catch (\Throwable $e) {
            $original->delete();
            throw $e;
        }
        $this->reset('customImageEditUpload', 'customImageBaseUpload');
        $this->dispatch('v65-quote-image-added');
    }
    public function setCustomCover(int $attachmentId): void
    {
        $collection = $this->requireEditableCustomPhotoCollection();
        DB::connection('period')->transaction(function () use ($collection, $attachmentId): void {
            $images = $this->document->attachments()->where('collection', $collection)
                ->orderBy('sort_order')->lockForUpdate()->get();
            abort_unless($images->contains('id', $attachmentId), 404);
            foreach ($images->sortBy(static fn ($item): int => $item->id === $attachmentId ? -1 : $item->sort_order)->values() as $index => $image) {
                $image->sort_order = $index;
                $image->save();
            }
        });
    }

    public function deleteCustomImage(int $attachmentId): void
    {
        $collection = $this->requireEditableCustomPhotoCollection();
        $image = $this->document->attachments()->where('collection', $collection)
            ->findOrFail($attachmentId);
        $image->delete();
    }



    /**
     * All revisions of the open quote are shown inside its own editor,
     * without exposing history for unrelated quote numbers.
     *
     * @return Collection<int, Document>
     */
    public function quoteRevisions(): Collection
    {
        if ($this->document === null || $this->document->number === null) {
            return new Collection;
        }

        return Document::query()
            ->where('document_type', DocumentType::Quote->value)
            ->where('number', $this->document->number)
            ->orderByDesc('revision_no')
            ->orderByDesc('id')
            ->get();
    }

    protected function documentType(): DocumentType
    {
        return DocumentType::Quote;
    }

    protected function permissionPrefix(): string
    {
        return 'quotes';
    }

    protected function pageTitle(): string
    {
        return $this->document ? 'Teklif' : 'Yeni Teklif';
    }

    protected function extraMutationNames(): array
    {
        return ['internalReview', 'customerReview', 'approve', 'revision', 'order', 'proforma'];
    }

    public function internalReview(SubmitQuoteForInternalApproval $action): void
    {
        $this->requireSavedDraft();
        abort_unless($this->document !== null, 422);
        $this->loadDocument($action->handle($this->document, $this->mutationKey('internalReview'))->load('lines'));
        $this->completeMutation('internalReview');
    }

    public function customerReview(SendQuoteToCustomerReview $action): void
    {
        $this->requireSavedDraft();
        abort_unless($this->document !== null, 422);
        $this->loadDocument($action->handle($this->document, $this->mutationKey('customerReview'))->load('lines'));
        $this->completeMutation('customerReview');
    }

    public function approve(ApproveQuote $action): void
    {
        abort_unless($this->document !== null, 422);
        $this->loadDocument($action->handle($this->document, $this->mutationKey('approve'))->load('lines'));
        $this->completeMutation('approve');
    }

    public function revision(CreateQuoteRevision $action): mixed
    {
        abort_unless($this->document !== null, 422);
        $revision = $action->handle($this->document, $this->mutationKey('revision'));
        $this->completeMutation('revision');

        return $this->redirectRoute('sales.quotes.edit', ['id' => $revision->id], navigate: false);
    }

    public function toOrder(ConvertQuoteToSalesOrder $action): mixed
    {
        abort_unless($this->document !== null, 422);
        $order = $action->handle($this->document, $this->mutationKey('order'));
        $this->completeMutation('order');

        return $this->redirectRoute('sales.orders.edit', ['id' => $order->id], navigate: false);
    }

    public function issueProforma(IssueProforma $action): mixed
    {
        abort_unless($this->document !== null, 422);
        $proforma = $action->handle($this->document, $this->documentDate, $this->mutationKey('proforma'));
        $this->completeMutation('proforma');

        return $this->redirectRoute('sales.proformas.show', ['id' => $proforma->id], navigate: false);
    }
}
