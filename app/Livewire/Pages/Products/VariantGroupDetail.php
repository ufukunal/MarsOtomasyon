<?php

namespace App\Livewire\Pages\Products;

use App\Actions\Products\SaveVariantValues;
use App\Models\Period\Product;
use App\Models\Period\VariantAttribute;
use App\Models\Period\VariantGroup;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class VariantGroupDetail extends Component
{
    public ?VariantGroup $group = null;
    public string $name = '';
    public bool $isActive = true;
    public int $version = 1;
    public string $newAttribute = '';
    public ?int $productId = null;
    public array $values = [];
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

    public function saveGroup(): void
    {
        abort_unless(auth()->user()?->can($this->group ? 'variant_groups.update' : 'variant_groups.create'), 403);

        $this->validate(['name' => ['required', 'max:255']]);

        $attributes = ['name' => trim($this->name), 'is_active' => $this->isActive];

        $this->group = $this->group
            ? $this->group->updateWithVersion($attributes, $this->version)
            : VariantGroup::query()->create($attributes);

        $this->version = (int) $this->group->version;
    }

    public function addAttribute(): void
    {
        abort_unless($this->group, 422);

        $this->validate(['newAttribute' => ['required', 'max:255']]);

        VariantAttribute::query()->create([
            'variant_group_id' => $this->group->id,
            'name' => trim($this->newAttribute),
            'sort_order' => $this->group->attributes()->count(),
        ]);

        $this->newAttribute = '';
    }

    public function attachProduct(SaveVariantValues $action): void
    {
        abort_unless($this->group && $this->productId, 422);

        $product = Product::query()->findOrFail($this->productId);
        $this->warnings = $action->handle($product, $this->group->id, $this->values);
        $this->values = [];
        $this->productId = null;
    }

    public function render(): View
    {
        return view('livewire.pages.products.variant-group-detail', [
            'groups' => VariantGroup::query()->orderBy('name')->get(),
            'products' => Product::query()->where('is_active', true)->orderBy('name')->limit(500)->get(),
            'attributes' => $this->group?->attributes()->get() ?? collect(),
            'groupProducts' => $this->group?->products()->with('variantValues.attribute')->get() ?? collect(),
        ])->layout('layouts.app', ['pageTitle' => 'Varyant Grupları']);
    }
}
