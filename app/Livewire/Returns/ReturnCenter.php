<?php

namespace App\Livewire\Returns;

use App\Actions\Returns\CreateReturnFromInvoice;
use App\Actions\Returns\PostReturnDocument;
use App\Actions\Returns\ReturnLineAvailability;
use App\Actions\Returns\ReverseReturnDocument;
use App\Enums\DocumentType;
use App\Livewire\Concerns\WithIdempotentMutations;
use App\Models\Period\Document;
use App\Models\Period\Location;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ReturnCenter extends Component
{
    use WithIdempotentMutations;

    public string $type = 'sales_return';
    public ?int $sourceInvoiceId = null;
    public ?int $selectedReturnId = null;
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
        $this->seedMutationKeys(['create', 'post', 'reverse']);
        abort_unless(auth()->user()?->can('returns.view'), 403);
        $this->documentDate = now()->toDateString();
        $this->reversalDate = now()->toDateString();
    }

    public function updatedType(): void
    {
        $this->sourceInvoiceId = null;
        $this->selectedReturnId = null;
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
    }

    public function post(PostReturnDocument $action): void
    {
        abort_unless($this->selectedReturnId !== null, 422);
        $return = Document::query()->findOrFail($this->selectedReturnId);
        $posted = $action->handle($return, $this->mutationKey('post'));
        $this->completeMutation('post');
        $this->selectedReturnId = (int) $posted->id;
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
    }

    public function selectReturn(int $id): void
    {
        $document = Document::query()->findOrFail($id);
        abort_unless(in_array($document->document_type, [DocumentType::SalesReturn, DocumentType::PurchaseReturn], true), 404);
        $this->selectedReturnId = $id;
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
