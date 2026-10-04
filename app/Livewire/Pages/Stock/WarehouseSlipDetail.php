<?php

namespace App\Livewire\Pages\Stock;

use App\Actions\Stock\PostWarehouseSlip;
use App\Actions\Stock\ReverseWarehouseSlip;
use App\Actions\Stock\SaveWarehouseSlipDraft;
use App\Exceptions\CostDeviationConfirmationRequiredException;
use App\Models\Period\Location;
use App\Models\Period\Product;
use App\Models\Period\WarehouseSlip;
use Carbon\CarbonImmutable;
use App\Livewire\Concerns\WithIdempotentMutations;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Component;

class WarehouseSlipDetail extends Component
{
    use WithIdempotentMutations;

    public ?WarehouseSlip $slip = null;

    public ?int $locationId = null;

    public string $slipDate = '';

    public string $direction = 'out';

    public string $reason = 'adjustment';

    public string $note = '';

    /** @var list<array{product_id:int|null,quantity:string,unit_cost:?string,note:?string}> */
    public array $draftLines = [];

    /** @var list<string> */
    public array $costWarnings = [];

    public bool $acceptCostDeviation = false;

    public string $postKey = '';

    public string $reverseKey = '';

    public function mount(?int $id = null): void
    {
        $this->seedMutationKeys(['saveDraft']);
        abort_unless(auth()->user()?->can('warehouse_slips.view'), 403);

        $this->refreshKeys();
        $this->slipDate = now()->toDateString();

        if ($id !== null) {
            $this->slip = WarehouseSlip::query()->with('location')->findOrFail($id);
            $this->loadSlip();

            return;
        }

        abort_unless(auth()->user()->can('warehouse_slips.create'), 403);
        $this->addLine();
    }

    public function updatedDirection(): void
    {
        $this->reason = 'adjustment';

        if ($this->direction === 'out') {
            foreach ($this->draftLines as $index => $line) {
                $this->draftLines[$index]['unit_cost'] = null;
            }
        }
    }

    public function addLine(): void
    {
        abort_unless($this->slip === null || $this->slip->status === 'draft', 422);

        $this->draftLines[] = [
            'product_id' => null,
            'quantity' => '1.000',
            'unit_cost' => null,
            'note' => null,
        ];
    }

    public function removeLine(int $index): void
    {
        abort_unless($this->slip === null || $this->slip->status === 'draft', 422);
        unset($this->draftLines[$index]);
        $this->draftLines = array_values($this->draftLines);
    }

    public function saveDraft(SaveWarehouseSlipDraft $action): void
    {
        $this->validate([
            'locationId' => ['required', 'integer'],
            'slipDate' => ['required', 'date'],
            'direction' => ['required', 'in:in,out'],
            'reason' => ['required', 'string', 'max:30'],
            'draftLines' => ['required', 'array', 'min:1'],
            'draftLines.*.product_id' => ['required', 'integer'],
            'draftLines.*.quantity' => ['required', 'decimal:0,3'],
            'draftLines.*.unit_cost' => ['nullable', 'decimal:0,4'],
        ]);

        $this->slip = $this->runPeriodMutation('saveDraft', fn () => $action->handle([
            'location_id' => (int) $this->locationId,
            'slip_date' => $this->slipDate,
            'direction' => $this->direction,
            'reason' => $this->reason,
            'note' => $this->note !== '' ? $this->note : null,
            'lines' => array_map(
                fn (array $line): array => [
                    'product_id' => (int) $line['product_id'],
                    'quantity' => (string) $line['quantity'],
                    'unit_cost' => $line['unit_cost'] !== null && $line['unit_cost'] !== ''
                        ? (string) $line['unit_cost']
                        : null,
                    'note' => $line['note'] !== null && $line['note'] !== ''
                        ? (string) $line['note']
                        : null,
                ],
                $this->draftLines,
            ),
        ], $this->slip));

        $this->loadSlip();

        if (request()->routeIs('stock.warehouse-slips.create')) {
            $this->redirectRoute(
                'stock.warehouse-slips.show',
                ['id' => $this->slip->id],
                navigate: false,
            );
        }
    }

    public function post(PostWarehouseSlip $action): void
    {
        abort_unless($this->slip !== null, 422);
        $this->costWarnings = [];

        try {
            $this->slip = $action->handle(
                $this->slip->id,
                $this->postKey,
                $this->acceptCostDeviation,
            );
        } catch (CostDeviationConfirmationRequiredException $exception) {
            $this->costWarnings = $exception->warnings;
            $this->acceptCostDeviation = true;

            return;
        }

        $this->acceptCostDeviation = false;
        $this->refreshKeys();
        $this->loadSlip();
    }

    public function reverse(ReverseWarehouseSlip $action): void
    {
        abort_unless($this->slip !== null, 422);

        $this->slip = $action->handle($this->slip->id, $this->reverseKey);
        $this->refreshKeys();
        $this->loadSlip();
    }

    public function render(): View
    {
        $canViewCost = auth()->user()?->can('cost.view') ?? false;
        $postedLines = collect();

        if ($this->slip) {
            $columns = ['id', 'warehouse_slip_id', 'product_id', 'quantity', 'note'];

            if ($canViewCost) {
                $columns[] = 'unit_cost';
            }

            $postedLines = $this->slip->lines()
                ->select($columns)
                ->with('product')
                ->orderBy('id')
                ->get();
        }

        return view('livewire.pages.stock.warehouse-slip-detail', [
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(),
            'products' => Product::query()
                ->where('is_active', true)
                ->where('kind', '!=', 'set')
                ->orderBy('name')
                ->limit(1000)
                ->get(),
            'postedLines' => $postedLines,
            'canViewCost' => $canViewCost,
            'reasonOptions' => $this->direction === 'in'
                ? [
                    'found' => 'Buluntu',
                    'adjustment' => 'Düzeltme',
                    'sample_return' => 'Numune İadesi',
                    'opening' => 'Açılış',
                ]
                : [
                    'scrap' => 'Fire',
                    'broken' => 'Kırık',
                    'sample_issue' => 'Numune Verme',
                    'fixed_asset' => 'Demirbaş',
                    'adjustment' => 'Düzeltme',
                ],
        ])->layout('layouts.app', [
            'pageTitle' => $this->slip
                ? 'Ambar Fişi · '.($this->slip->number ?: 'Taslak #'.$this->slip->id)
                : 'Yeni Ambar Fişi',
        ]);
    }

    private function loadSlip(): void
    {
        if (! $this->slip) {
            return;
        }

        $this->slip->refresh()->load('location');
        $this->locationId = $this->slip->location_id;
        $this->slipDate = CarbonImmutable::parse((string) $this->slip->slip_date)->toDateString();
        $this->direction = $this->slip->direction;
        $this->reason = $this->slip->reason;
        $this->note = (string) ($this->slip->note ?? '');

        $canViewCost = auth()->user()?->can('cost.view') ?? false;
        $columns = ['id', 'warehouse_slip_id', 'product_id', 'quantity', 'note'];

        if ($canViewCost) {
            $columns[] = 'unit_cost';
        }

        $lines = $this->slip->lines()
            ->select($columns)
            ->orderBy('id')
            ->get();

        $this->draftLines = $lines
            ->map(fn ($line): array => [
                'product_id' => $line->product_id,
                'quantity' => (string) $line->quantity,
                'unit_cost' => $canViewCost && $line->unit_cost !== null ? (string) $line->unit_cost : null,
                'note' => $line->note !== null ? (string) $line->note : null,
            ])
            ->values()
            ->all();
    }

    private function refreshKeys(): void
    {
        $this->postKey = (string) Str::uuid();
        $this->reverseKey = (string) Str::uuid();
    }
}
