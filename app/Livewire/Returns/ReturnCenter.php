<?php

namespace App\Livewire\Returns;

use App\Actions\Returns\CreateReturnFromInvoice;
use App\Actions\Returns\PostReturnDocument;
use App\Actions\Returns\ReturnLineAvailability;
use App\Actions\Returns\ReverseReturnDocument;
use App\Actions\Returns\UpdateReturnDraft;
use App\Models\Period\DocumentRelation;
use Illuminate\Validation\ValidationException;
use App\Enums\DocumentType;
use App\Livewire\Concerns\WithIdempotentMutations;
use App\Models\Period\Document;
use App\Models\Period\Location;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ReturnCenter extends Component
{
    use WithIdempotentMutations;

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

        $invoice = Document::query()->with('lines')->findOrFail($this->sourceInvoiceId);
        $returnType = DocumentType::from($this->type);
        $availability = app(ReturnLineAvailability::class);

        foreach ($invoice->lines as $line) {
            $this->quantities[$line->id] = $availability->remaining($line, $returnType);
            $this->locationIds[$line->id] = $line->location_id;
        }
    }

    public function create(CreateReturnFromInvoice $action): void
    {
        abort_if($this->editingReturnDraft, 409, 'Düzenlediğiniz iade taslağını önce kaydedin veya kapatın.');
        abort_unless($this->sourceInvoiceId !== null, 422);
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
        $this->editingReturnDraft = true;
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
        $document = Document::query()->findOrFail($id);
        abort_unless(in_array($document->document_type, [DocumentType::SalesReturn, DocumentType::PurchaseReturn], true), 404);
        $this->selectedReturnId = $id;
        $this->selectedReturnVersion = (int) $document->version;
        $this->editingReturnDraft = false;
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
        $sourceType = $this->type === DocumentType::SalesReturn->value
            ? DocumentType::SalesInvoice
            : DocumentType::SupplierInvoice;

        return view('livewire.returns.return-center', [
            'sourceInvoices' => Document::query()
                ->with('contact')
                ->where('document_type', $sourceType->value)
                ->where('status', 'posted')
                ->whereDoesntHave('incomingRelations', fn ($query) => $query->where('relation_type', 'reversal_of'))
                ->whereDoesntHave('outgoingRelations', fn ($query) => $query->where('relation_type', 'reversal_of'))
                ->orderByDesc('document_date')
                ->limit(500)
                ->get(),
            'sourceInvoice' => $this->sourceInvoiceId
                ? Document::query()->with('lines')->find($this->sourceInvoiceId)
                : null,
            'selectedReturn' => $this->selectedReturnId
                ? Document::query()->with(['lines', 'contact'])->find($this->selectedReturnId)
                : null,
            'returns' => Document::query()
                ->with('contact')
                ->whereIn('document_type', [DocumentType::SalesReturn->value, DocumentType::PurchaseReturn->value])
                ->orderByDesc('document_date')
                ->orderByDesc('id')
                ->limit(200)
                ->get(),
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(),
        ])->layout('layouts.app', ['pageTitle' => 'İade Merkezi']);
    }
}
