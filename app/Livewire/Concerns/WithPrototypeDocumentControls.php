<?php

namespace App\Livewire\Concerns;

use App\Actions\Documents\CreateEditableDocumentCopy;
use App\Enums\DocumentType;
use App\Models\Period\DocumentRelation;
use App\Models\Period\Product;
use Illuminate\Database\Eloquent\Collection;

/**
 * Visual controls for the approved sales/purchase editor. All financial
 * calculations, stock constraints and posting are still handled server-side
 * by the existing document actions.
 */
trait WithPrototypeDocumentControls
{
    /**
     * A closed document stays immutable. Once the source is financially
     * reversed (where applicable), authorized users can open a separate draft.
     */
    public function canCreateEditableCopy(): bool
    {
        $document = $this->document;

        if ($document === null || $document->status === 'draft'
            || ! in_array($document->document_type, [
                DocumentType::Quote,
                DocumentType::SalesOrder,
                DocumentType::Dispatch,
                DocumentType::SalesInvoice,
                DocumentType::PurchaseQuote,
                DocumentType::PurchaseOrder,

                DocumentType::SupplierInvoice,
            ], true)) {
            return false;
        }

        $user = auth()->user();
        $permission = $this->permissionPrefix();

        if (! $user || ! $user->can($permission.'.view')
            || ! $user->can($permission.'.update')
            || ! $user->can($permission.'.create')) {
            return false;
        }

        if ($document->document_type === DocumentType::Quote && $document->number !== null) {
            return false; // Existing numbered-quote revision workflow.
        }

        if (DocumentRelation::query()
            ->where('relation_type', 'reversal_of')
            ->where('source_document_id', $document->id)->exists()) {
            return false;
        }

        return $document->status !== 'posted' || DocumentRelation::query()
            ->where('relation_type', 'reversal_of')
            ->where('target_document_id', $document->id)
            ->exists();
    }

    public function startEditableCopy(CreateEditableDocumentCopy $action): mixed
    {
        abort_unless($this->document !== null && $this->canCreateEditableCopy(), 403);

        $copy = $action->handle($this->document, $this->mutationKey('editCopy'));
        $this->completeMutation('editCopy');

        $route = match ($copy->document_type) {
            DocumentType::Quote => 'sales.quotes.edit',
            DocumentType::SalesOrder => 'sales.orders.edit',
            DocumentType::Dispatch => 'sales.dispatches.edit',
            DocumentType::SalesInvoice => 'sales.invoices.edit',
            DocumentType::PurchaseQuote => 'purchases.quotes.edit',
            DocumentType::PurchaseOrder => 'purchases.orders.edit',
            DocumentType::PurchaseDispatch => 'purchases.dispatches.edit',
            DocumentType::SupplierInvoice => 'purchases.invoices.edit',
            default => abort(404),
        };

        return $this->redirectRoute($route, ['id' => $copy->id], navigate: false);
    }

    public string $quickProductSearch = '';

    public string $bulkVatPreset = '';

    /** Reuse the same empty-row predicate as the authoritative draft saver. */
    public function isPrototypeEntryRow(array $line): bool
    {
        return $this->isUnusedEntryRow($line);
    }

    /** @return Collection<int, Product> */
    public function quickProductOptions(): Collection
    {
        $needle = mb_substr(trim($this->quickProductSearch), 0, 80);
        if ($needle === '' || mb_strlen($needle) < 2) {
            return new Collection();
        }

        $pattern = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $needle).'%';

        return Product::query()->where('is_active', true)
            ->where(fn ($query) => $query->where('code', 'ilike', $pattern)
                ->orWhere('name', 'ilike', $pattern)
                ->orWhere('barcode', 'ilike', $pattern))
            ->orderBy('name')->limit(12)->get();
    }

    public function insertQuickProduct(int $productId): void
    {
        abort_unless(auth()->user()?->can(
            $this->permissionPrefix().($this->document ? '.update' : '.create')
        ), 403);
        abort_unless($this->document === null || $this->document->status === 'draft', 409);

        Product::query()->where('is_active', true)->findOrFail($productId);
        $this->ensureNextEntryRow();

        foreach ($this->lines as $index => $line) {
            if ($this->isUnusedEntryRow($line)) {
                $this->lines[$index]['product_id'] = $productId;
                // Populate real unit, VAT, price and depot; add the next blank row.
                $this->updated('lines.'.$index.'.product_id');
                $this->quickProductSearch = '';

                return;
            }
        }

        abort(409, 'Yeni ürün satırı açılamadı.');
    }

    public function updatedBulkVatPreset(string $value): void
    {
        if ($value === '') {
            return;
        }

        abort_unless($this->document === null || $this->document->status === 'draft', 409);
        abort_unless($this->documentType() !== DocumentType::GoodsReceipt, 403);
        abort_unless(in_array($value, ['0', '1', '10', '20'], true), 422);
        $this->bulkVatRate = $value;
        $this->applyVatToAll();
        $this->bulkVatPreset = '';
    }

    /**
     * Screenshot's reset action applies only to a new, UNSAVED form.
     * Existing database documents must not be cleared by this control.
     */
    /** Implemented by sales/purchase editors for their own typed fields. */
    abstract protected function resetPrototypeTypeFields(): void;

    public function resetNewDraft(): void
    {
        abort_unless($this->document === null, 409);
        abort_unless(auth()->user()?->can($this->permissionPrefix().'.create'), 403);

        $this->contactId = null;
        $this->contactSearch = '';
        $this->productSearches = [];
        $this->quickProductSearch = '';
        $this->bulkVatPreset = '';
        $this->bulkVatRate = '20';
        $this->discountRate = '0';
        $this->additionalDiscountAmount = '0';
        $this->uiMeta = [];
        $this->notes = '';
        $this->documentDate = now()->toDateString();
        $this->dueDate = null;

        $this->resetPrototypeTypeFields();
        $this->lines = [$this->emptyLine()];
        $this->documentDirty = false;
        $this->resetValidation();
    }
}
