<?php

namespace App\Livewire\Pages\Stock;

use App\Actions\Stock\CancelTransfer;
use App\Actions\Stock\ReceiveTransfer;
use App\Actions\Stock\SaveTransferDraft;
use App\Actions\Stock\SendTransfer;
use App\Models\Period\Location;
use App\Models\Period\Product;
use App\Models\Period\Transfer;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Component;

class TransferDetail extends Component
{
    public ?Transfer $transfer = null;

    public ?int $fromLocationId = null;

    public ?int $toLocationId = null;

    public string $transferDate = '';

    public string $note = '';

    /** @var list<array{product_id:int|null,quantity:string}> */
    public array $draftLines = [];

    /** @var array<int, string> */
    public array $receiveQuantities = [];

    public string $sendKey = '';

    public string $receiveKey = '';

    public string $cancelKey = '';

    public function mount(?int $id = null): void
    {
        abort_unless(auth()->user()?->can('transfers.view'), 403);

        $this->refreshKeys();
        $this->transferDate = now()->toDateString();

        if ($id !== null) {
            $this->transfer = Transfer::query()
                ->with(['fromLocation', 'toLocation', 'lines.product'])
                ->findOrFail($id);
            $this->loadTransfer();

            return;
        }

        abort_unless(auth()->user()->can('transfers.create'), 403);
        $this->addLine();
    }

    public function addLine(): void
    {
        abort_unless($this->transfer === null || $this->transfer->status === 'draft', 422);

        $this->draftLines[] = [
            'product_id' => null,
            'quantity' => '1.000',
        ];
    }

    public function removeLine(int $index): void
    {
        abort_unless($this->transfer === null || $this->transfer->status === 'draft', 422);
        unset($this->draftLines[$index]);
        $this->draftLines = array_values($this->draftLines);
    }

    public function saveDraft(SaveTransferDraft $action): void
    {
        $this->validate([
            'fromLocationId' => ['required', 'integer'],
            'toLocationId' => ['required', 'integer', 'different:fromLocationId'],
            'transferDate' => ['required', 'date'],
            'draftLines' => ['required', 'array', 'min:1'],
            'draftLines.*.product_id' => ['required', 'integer'],
            'draftLines.*.quantity' => ['required', 'decimal:0,3'],
        ]);

        $this->transfer = $action->handle([
            'from_location_id' => (int) $this->fromLocationId,
            'to_location_id' => (int) $this->toLocationId,
            'transfer_date' => $this->transferDate,
            'note' => $this->note !== '' ? $this->note : null,
            'lines' => array_map(
                fn (array $line): array => [
                    'product_id' => (int) $line['product_id'],
                    'quantity' => (string) $line['quantity'],
                ],
                $this->draftLines,
            ),
        ], $this->transfer);

        $this->loadTransfer();

        if (request()->routeIs('stock.transfers.create')) {
            $this->redirectRoute(
                'stock.transfers.show',
                ['id' => $this->transfer->id],
                navigate: false,
            );
        }
    }

    public function send(SendTransfer $action): void
    {
        abort_unless($this->transfer !== null, 422);

        $this->transfer = $action->handle($this->transfer->id, $this->sendKey);
        $this->refreshKeys();
        $this->loadTransfer();
    }

    public function receive(ReceiveTransfer $action): void
    {
        abort_unless($this->transfer !== null, 422);

        $this->transfer = $action->handle(
            $this->transfer->id,
            $this->receiveQuantities,
            $this->receiveKey,
        );
        $this->refreshKeys();
        $this->loadTransfer();
    }

    public function cancel(CancelTransfer $action): void
    {
        abort_unless($this->transfer !== null, 422);

        $this->transfer = $action->handle($this->transfer->id, $this->cancelKey);
        $this->refreshKeys();
        $this->loadTransfer();
    }

    public function render(): View
    {
        return view('livewire.pages.stock.transfer-detail', [
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(),
            'products' => Product::query()
                ->where('is_active', true)
                ->where('kind', '!=', 'set')
                ->orderBy('name')
                ->limit(1000)
                ->get(),
            'postedLines' => $this->transfer?->lines()->with('product')->orderBy('id')->get() ?? collect(),
        ])->layout('layouts.app', [
            'pageTitle' => $this->transfer
                ? 'Transfer · '.($this->transfer->number ?: 'Taslak #'.$this->transfer->id)
                : 'Yeni Transfer',
        ]);
    }

    private function loadTransfer(): void
    {
        if (! $this->transfer) {
            return;
        }

        $this->transfer->refresh()->load(['fromLocation', 'toLocation', 'lines.product']);
        $this->fromLocationId = $this->transfer->from_location_id;
        $this->toLocationId = $this->transfer->to_location_id;
        $this->transferDate = CarbonImmutable::parse((string) $this->transfer->transfer_date)->toDateString();
        $this->note = (string) ($this->transfer->note ?? '');
        $this->draftLines = $this->transfer->lines
            ->map(fn ($line): array => [
                'product_id' => $line->product_id,
                'quantity' => (string) $line->quantity,
            ])
            ->values()
            ->all();
        $this->receiveQuantities = $this->transfer->lines
            ->mapWithKeys(fn ($line): array => [$line->id => '0.000'])
            ->all();
    }

    private function refreshKeys(): void
    {
        $this->sendKey = (string) Str::uuid();
        $this->receiveKey = (string) Str::uuid();
        $this->cancelKey = (string) Str::uuid();
    }
}
