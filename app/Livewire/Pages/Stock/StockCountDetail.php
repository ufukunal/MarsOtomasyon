<?php

namespace App\Livewire\Pages\Stock;

use App\Actions\Stock\PostStockCount;
use App\Actions\Stock\ReviewStockCount;
use App\Actions\Stock\SaveStockCountDraft;
use App\Actions\Stock\SaveStockCountLine;
use App\Actions\Stock\StartStockCount;
use App\Models\Period\Location;
use App\Models\Period\Product;
use App\Models\Period\StockCount;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Component;

class StockCountDetail extends Component
{
    public ?StockCount $count = null;

    public ?int $locationId = null;

    public string $countDate = '';

    public string $note = '';

    /** @var list<int> */
    public array $productIds = [];

    /** @var array<int,string> */
    public array $countedQuantities = [];

    /** @var array<int,string> */
    public array $lineNotes = [];

    /** @var array<int,bool> */
    public array $approved = [];

    public bool $differencesOnly = false;

    public string $startKey = '';

    public string $postKey = '';

    public function mount(?int $id = null): void
    {
        abort_unless(auth()->user()?->can('stock_counts.view'), 403);
        $this->refreshKeys();
        $this->countDate = now()->toDateString();

        if ($id !== null) {
            $this->count = StockCount::query()->with(['location', 'lines.product'])->findOrFail($id);
            $this->loadCount();

            return;
        }

        abort_unless(auth()->user()->can('stock_counts.create'), 403);
    }

    public function saveDraft(SaveStockCountDraft $action): void
    {
        $this->validate([
            'locationId' => ['required', 'integer'], 'countDate' => ['required', 'date'],
            'productIds' => ['required', 'array', 'min:1'], 'productIds.*' => ['integer'],
        ]);

        $this->count = $action->handle([
            'location_id' => (int) $this->locationId, 'count_date' => $this->countDate,
            'note' => $this->note !== '' ? $this->note : null, 'product_ids' => array_map('intval', $this->productIds),
        ], $this->count);
        $this->loadCount();

        if (request()->routeIs('stock.counts.create')) {
            $this->redirectRoute('stock.counts.show', ['id' => $this->count->id], navigate: false);
        }
    }

    public function start(StartStockCount $action): void
    {
        abort_unless($this->count !== null, 422);
        $this->count = $action->handle($this->count->id, $this->startKey);
        $this->refreshKeys();
        $this->loadCount();
    }

    public function saveLine(int $lineId, SaveStockCountLine $action): void
    {
        abort_unless($this->count !== null, 422);
        $action->handle(
            $this->count->id,
            $lineId,
            (string) ($this->countedQuantities[$lineId] ?? '0'),
            $this->lineNotes[$lineId] ?? null,
            $this->count->status === 'review' ? (bool) ($this->approved[$lineId] ?? false) : null,
        );
        $this->loadCount();
    }

    public function review(ReviewStockCount $action): void
    {
        abort_unless($this->count !== null, 422);
        $this->count = $action->handle($this->count->id);
        $this->loadCount();
    }

    public function post(PostStockCount $action): void
    {
        abort_unless($this->count !== null, 422);

        foreach ($this->count->lines as $line) {
            if ($line->counted_quantity !== null) {
                $this->saveLine($line->id, app(SaveStockCountLine::class));
            }
        }

        $this->count = $action->handle($this->count->id, $this->postKey);
        $this->refreshKeys();
        $this->loadCount();
    }

    public function render(): View
    {
        $lines = $this->count?->lines()->with('product')->orderBy('product_id');

        if ($lines && $this->differencesOnly) {
            $lines->where('difference', '<>', 0);
        }

        return view('livewire.pages.stock.stock-count-detail', [
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(),
            'products' => Product::query()->where('is_active', true)->where('kind', '!=', 'set')->orderBy('name')->limit(1000)->get(),
            'countLines' => $lines?->get() ?? collect(),
        ])->layout('layouts.app', ['pageTitle' => $this->count ? 'Stok Sayımı · '.($this->count->number ?: 'Taslak #'.$this->count->id) : 'Yeni Stok Sayımı']);
    }

    private function loadCount(): void
    {
        if (! $this->count) {
            return;
        }

        $this->count->refresh()->load(['location', 'lines.product']);
        $this->locationId = $this->count->location_id;
        $this->countDate = CarbonImmutable::parse((string) $this->count->count_date)->toDateString();
        $this->note = (string) ($this->count->note ?? '');
        $this->productIds = $this->count->lines->pluck('product_id')->map(fn ($id): int => (int) $id)->all();

        foreach ($this->count->lines as $line) {
            $this->countedQuantities[$line->id] = $line->counted_quantity !== null ? (string) $line->counted_quantity : '0.000';
            $this->lineNotes[$line->id] = (string) ($line->note ?? '');
            $this->approved[$line->id] = (bool) $line->is_approved;
        }
    }

    private function refreshKeys(): void
    {
        $this->startKey = (string) Str::uuid();
        $this->postKey = (string) Str::uuid();
    }
}
