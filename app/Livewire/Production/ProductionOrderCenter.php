<?php

namespace App\Livewire\Production;

use App\Actions\Production\CancelProductionOrderRemaining;
use App\Actions\Production\ConfirmProductionOrder;
use App\Actions\Production\PostProductionCompletion;
use App\Actions\Production\ReverseProductionCompletion;
use App\Actions\Production\SaveProductionOrderDraft;
use App\Enums\LocationKind;
use App\Livewire\Concerns\WithIdempotentMutations;
use App\Models\Period\Contact;
use App\Models\Period\Location;
use App\Models\Period\Product;
use App\Models\Period\ProductionCompletion;
use App\Models\Period\ProductionOrder;
use App\Models\Period\ProductionRecipe;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ProductionOrderCenter extends Component
{
    use WithIdempotentMutations;

    public ?int $selectedOrderId = null;
    public int $version = 1;
    public string $documentDate = '';
    public ?int $productId = null;
    public ?int $recipeId = null;
    public string $plannedQuantity = '1.000';
    public string $productionType = 'internal';
    public ?int $subcontractorContactId = null;
    public ?int $subcontractorLocationId = null;
    public string $notes = '';

    public string $completionDate = '';
    public string $completionQuantity = '0.000';

    /** @var array<int,array{consumed_quantity:string,fire_quantity:string,location_id:int|null}> */
    public array $consumptions = [];

    /** @var list<array{location_id:int|null,quantity:string}> */
    public array $outputs = [];

    public string $completionNotes = '';
    public string $reversalDate = '';
    public string $reversalReason = '';

    public function mount(): void
    {
        $this->seedMutationKeys(['save', 'confirm', 'cancel', 'complete', 'reverse']);
        abort_unless(auth()->user()?->can('production_orders.view'), 403);
        $this->documentDate = now()->toDateString();
        $this->completionDate = now()->toDateString();
        $this->reversalDate = now()->toDateString();
        $this->newOrder();
    }

    public function newOrder(): void
    {
        $this->selectedOrderId = null;
        $this->version = 1;
        $this->documentDate = now()->toDateString();
        $this->productId = null;
        $this->recipeId = null;
        $this->plannedQuantity = '1.000';
        $this->productionType = 'internal';
        $this->subcontractorContactId = null;
        $this->subcontractorLocationId = null;
        $this->notes = '';
        $this->consumptions = [];
        $this->outputs = [['location_id' => null, 'quantity' => '0.000']];
    }

    public function selectOrder(int $id): void
    {
        $order = ProductionOrder::query()
            ->with(['components.componentProduct', 'completions'])
            ->findOrFail($id);

        $this->selectedOrderId = (int) $order->id;
        $this->version = (int) $order->version;
        $this->documentDate = $order->document_date->toDateString();
        $this->productId = (int) $order->product_id;
        $this->recipeId = (int) $order->recipe_id;
        $this->plannedQuantity = (string) $order->planned_quantity;
        $this->productionType = (string) $order->production_type;
        $this->subcontractorContactId = $order->subcontractor_contact_id;
        $this->subcontractorLocationId = $order->subcontractor_location_id;
        $this->notes = (string) ($order->notes ?? '');
        $this->syncCompletionInputs($order);
    }

    public function save(SaveProductionOrderDraft $action): void
    {
        $existing = $this->selectedOrderId
            ? ProductionOrder::query()->findOrFail($this->selectedOrderId)
            : null;

        $saved = $this->runPeriodMutation('save', fn () => $action->handle([
            'document_date' => $this->documentDate,
            'product_id' => $this->productId,
            'recipe_id' => $this->recipeId,
            'planned_quantity' => $this->plannedQuantity,
            'production_type' => $this->productionType,
            'subcontractor_contact_id' => $this->subcontractorContactId,
            'subcontractor_location_id' => $this->subcontractorLocationId,
            'source_sales_order_id' => $existing?->source_sales_order_id,
            'notes' => $this->notes,
        ], $existing, $existing ? $this->version : null));

        $this->selectOrder((int) $saved->id);
    }

    public function confirm(ConfirmProductionOrder $action): void
    {
        $order = $this->currentOrder();
        $confirmed = $action->handle($order, $this->mutationKey('confirm'));
        $this->completeMutation('confirm');
        $this->selectOrder((int) $confirmed->id);
    }

    public function cancelRemaining(CancelProductionOrderRemaining $action): void
    {
        $order = $this->currentOrder();
        $updated = $action->handle($order, $this->mutationKey('cancel'));
        $this->completeMutation('cancel');
        $this->selectOrder((int) $updated->id);
    }

    public function addOutput(): void
    {
        $this->outputs[] = ['location_id' => null, 'quantity' => '0.000'];
    }

    public function removeOutput(int $index): void
    {
        unset($this->outputs[$index]);
        $this->outputs = array_values($this->outputs);

        if ($this->outputs === []) {
            $this->addOutput();
        }
    }

    public function postCompletion(PostProductionCompletion $action): void
    {
        $order = $this->currentOrder();
        $completion = $action->handle(
            $order,
            $this->completionDate,
            $this->completionQuantity,
            $this->consumptions,
            array_map(fn (array $output): array => [
                'location_id' => (int) ($output['location_id'] ?? 0),
                'quantity' => (string) $output['quantity'],
            ], $this->outputs),
            $this->mutationKey('complete'),
            $this->completionNotes !== '' ? $this->completionNotes : null,
        );
        $this->completeMutation('complete');
        $this->selectOrder((int) $completion->production_order_id);
    }

    public function reverseCompletion(int $completionId, ReverseProductionCompletion $action): void
    {
        $completion = ProductionCompletion::query()->findOrFail($completionId);
        abort_unless((int) $completion->production_order_id === $this->selectedOrderId, 404);

        $reversal = $action->handle(
            $completion,
            $this->reversalDate,
            $this->reversalReason,
            $this->mutationKey('reverse'),
        );
        $this->completeMutation('reverse');
        $this->reversalReason = '';
        $this->selectOrder((int) $reversal->production_order_id);
    }

    public function render(): View
    {
        $order = $this->selectedOrderId
            ? ProductionOrder::query()
                ->with([
                    'product', 'recipe', 'components.componentProduct', 'subcontractor',
                    'subcontractorLocation', 'sourceSalesOrder',
                    'completions.consumptions.componentProduct',
                    'completions.outputs.location',
                ])
                ->find($this->selectedOrderId)
            : null;

        return view('livewire.production.production-order-center', [
            'orders' => ProductionOrder::query()
                ->with('product')
                ->orderByDesc('id')
                ->limit(300)
                ->get(),
            'order' => $order,
            'products' => Product::query()
                ->where('is_active', true)
                ->where('kind', '!=', 'set')
                ->orderBy('name')
                ->limit(1000)
                ->get(),
            'recipes' => ProductionRecipe::query()
                ->where('is_active', true)
                ->orderBy('product_id')
                ->get(),
            'contacts' => Contact::query()->where('is_active', true)->orderBy('title')->limit(500)->get(),
            'subcontractorLocations' => Location::query()
                ->where('is_active', true)
                ->where('kind', LocationKind::Subcontractor->value)
                ->orderBy('name')
                ->get(),
            'normalLocations' => Location::query()
                ->where('is_active', true)
                ->where('kind', '!=', LocationKind::Subcontractor->value)
                ->orderBy('name')
                ->get(),
        ])->layout('layouts.app', ['pageTitle' => 'Üretim Emirleri']);
    }

    private function currentOrder(): ProductionOrder
    {
        abort_unless($this->selectedOrderId !== null, 422);

        return ProductionOrder::query()->findOrFail($this->selectedOrderId);
    }

    private function syncCompletionInputs(ProductionOrder $order): void
    {
        $order->loadMissing('components');
        $remaining = $order->remainingQuantity();
        $this->completionQuantity = bccomp($remaining, '0', 3) > 0 ? $remaining : '0.000';
        $this->completionDate = now()->toDateString();
        $this->completionNotes = '';
        $this->consumptions = [];

        foreach ($order->components as $component) {
            $suggested = bccomp((string) $order->planned_quantity, '0', 3) > 0
                ? bcadd(
                    bcdiv(
                        bcmul((string) $component->planned_base_quantity, $this->completionQuantity, 8),
                        (string) $order->planned_quantity,
                        8,
                    ),
                    '0',
                    3,
                )
                : '0.000';

            $this->consumptions[(int) $component->component_product_id] = [
                'consumed_quantity' => $suggested,
                'fire_quantity' => '0.000',
                'location_id' => $order->production_type === 'subcontract'
                    ? $order->subcontractor_location_id
                    : null,
            ];
        }

        $this->outputs = [[
            'location_id' => null,
            'quantity' => $this->completionQuantity,
        ]];
    }
}
