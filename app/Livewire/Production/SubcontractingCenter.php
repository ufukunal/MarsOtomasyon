<?php

namespace App\Livewire\Production;

use App\Actions\Production\LinkProductionServiceInvoice;
use App\Actions\Production\SendMaterialsToSubcontractor;
use App\Enums\DocumentType;
use App\Enums\LocationKind;
use App\Livewire\Concerns\WithIdempotentMutations;
use App\Models\Period\Document;
use App\Models\Period\Location;
use App\Models\Period\ProductionOrder;
use App\Models\Period\ProductionServiceAllocation;
use App\Models\Period\StockBalance;
use App\Models\Period\Transfer;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class SubcontractingCenter extends Component
{
    use WithIdempotentMutations;

    public ?int $selectedOrderId = null;
    public ?int $sourceLocationId = null;
    public string $transferDate = '';

    /** @var array<int,string> */
    public array $transferQuantities = [];

    public ?int $serviceInvoiceId = null;

    public function mount(): void
    {
        $this->seedMutationKeys(['send', 'link']);
        abort_unless(auth()->user()?->can('subcontracting.view'), 403);
        $this->transferDate = now()->toDateString();
    }

    public function selectOrder(int $id): void
    {
        $order = ProductionOrder::query()
            ->with('components')
            ->where('production_type', 'subcontract')
            ->findOrFail($id);

        $this->selectedOrderId = (int) $order->id;
        $this->serviceInvoiceId = null;
        $this->transferQuantities = [];

        foreach ($order->components as $component) {
            $this->transferQuantities[(int) $component->component_product_id] = '0.000';
        }
    }

    public function sendMaterials(SendMaterialsToSubcontractor $action): void
    {
        abort_unless($this->selectedOrderId !== null && $this->sourceLocationId !== null, 422);
        $order = ProductionOrder::query()->findOrFail($this->selectedOrderId);

        $action->handle(
            $order,
            $this->sourceLocationId,
            $this->transferQuantities,
            $this->transferDate,
            $this->mutationKey('send'),
        );
        $this->completeMutation('send');

        foreach (array_keys($this->transferQuantities) as $productId) {
            $this->transferQuantities[$productId] = '0.000';
        }
    }

    public function linkInvoice(LinkProductionServiceInvoice $action): void
    {
        abort_unless($this->selectedOrderId !== null && $this->serviceInvoiceId !== null, 422);
        $order = ProductionOrder::query()->findOrFail($this->selectedOrderId);
        $invoice = Document::query()->findOrFail($this->serviceInvoiceId);

        $action->handle($order, $invoice, $this->mutationKey('link'));
        $this->completeMutation('link');
        $this->serviceInvoiceId = null;
    }

    public function render(): View
    {
        $order = $this->selectedOrderId
            ? ProductionOrder::query()
                ->with([
                    'product', 'subcontractor', 'subcontractorLocation',
                    'components.componentProduct', 'serviceInvoices.invoice',
                    'completions',
                ])
                ->where('production_type', 'subcontract')
                ->find($this->selectedOrderId)
            : null;

        $balances = collect();
        $transfers = collect();
        $allocations = collect();
        $serviceInvoices = collect();

        if ($order) {
            $balances = StockBalance::query()
                ->with('product')
                ->where('location_id', $order->subcontractor_location_id)
                ->where('quantity', '!=', 0)
                ->orderBy('product_id')
                ->get();

            $transfers = Transfer::query()
                ->with('lines.product')
                ->where('production_order_id', $order->id)
                ->orderByDesc('id')
                ->get();

            $allocations = ProductionServiceAllocation::query()
                ->with(['invoiceLine', 'completion'])
                ->where('production_order_id', $order->id)
                ->orderBy('purchase_invoice_line_id')
                ->orderBy('production_completion_id')
                ->get();

            $serviceInvoices = Document::query()
                ->with('lines')
                ->where('document_type', DocumentType::SupplierInvoice->value)
                ->where('status', 'posted')
                ->where('contact_id', $order->subcontractor_contact_id)
                ->whereHas('lines', fn ($query) => $query->where('line_kind', 'service'))
                ->whereDoesntHave('incomingRelations', fn ($query) => $query->where('relation_type', 'reversal_of'))
                ->orderByDesc('document_date')
                ->limit(200)
                ->get();
        }

        return view('livewire.production.subcontracting-center', [
            'orders' => ProductionOrder::query()
                ->with(['product', 'subcontractor'])
                ->where('production_type', 'subcontract')
                ->orderByDesc('id')
                ->limit(300)
                ->get(),
            'order' => $order,
            'normalLocations' => Location::query()
                ->where('is_active', true)
                ->where('kind', '!=', LocationKind::Subcontractor->value)
                ->orderBy('name')
                ->get(),
            'balances' => $balances,
            'transfers' => $transfers,
            'allocations' => $allocations,
            'serviceInvoices' => $serviceInvoices,
        ])->layout('layouts.app', ['pageTitle' => 'Fason Üretim']);
    }
}
