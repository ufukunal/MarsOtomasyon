<?php

namespace App\Livewire\Pages\Pricing;

use App\Actions\Pricing\BulkAdjustPriceList;
use App\Actions\Pricing\SavePriceList;
use App\Actions\Pricing\SavePriceListItem;
use App\Models\Period\PriceList;
use App\Models\Period\Product;
use App\Livewire\Concerns\WithIdempotentMutations;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class PriceListDetail extends Component
{
    use WithIdempotentMutations;

    public ?PriceList $list = null;

    public string $name = '';

    public string $currency = 'TRY';

    public bool $vatIncluded = false;

    public bool $isDefault = false;

    public bool $isActive = true;

    public int $version = 1;

    public ?int $productId = null;

    public string $price = '0.0000';

    public ?string $validFrom = null;

    public ?string $validTo = null;

    public string $bulkPercent = '0';

    public ?int $itemId = null;

    public int $itemVersion = 1;

    public function mount(?PriceList $list = null): void
    {
        $this->seedMutationKeys(['saveList','addItem','bulkAdjust']);
        abort_unless(auth()->user()?->can('price_lists.view'), 403);

        $this->list = $list;

        if ($list) {
            $this->name = $list->name;
            $this->currency = $list->currency;
            $this->vatIncluded = (bool) $list->vat_included;
            $this->isDefault = (bool) $list->is_default;
            $this->isActive = (bool) $list->is_active;
            $this->version = (int) $list->version;
        }
    }

    public function saveList(SavePriceList $action): void
    {
        abort_unless(auth()->user()?->can($this->list ? 'price_lists.update' : 'price_lists.create'), 403);

        $this->validate([
            'name' => ['required', 'max:255'],
            'currency' => ['required', 'size:3'],
        ]);

        $this->list = $this->runPeriodMutation('saveList', fn () => $action->handle([
            'name' => $this->name,
            'currency' => $this->currency,
            'vat_included' => $this->vatIncluded,
            'is_default' => $this->isDefault,
            'is_active' => $this->isActive,
        ], $this->list, $this->version));

        $this->version = (int) $this->list->version;
    }

    public function addItem(SavePriceListItem $action): void
    {
        abort_unless($this->list && $this->productId, 422);

        $this->validate(['price' => ['required', 'decimal:0,4', 'min:0']]);

        $product = Product::query()->findOrFail($this->productId);

        $item = $this->itemId
            ? $this->list->items()->findOrFail($this->itemId)
            : null;

        $this->runPeriodMutation('addItem', fn () => $action->handle(
            $this->list,
            $product,
            $this->price,
            $this->validFrom,
            $this->validTo,
            $item,
            $item ? $this->itemVersion : null,
        ));

        $this->resetItemEditor();
    }

    public function editItem(int $itemId): void
    {
        abort_unless($this->list !== null, 422);

        $item = $this->list->items()->findOrFail($itemId);

        $this->itemId = $item->id;
        $this->itemVersion = (int) $item->version;
        $this->productId = $item->product_id;
        $this->price = (string) $item->price;
        $this->validFrom = $item->valid_from?->toDateString();
        $this->validTo = $item->valid_to?->toDateString();
    }

    public function cancelItemEdit(): void
    {
        $this->resetItemEditor();
    }

    private function resetItemEditor(): void
    {
        $this->itemId = null;
        $this->itemVersion = 1;
        $this->productId = null;
        $this->price = '0.0000';
        $this->validFrom = null;
        $this->validTo = null;
    }

    public function bulkAdjust(BulkAdjustPriceList $action): void
    {
        abort_unless($this->list !== null, 422);
        $this->validate(['bulkPercent' => ['required', 'decimal:0,4']]);

        $this->runPeriodMutation('bulkAdjust', fn () => $action->handle($this->list, $this->bulkPercent));
        $this->bulkPercent = '0';
    }

    public function render(): View
    {
        return view('livewire.pages.pricing.price-list-detail', [
            'products' => Product::query()->where('is_active', true)->orderBy('name')->limit(500)->get(),
            'items' => $this->list?->items()->with('product')->orderBy('product_id')->get() ?? collect(),
        ])->layout('layouts.app', ['pageTitle' => $this->list ? "Fiyat Listesi · {$this->name}" : 'Yeni Fiyat Listesi']);
    }
}
