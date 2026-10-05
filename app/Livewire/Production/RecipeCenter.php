<?php

namespace App\Livewire\Production;

use App\Actions\Production\CreateProductionRecipeRevision;
use App\Actions\Production\SetActiveProductionRecipe;
use App\Livewire\Concerns\WithIdempotentMutations;
use App\Models\Period\Product;
use App\Models\Period\ProductionRecipe;
use App\Models\Period\Unit;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class RecipeCenter extends Component
{
    use WithIdempotentMutations;

    public ?int $selectedRecipeId = null;
    public ?int $productId = null;
    public string $outputQuantity = '1.000';

    /** @var list<array{component_product_id:int|null,unit_id:int|null,quantity:string}> */
    public array $lines = [];

    public function mount(): void
    {
        $this->seedMutationKeys(['save', 'activate']);
        abort_unless(auth()->user()?->can('production_recipes.view'), 403);
        $this->addLine();
    }

    public function addLine(): void
    {
        $this->lines[] = [
            'component_product_id' => null,
            'unit_id' => null,
            'quantity' => '1.000',
        ];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);

        if ($this->lines === []) {
            $this->addLine();
        }
    }

    public function selectRecipe(int $id): void
    {
        $recipe = ProductionRecipe::query()->with('lines')->findOrFail($id);
        $this->selectedRecipeId = (int) $recipe->id;
        $this->productId = (int) $recipe->product_id;
        $this->outputQuantity = (string) $recipe->output_quantity;
        $this->lines = $recipe->lines->map(fn ($line): array => [
            'component_product_id' => (int) $line->component_product_id,
            'unit_id' => (int) $line->unit_id,
            'quantity' => (string) $line->quantity,
        ])->values()->all();
    }

    public function newRecipe(): void
    {
        $this->selectedRecipeId = null;
        $this->productId = null;
        $this->outputQuantity = '1.000';
        $this->lines = [];
        $this->addLine();
    }

    public function save(CreateProductionRecipeRevision $action): void
    {
        abort_unless(auth()->user()?->can(
            'production_recipes.'.($this->selectedRecipeId ? 'update' : 'create')
        ), 403);

        $this->validate([
            'productId' => ['required', 'integer'],
            'outputQuantity' => ['required', 'decimal:0,3'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.component_product_id' => ['required', 'integer'],
            'lines.*.unit_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'decimal:0,3'],
        ]);

        $recipe = $this->runPeriodMutation('save', fn () => $action->handle(
            (int) $this->productId,
            $this->outputQuantity,
            array_map(fn (array $line): array => [
                'component_product_id' => (int) $line['component_product_id'],
                'unit_id' => (int) $line['unit_id'],
                'quantity' => (string) $line['quantity'],
            ], $this->lines),
        ));

        $this->selectRecipe((int) $recipe->id);
    }

    public function activate(int $id, SetActiveProductionRecipe $action): void
    {
        $recipe = ProductionRecipe::query()->findOrFail($id);
        $this->runPeriodMutation('activate', fn () => $action->handle($recipe));
        $this->completeMutation('activate');
        $this->selectRecipe($id);
    }

    public function render(): View
    {
        return view('livewire.production.recipe-center', [
            'recipes' => ProductionRecipe::query()
                ->with('product')
                ->orderBy('product_id')
                ->orderByDesc('revision_no')
                ->get(),
            'products' => Product::query()
                ->where('is_active', true)
                ->where('kind', '!=', 'set')
                ->orderBy('name')
                ->limit(1000)
                ->get(),
            'units' => Unit::query()->where('is_active', true)->orderBy('name')->get(),
            'selectedRecipe' => $this->selectedRecipeId
                ? ProductionRecipe::query()
                    ->with(['product', 'lines.componentProduct', 'lines.unit'])
                    ->find($this->selectedRecipeId)
                : null,
        ])->layout('layouts.app', ['pageTitle' => 'Üretim Reçeteleri']);
    }
}
