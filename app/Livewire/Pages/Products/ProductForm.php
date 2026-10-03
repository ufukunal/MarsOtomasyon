<?php

namespace App\Livewire\Pages\Products;

use App\Actions\Products\DeleteSetComponent;
use App\Actions\Products\SaveConfigDefinition;
use App\Actions\Products\SaveProduct;
use App\Actions\Products\SaveSetComponent;
use App\Models\Period\Brand;
use App\Models\Period\ConfigDefinition;
use App\Models\Period\Product;
use App\Models\Period\ProductCategory;
use App\Models\Period\ProductSet;
use App\Models\Period\Unit;
use App\Models\Period\VariantGroup;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ProductForm extends Component
{
    public ?Product $product = null;
    public string $code = '';
    public string $name = '';
    public string $description = '';
    public ?int $categoryId = null;
    public ?int $brandId = null;
    public ?int $unitId = null;
    public string $barcode = '';
    public string $vatRate = '20.0000';
    public string $listPrice = '0.0000';
    public bool $priceVatIncluded = false;
    public string $currency = 'TRY';
    public string $kind = 'normal';
    public ?int $variantGroupId = null;
    public bool $allowNegativeStock = false;
    public string $minStock = '0.000';
    public string $channelStockMode = 'stock';
    public bool $isActive = true;
    public int $version = 1;
    public string $activeTab = 'general';

    public ?int $componentLineId = null;
    public int $componentVersion = 1;
    public ?int $componentProductId = null;
    public string $componentQuantity = '1.000';

    public ?int $configDefinitionId = null;
    public int $configDefinitionVersion = 1;
    public string $configName = '';
    public bool $configRequired = false;
    public array $configOptions = [];

    public function mount(?Product $product = null): void
    {
        $this->product = $product;
        $this->authorize($product ? 'update' : 'create', $product ?? Product::class);

        if (! $product) {
            $this->unitId = Unit::query()->where('code', 'ADET')->value('id');
            return;
        }

        $this->code = $product->code;
        $this->name = $product->name;
        $this->description = (string) $product->description;
        $this->categoryId = $product->category_id;
        $this->brandId = $product->brand_id;
        $this->unitId = $product->unit_id;
        $this->barcode = (string) $product->barcode;
        $this->vatRate = (string) $product->vat_rate;
        $this->listPrice = (string) $product->list_price;
        $this->currency = $product->currency;
        $this->kind = $product->kind->value;
        $this->variantGroupId = $product->variant_group_id;
        $this->allowNegativeStock = (bool) $product->allow_negative_stock;
        $this->minStock = (string) $product->min_stock;
        $this->channelStockMode = $product->channel_stock_mode->value;
        $this->isActive = (bool) $product->is_active;
        $this->version = (int) $product->version;
    }

    public function save(SaveProduct $action): void
    {
        $data = $this->validate([
            'code' => ['required', 'max:40'],
            'name' => ['required', 'max:255'],
            'description' => ['nullable'],
            'categoryId' => ['nullable', 'integer'],
            'brandId' => ['nullable', 'integer'],
            'unitId' => ['required', 'integer'],
            'barcode' => ['nullable', 'max:40'],
            'vatRate' => ['required', 'decimal:0,4', 'min:0'],
            'listPrice' => ['required', 'decimal:0,4', 'min:0'],
            'currency' => ['required', 'size:3'],
            'kind' => ['required', 'in:normal,set,configurable'],
            'variantGroupId' => ['nullable', 'integer'],
            'minStock' => ['required', 'decimal:0,3', 'min:0'],
            'channelStockMode' => ['required', 'in:stock,production,manual'],
        ]);

        $saved = $action->handle([
            'code' => $data['code'],
            'name' => $data['name'],
            'description' => $data['description'],
            'category_id' => $data['categoryId'],
            'brand_id' => $data['brandId'],
            'unit_id' => $data['unitId'],
            'barcode' => $data['barcode'],
            'vat_rate' => $data['vatRate'],
            'list_price' => $data['listPrice'],
            'price_vat_included' => $this->priceVatIncluded,
            'currency' => $data['currency'],
            'kind' => $data['kind'],
            'variant_group_id' => $data['variantGroupId'],
            'allow_negative_stock' => $this->allowNegativeStock,
            'min_stock' => $data['minStock'],
            'channel_stock_mode' => $data['channelStockMode'],
            'is_active' => $this->isActive,
        ], $this->product, $this->version);

        $this->redirectRoute('products.edit', ['product' => $saved->id], navigate: false);
    }

    public function saveSetComponent(SaveSetComponent $action): void
    {
        abort_unless($this->product, 422);

        $data = $this->validate([
            'componentProductId' => ['required', 'integer'],
            'componentQuantity' => ['required', 'decimal:0,3'],
        ]);

        $component = Product::query()->findOrFail($data['componentProductId']);
        $line = $this->componentLineId
            ? ProductSet::query()->where('set_product_id', $this->product->id)->findOrFail($this->componentLineId)
            : null;

        $action->handle(
            $this->product,
            $component,
            $data['componentQuantity'],
            $line,
            $line ? $this->componentVersion : null,
        );

        $this->resetSetEditor();
    }

    public function editSetComponent(int $lineId): void
    {
        abort_unless($this->product, 422);

        $line = ProductSet::query()
            ->where('set_product_id', $this->product->id)
            ->findOrFail($lineId);

        $this->componentLineId = $line->id;
        $this->componentVersion = (int) $line->version;
        $this->componentProductId = $line->component_product_id;
        $this->componentQuantity = (string) $line->quantity;
    }

    public function removeSetComponent(int $lineId, DeleteSetComponent $action): void
    {
        abort_unless($this->product, 422);

        $line = ProductSet::query()
            ->where('set_product_id', $this->product->id)
            ->findOrFail($lineId);

        $action->handle($this->product, $line, (int) $line->version);
        $this->resetSetEditor();
    }

    private function resetSetEditor(): void
    {
        $this->componentLineId = null;
        $this->componentVersion = 1;
        $this->componentProductId = null;
        $this->componentQuantity = '1.000';
    }

    public function saveConfigGroup(SaveConfigDefinition $action): void
    {
        abort_unless($this->product, 422);

        $options = collect($this->configOptions)
            ->filter(fn ($row): bool => trim((string) ($row['label'] ?? '')) !== '')
            ->values()
            ->all();

        $definition = $this->configDefinitionId
            ? ConfigDefinition::query()->where('product_id', $this->product->id)->findOrFail($this->configDefinitionId)
            : null;

        $action->handle(
            $this->product,
            [
                'name' => $this->configName,
                'is_required' => $this->configRequired,
                'sort_order' => $definition?->sort_order ?? $this->product->configDefinitions()->count(),
            ],
            $options,
            $definition,
            $definition ? $this->configDefinitionVersion : null,
        );

        $this->resetConfigEditor();
    }

    public function editConfigGroup(int $definitionId): void
    {
        abort_unless($this->product, 422);

        $definition = ConfigDefinition::query()
            ->where('product_id', $this->product->id)
            ->with('options')
            ->findOrFail($definitionId);

        $this->configDefinitionId = $definition->id;
        $this->configDefinitionVersion = (int) $definition->version;
        $this->configName = $definition->name;
        $this->configRequired = (bool) $definition->is_required;
        $this->configOptions = $definition->options
            ->sortBy('sort_order')
            ->map(fn ($option) => [
                'id' => $option->id,
                'version' => (int) $option->version,
                'label' => $option->label,
                'component_product_id' => $option->component_product_id,
                'is_default' => (bool) $option->is_default,
            ])
            ->values()
            ->all();
    }

    public function addConfigOptionRow(): void
    {
        $this->configOptions[] = [
            'label' => '',
            'component_product_id' => null,
            'is_default' => false,
        ];
    }

    public function removeConfigOptionRow(int $index): void
    {
        unset($this->configOptions[$index]);
        $this->configOptions = array_values($this->configOptions);
    }

    public function cancelConfigEdit(): void
    {
        $this->resetConfigEditor();
    }

    private function resetConfigEditor(): void
    {
        $this->configDefinitionId = null;
        $this->configDefinitionVersion = 1;
        $this->configName = '';
        $this->configRequired = false;
        $this->configOptions = [];
    }

    public function render(): View
    {
        return view('livewire.pages.products.product-form', [
            'categories' => ProductCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'brands' => Brand::query()->where('is_active', true)->orderBy('name')->get(),
            'units' => Unit::query()->where('is_active', true)->orderBy('name')->get(),
            'variantGroups' => VariantGroup::query()->where('is_active', true)->orderBy('name')->get(),
            'setLines' => $this->product?->setComponents()->with('componentProduct')->get() ?? collect(),
            'configDefinitions' => $this->product?->configDefinitions()->with('options.componentProduct')->get() ?? collect(),
        ])->layout('layouts.app', [
            'pageTitle' => $this->product ? "Ürün · {$this->code}" : 'Yeni Ürün',
        ]);
    }
}
