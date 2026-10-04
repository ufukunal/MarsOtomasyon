<?php

namespace App\Livewire\Pages\Stock;

use App\Livewire\Components\DataTable\Column;
use App\Livewire\Components\DataTable\DataTableComponent;
use App\Livewire\Components\DataTable\DateRangeFilter;
use App\Livewire\Components\DataTable\SelectFilter;
use App\Models\Period\Transfer;
use Illuminate\Database\Eloquent\Builder;

/** @extends DataTableComponent<Transfer> */
class TransferList extends DataTableComponent
{
    public string $model = Transfer::class;

    public string $sort = 'transfer_date';

    public string $direction = 'desc';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('transfers.view'), 403);
    }

    /** @return Builder<Transfer> */
    protected function baseQuery(): Builder
    {
        return Transfer::query()
            ->join('locations as source', 'source.id', '=', 'transfers.from_location_id')
            ->join('locations as target', 'target.id', '=', 'transfers.to_location_id')
            ->select([
                'transfers.*',
                'source.name as from_location_name',
                'target.name as to_location_name',
            ])
            ->selectSub(
                fn ($query) => $query
                    ->from('transfer_lines')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('transfer_lines.transfer_id', 'transfers.id'),
                'line_count',
            );
    }

    /** @return list<Column> */
    public function columns(): array
    {
        return [
            Column::make('number', 'Numara')->searchable()->sortable(),
            Column::make('from_location_name', 'Kaynak'),
            Column::make('to_location_name', 'Hedef'),
            Column::make('transfer_date', 'Tarih')->sortable(),
            Column::make('status', 'Durum')->sortable(),
            Column::make('line_count', 'Satır')->alignEnd(),
        ];
    }

    /** @return list<SelectFilter|DateRangeFilter> */
    public function filters(): array
    {
        return [
            DateRangeFilter::make('transfer_date', 'Tarih'),
            SelectFilter::make('status', 'Durum')->options([
                'draft' => 'Taslak',
                'in_transit' => 'Yolda',
                'partially_received' => 'Kısmi Teslim',
                'received' => 'Teslim Alındı',
                'cancelled' => 'İptal',
            ]),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function rowActions(): array
    {
        return [['label' => 'Detay', 'method' => 'openTransfer']];
    }

    public function openTransfer(int|string $id): mixed
    {
        return $this->redirectRoute('stock.transfers.show', ['id' => $id], navigate: false);
    }

    /** @return array<string, mixed>|null */
    public function emptyAction(): ?array
    {
        return auth()->user()?->can('transfers.create')
            ? ['label' => 'Yeni Transfer', 'route' => 'stock.transfers.create']
            : null;
    }
}
