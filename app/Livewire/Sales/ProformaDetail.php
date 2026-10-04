<?php

namespace App\Livewire\Sales;

use App\Actions\Sales\ConvertProformaToInvoice;
use App\Enums\DocumentType;
use App\Livewire\Concerns\WithIdempotentMutations;
use App\Models\Period\Document;
use App\Models\Period\Location;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ProformaDetail extends Component
{
    use WithIdempotentMutations;

    public Document $proforma;

    public string $invoiceDate = '';

    /** @var array<int,int|null> */
    public array $fallbackLocationIds = [];

    public function mount(int $id): void
    {
        $this->seedMutationKeys(['invoice']);
        abort_unless(auth()->user()?->can('proformas.view'), 403);

        $this->proforma = Document::query()->with(['lines.product', 'contact'])->findOrFail($id);
        abort_unless($this->proforma->document_type === DocumentType::Proforma, 404);
        $this->invoiceDate = now()->toDateString();
    }

    public function createInvoice(ConvertProformaToInvoice $action): mixed
    {
        $locations = [];

        foreach ($this->fallbackLocationIds as $lineId => $locationId) {
            if ($locationId) {
                $locations[(int) $lineId] = (int) $locationId;
            }
        }

        $invoice = $action->handle(
            $this->proforma,
            $locations,
            $this->invoiceDate,
            $this->mutationKey('invoice'),
        );
        $this->completeMutation('invoice');

        return $this->redirectRoute('sales.invoices.edit', ['id' => $invoice->id], navigate: false);
    }

    public function render(): View
    {
        return view('livewire.sales.proforma-detail', [
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(),
        ])->layout('layouts.app', ['pageTitle' => 'Proforma']);
    }
}
