<?php

namespace App\Livewire\Pages\Products;

use App\Actions\Products\SaveVariantAttribute;
use App\Actions\Products\SaveVariantGroup;
use App\Actions\Products\SaveVariantValues;
use App\Models\Period\Product;
use App\Models\Period\VariantAttribute;
use App\Models\Period\VariantGroup;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class VariantGroupDetail extends Component
{
    public ?VariantGroup $group = null;

    public string $name = '';

    public bool $isActive = true;

    public int $version = 1;

    public ?int $attributeId = null;

    public int $attributeVersion = 1;

    public string $newAttribute = '';

    public ?int $productId = null;

    /** @var array<int, string> */
    public array $values = [];

    /** @var list<string> */
    public array $warnings = [];

    public function mount(?VariantGroup $group = null): void
    {
        abort_unless(auth()->user()?->can('variant_groups.view'), 403);
        $this->group = $group;

        if ($group) {
            $this->name = $group->name;
            $this->isActive = (bool) $group->is_active;
            $this->version = (int) $group->version;
        }
    }

    public function saveGroup(SaveVariantGroup $action): void
    {
        $this->validate(['name' => ['required', 'max:255']]);

        $this->group = $action->handle([
            'name' => $this->name,
            'is_active' => $this->isActive,
        ], $this->group, $this->version);

        $this->version = (int) $this->group->version;
    }

    public function saveAttribute(SaveVariantAttribute $action): void
    {
        abort_unless($this->group !== null, 422);
        $this->validate(['newAttribute' => ['required', 'max:255']]);

        $attribute = $this->attributeId
            ? VariantAttribute::query()->where('variant_group_id', $this->group->id)->findOrFail($this->attributeId)
            : null;

        $action->handle($this->group, [
            'name' => $this->newAttribute,
            'sort_order' => $attribute->sort_order ?? $this->group->attributes()->count(),
        ], $attribute, $attribute ? $this->attributeVersion : null);

        $this->attributeId = null;
        $this->attributeVersion = 1;
        $this->newAttribute = '';
    }

    public function editAttribute(int $id): void
    {
        abort_unless($this->group !== null, 422);
        $attribute = VariantAttribute::query()->where('variant_group_id', $this->group->id)->findOrFail($id);
        $this->attributeId = $attribute->id;
        $this->attributeVersion = (int) $attribute->version;
        $this->newAttribute = $attribute->name;
    }

    #[On('lookup-selected')]
    public function productSelected(int|string $id): void
    {
        if (! $this->group) {
            return;
        }

        $product = Product::query()->findOrFail($id);
        $this->productId = (int) $product->id;
        $this->values = $product->variantValues()
            ->pluck('value', 'variant_attribute_id')
            ->all();
    }

    public function attachProduct(SaveVariantValues $action): void
    {
        abort_unless($this->group !== null && $this->productId !== null, 422);

        $product = Product::query()->findOrFail($this->productId);
        $this->warnings = $action->handle($product, $this->group->id, $this->values);
        $this->values = [];
        $this->productId = null;
    }

    public function render(): View
    {
        return view('livewire.pages.products.variant-group-detail', [
            'attributes' => $this->group?->attributes()->get() ?? collect(),
            'groupProducts' => $this->group?->products()
                ->with('variantValues.attribute')
                ->orderBy('code')
                ->get() ?? collect(),
        ])->layout('layouts.app', ['pageTitle' => 'Varyant Grupları']);
    }
}
