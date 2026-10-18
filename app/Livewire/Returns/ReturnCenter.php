<?php

namespace App\Livewire\Returns;

use App\Actions\Returns\CreateReturnFromInvoice;
use App\Actions\Returns\PostReturnDocument;
use App\Actions\Returns\ReverseReturnDocument;
use App\Actions\Returns\UpdateReturnDraft;
use App\Enums\DocumentType;
use App\Livewire\Concerns\WithDocumentEditDeepLink;
use App\Livewire\Concerns\WithIdempotentMutations;
use App\Models\Period\Document;
use App\Models\Period\DocumentRelation;
use App\Models\Period\Location;
use App\Support\Returns\ReturnDraftPreview;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ReturnCenter extends Component
{
    use WithIdempotentMutations;
    use WithDocumentEditDeepLink;

    public string $type = 'sales_return';

    public ?int $sourceInvoiceId = null;

    #[Locked]
    public ?int $selectedReturnId = null;

    #[Locked]
    public int $selectedReturnVersion = 0;

    #[Locked]
    public bool $editingReturnDraft = false;

    public string $documentDate = '';

    public string $note = '';

    public string $reversalDate = '';

    public string $reversalReason = '';

    /** @var array<int,string> */
    public array $quantities = [];

    /** @var array<int,int|null> */
    public array $locationIds = [];

    public function mount(): void
    {
        $this->seedMutationKeys(['create', 'post', 'reverse', 'saveDraft']);
        abort_unless(auth()->user()?->can('returns.view'), 403);
        $this->documentDate = now()->toDateString();
        $this->reversalDate = now()->toDateString();
        if (request()->routeIs('returns.sales')) {
            $this->type = DocumentType::SalesReturn->value;
        } elseif (request()->routeIs('returns.purchases')) {
            $this->type = DocumentType::PurchaseReturn->value;
        }
        $id = $this->linkedDocumentId();
        if ($id !== null) {
            $document = Document::query()->whereIn('document_type', [
                DocumentType::SalesReturn->value, DocumentType::PurchaseReturn->value,
            ])->findOrFail($id);
            if ($document->status === 'draft' && auth()->user()?->can('returns.update')) {
                $this->editDraft($id);
            } else {
                $this->selectReturn($id);
            }
        }
    }

    public function startNewReturn(): void
    {
        abort_unless(auth()->user()?->can('returns.create'), 403);
        $this->sourceInvoiceId = null;
        $this->selectedReturnId = null;
        $this->selectedReturnVersion = 0;
        $this->editingReturnDraft = false;
        $this->quantities = [];
        $this->locationIds = [];
        $this->note = '';
        $this->documentDate = now()->toDateString();
        $this->resetValidation();
    }

    public function updatedType(): void
    {
        $this->sourceInvoiceId = null;
        $this->selectedReturnId = null;
        $this->selectedReturnVersion = 0;
        $this->editingReturnDraft = false;
        $this->quantities = [];
        $this->locationIds = [];
    }

    public function updatedSourceInvoiceId(): void
    {
        $this->quantities = [];
        $this->locationIds = [];

        if (! $this->sourceInvoiceId) {
            return;
        }

        $returnType = DocumentType::from($this->type);
        abort_unless(in_array($returnType, [DocumentType::SalesReturn, DocumentType::PurchaseReturn], true), 422);
        $invoiceType = $returnType === DocumentType::SalesReturn
            ? DocumentType::SalesInvoice : DocumentType::SupplierInvoice;
        $invoice = Document::query()->with('lines')
            ->where('document_type', $invoiceType->value)
            ->where('status', 'posted')
            ->when($returnType === DocumentType::SalesReturn, fn ($query) => $query->where('currency', 'TRY'))
            ->findOrFail($this->sourceInvoiceId);
        // Selecting an invoice must never auto-return every eligible line.
        // Available quantities remain visible in ReturnDraftPreview.
        foreach ($invoice->lines as $line) {
            $this->quantities[$line->id] = '0';
            $this->locationIds[$line->id] = $line->location_id;
        }
    }

    /** Remove a row from the return selection; never change the source invoice. */
    public function clearReturnLine(int $lineId): void
    {
        abort_unless(auth()->user()?->can($this->editingReturnDraft ? 'returns.update' : 'returns.create'), 403);
        abort_if($this->selectedReturnId !== null && ! $this->editingReturnDraft, 409);
        abort_unless($this->sourceInvoiceId !== null, 422);
        $belongsToSource = \App\Models\Period\DocumentLine::query()
            ->where('document_id', $this->sourceInvoiceId)
            ->whereKey($lineId)->exists();
        abort_unless($belongsToSource, 404);
        $this->quantities[$lineId] = '0';
    }

    public function create(CreateReturnFromInvoice $action): void
    {
        abort_if($this->editingReturnDraft || $this->selectedReturnId !== null, 409,
            'Yeni iade için önce Yeni İade seçin.');
        $this->validate([
            'type' => ['required', 'in:sales_return,purchase_return'],
            'sourceInvoiceId' => ['required', 'integer', 'min:1'],
            'documentDate' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:3000'],
            'quantities' => ['required', 'array'],
            'quantities.*' => ['required', 'numeric', 'min:0', 'decimal:0,3'],
            'locationIds.*' => ['nullable', 'integer', 'min:1'],
        ]);
        $source = Document::query()->findOrFail($this->sourceInvoiceId);

        $quantities = array_filter(
            $this->quantities,
            fn (string $quantity): bool => bccomp($quantity, '0', 3) > 0,
        );
        $locations = array_filter(
            $this->locationIds,
            fn ($id): bool => $id !== null,
        );

        $return = $action->handle(
            $source,
            DocumentType::from($this->type),
            $quantities,
            array_map('intval', $locations),
            $this->documentDate,
            $this->mutationKey('create'),
            $this->note !== '' ? $this->note : null,
        );
        $this->completeMutation('create');
        $this->selectedReturnId = (int) $return->id;
        $this->selectedReturnVersion = (int) $return->version;
        $this->editingReturnDraft = false;
    }

    public function saveDraft(UpdateReturnDraft $action): void
    {
        abort_unless(auth()->user()?->can('returns.update'), 403);
        abort_unless($this->editingReturnDraft && $this->selectedReturnId !== null, 422);

        $this->validate([
            'documentDate' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:3000'],
            'quantities' => ['required', 'array'],
            'quantities.*' => ['required', 'numeric', 'min:0', 'decimal:0,3'],
            'locationIds.*' => ['nullable', 'integer'],
        ]);

        $quantities = array_filter($this->quantities,
            fn ($amount): bool => bccomp((string) $amount, '0', 3) > 0);
        $locations = array_filter($this->locationIds, fn ($id): bool => $id !== null && $id !== '');

        $updated = $action->handle(
            Document::query()->findOrFail($this->selectedReturnId),
            $quantities,
            array_map('intval', $locations),
            $this->documentDate,
            $this->note,
            $this->selectedReturnVersion,
            $this->mutationKey('saveDraft'),
        );
        $this->completeMutation('saveDraft');
        $this->selectedReturnVersion = (int) $updated->version;
        $this->editingReturnDraft = false;
    }

    public function post(PostReturnDocument $action): void
    {
        abort_if($this->editingReturnDraft, 409, 'İade taslağındaki değişiklikleri kaydedin.');
        abort_unless($this->selectedReturnId !== null, 422);
        $return = Document::query()->findOrFail($this->selectedReturnId);
        $posted = $action->handle($return, $this->mutationKey('post'));
        $this->completeMutation('post');
        $this->selectedReturnId = (int) $posted->id;
        $this->editingReturnDraft = false;
    }

    /** Direct audited deletion for an already posted return. */
    public function deleteReturn(int $id): void
    {
        abort_unless(auth()->user()?->can('returns.cancel'), 403);
        $return = Document::query()->whereIn('document_type', [
            DocumentType::SalesReturn->value, DocumentType::PurchaseReturn->value,
        ])->where('status', 'posted')->findOrFail($id);
        app(ReverseReturnDocument::class)->handle(
            $return,
            now()->toDateString(),
            'İade kullanıcı tarafından silindi; mali geçmiş korundu.',
            $this->childMutationKey('reverse', 'delete:'.$id),
        );
        $this->completeMutation('reverse');
        if ($this->selectedReturnId === $id) {
            $this->startNewReturn();
        }
    }

    public function reverse(ReverseReturnDocument $action): void
    {
        abort_unless($this->selectedReturnId !== null, 422);
        $return = Document::query()->findOrFail($this->selectedReturnId);
        $reversal = $action->handle(
            $return,
            $this->reversalDate,
            $this->reversalReason,
            $this->mutationKey('reverse'),
        );
        $this->completeMutation('reverse');
        $this->selectedReturnId = (int) $reversal->id;
        $this->editingReturnDraft = false;
    }

    public function selectReturn(int $id): void
    {
        $document = Document::query()->with('lines')->findOrFail($id);
        abort_unless(in_array($document->document_type, [DocumentType::SalesReturn, DocumentType::PurchaseReturn], true), 404);
        $this->type = $document->document_type->value;
        $this->selectedReturnId = $id;
        $this->selectedReturnVersion = (int) $document->version;
        $this->editingReturnDraft = false;
        $this->sourceInvoiceId = null;
        $this->quantities = [];
        $this->locationIds = [];

        // Show the original invoice and its frozen values when an existing
        // return is inspected; never make the posted return editable in place.
        $relationType = $document->document_type === DocumentType::SalesReturn
            ? 'sales_invoice_to_return' : 'supplier_invoice_to_return';
        $sourceId = DocumentRelation::query()
            ->where('target_document_id', $document->id)
            ->where('relation_type', $relationType)
            ->value('source_document_id');
        if ($sourceId !== null) {
            $source = Document::query()->with('lines')->find((int) $sourceId);
            $sourceType = $document->document_type === DocumentType::SalesReturn
                ? DocumentType::SalesInvoice : DocumentType::SupplierInvoice;
            if ($source && $source->document_type === $sourceType) {
                $this->sourceInvoiceId = (int) $sourceId;
                foreach ($source->lines as $line) {
                    $this->quantities[(int) $line->id] = '0';
                    $this->locationIds[(int) $line->id] = $line->location_id;
                }
                foreach ($document->lines as $line) {
                    if ($line->source_line_id !== null) {
                        $this->quantities[(int) $line->source_line_id] = (string) $line->quantity;
                        $this->locationIds[(int) $line->source_line_id] = $line->location_id;
                    }
                }
            }
        }
    }

    public function editDraft(int $id): void
    {
        abort_unless(auth()->user()?->can('returns.update'), 403);
        $return = Document::query()->with('lines')
            ->whereIn('document_type', [DocumentType::SalesReturn->value, DocumentType::PurchaseReturn->value])
            ->where('status', 'draft')->findOrFail($id);
        $relationType = $return->document_type === DocumentType::SalesReturn
            ? 'sales_invoice_to_return' : 'supplier_invoice_to_return';
        $sourceId = DocumentRelation::query()->where('target_document_id', $return->id)
            ->where('relation_type', $relationType)->value('source_document_id');

        if ($sourceId === null) {
            throw ValidationException::withMessages([
                'selectedReturnId' => 'İade taslağının kaynak faturası bulunamadı.',
            ]);
        }

        $source = Document::query()->with('lines')->findOrFail((int) $sourceId);
        $this->type = $return->document_type->value;
        $this->sourceInvoiceId = (int) $sourceId;
        $this->documentDate = $return->document_date->toDateString();
        $this->note = (string) ($return->notes ?? '');
        $this->quantities = [];
        $this->locationIds = [];

        foreach ($source->lines as $line) {
            $this->quantities[$line->id] = '0.000';
            $this->locationIds[$line->id] = $line->location_id;
        }
        foreach ($return->lines as $line) {
            $this->quantities[(int) $line->source_line_id] = (string) $line->quantity;
            $this->locationIds[(int) $line->source_line_id] = $line->location_id;
        }

        $this->selectedReturnId = $return->id;
        $this->selectedReturnVersion = (int) $return->version;
        $this->editingReturnDraft = true;
    }

    public function render(): View
    {
        $returnType = $this->type === DocumentType::SalesReturn->value
            ? DocumentType::SalesReturn : DocumentType::PurchaseReturn;
        $sourceType = $returnType === DocumentType::SalesReturn
            ? DocumentType::SalesInvoice : DocumentType::SupplierInvoice;
        $sourceInvoice = $this->sourceInvoiceId
            ? Document::query()->with(['lines.product', 'lines.unit', 'contact'])
                ->where('document_type', $sourceType->value)
                ->where('status', 'posted')
                ->find($this->sourceInvoiceId)
            : null;
        $selectedReturn = $this->selectedReturnId
            ? Document::query()->with(['lines', 'contact'])->find($this->selectedReturnId)
            : null;
        $isPosted = $selectedReturn && $selectedReturn->status === 'posted';
        $preview = $sourceInvoice
            ? app(ReturnDraftPreview::class)->handle(
                $sourceInvoice, $returnType, $isPosted ? [] : $this->quantities,
            )
            : ['totals' => null, 'additional' => '0.0000', 'rows' => [],
                'remaining' => [], 'count' => 0, 'error' => null];

        if ($isPosted) {
            $preview['count'] = $selectedReturn->lines->count();
            $preview['additional'] = (string) $selectedReturn->additional_discount_amount;
            $preview['error'] = null;
            foreach ($selectedReturn->lines as $line) {
                if ($line->source_line_id === null) {
                    continue;
                }
                $net = (string) $line->line_total;
                $vat = \App\Support\Money\Money::of($net)
                    ->percent((string) $line->vat_rate)->amount;
                $preview['rows'][(int) $line->source_line_id] = [
                    'net' => $net,
                    'with_vat' => \App\Support\Money\Money::of($net)
                        ->plus(\App\Support\Money\Money::of($vat))->amount,
                ];
            }
        }

        return view('livewire.returns.return-center', [
            'returnPreview' => $preview,
            'sourceInvoices' => Document::query()
                ->with('contact')
                ->where('document_type', $sourceType->value)
                ->where('status', 'posted')
                ->when($returnType === DocumentType::SalesReturn, fn ($query) => $query->where('currency', 'TRY'))
                ->whereDoesntHave('incomingRelations', fn ($query) => $query->where('relation_type', 'reversal_of'))
                ->whereDoesntHave('outgoingRelations', fn ($query) => $query->where('relation_type', 'reversal_of'))
                ->orderByDesc('document_date')
                ->limit(500)
                ->get(),
            'sourceInvoice' => $sourceInvoice,
            'selectedReturn' => $selectedReturn,
            'returns' => Document::query()
                ->with('contact')
                ->where('document_type', $returnType->value)
                ->whereDoesntHave('incomingRelations', fn ($relation) => $relation->where('relation_type', 'reversal_of'))
                ->whereDoesntHave('outgoingRelations', fn ($relation) => $relation->where('relation_type', 'reversal_of'))
                ->orderByDesc('document_date')
                ->orderByDesc('id')
                ->limit(200)
                ->get(),
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(),
        ])->layout('layouts.app', ['pageTitle' => 'İade Merkezi']);
    }
}
