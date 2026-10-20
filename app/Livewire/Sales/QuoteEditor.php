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
use App\Models\Attachment;
use App\Models\Period\Document;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\WithFileUploads;

class QuoteEditor extends BaseSalesDocumentEditor
{
    use WithFileUploads;

    public string $customSelectedRowKey = '';

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $customImageUploads = [];

    /** Current edited upload; stored separately from the immutable original image. */
    public $customImageEditUpload = null;

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
        $collection = $this->requireEditableCustomPhotoCollection();
        $this->validate([
            'customImageUploads' => ['required', 'array', 'min:1', 'max:8'],
            'customImageUploads.*' => ['required', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
        ]);
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
    public function saveCustomImageAsNew(): void
    {
        $collection = $this->requireEditableCustomPhotoCollection();
        $this->validate([
            'customImageEditUpload' => ['required', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
        ]);
        $count = $this->document->attachments()->where('collection', $collection)->count();
        if ($count >= 10) {
            throw ValidationException::withMessages([
                'customImageEditUpload' => 'En fazla 10 görsel eklenebilir.',
            ]);
        }
        $dims = @getimagesize($this->customImageEditUpload->getRealPath());
        if (! is_array($dims) || $dims[0] < 1 || $dims[1] < 1
            || $dims[0] > 3200 || $dims[1] > 3200
            || $dims[0] * $dims[1] > 10000000) {
            throw ValidationException::withMessages([
                'customImageEditUpload' => 'Görsel ölçüleri geçersiz.',
            ]);
        }
        app(StoreAttachment::class)->handle(
            $this->document, $this->customImageEditUpload, $collection, $count
        );
        $this->reset('customImageEditUpload');
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
