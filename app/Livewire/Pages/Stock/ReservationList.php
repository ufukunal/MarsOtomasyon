<?php

namespace App\Livewire\Pages\Stock;

use App\Actions\Stock\ReleaseReservation;
use App\Livewire\Components\DataTable\Column;
use App\Livewire\Concerns\WithIdempotentMutations;
use App\Livewire\Components\DataTable\DataTableComponent;
use App\Livewire\Components\DataTable\SelectFilter;
use App\Models\Period\StockReservation;
use Illuminate\Database\Eloquent\Builder;

/** @extends DataTableComponent<StockReservation> */
class ReservationList extends DataTableComponent
{
    use WithIdempotentMutations;

    public string $model = StockReservation::class;

    public string $sort = 'created_at';

    public string $direction = 'desc';

    public function mount(): void
    {
        $this->seedMutationKeys(['releaseReservation']);
        abort_unless(auth()->user()?->can('reservations.view'), 403);
    }

    /** @return Builder<StockReservation> */
    protected function baseQuery(): Builder
    {
        return StockReservation::query()
            ->join('products as p', 'p.id', '=', 'stock_reservations.product_id')
            ->join('locations as l', 'l.id', '=', 'stock_reservations.location_id')
            ->select([
                'stock_reservations.*',
                'p.code as product_code',
                'p.name as product_name',
                'l.name as location_name',
            ]);
    }

    /** @return list<Column> */
    public function columns(): array
    {
        return [
            Column::make('product_code', 'Kod')->searchable()->sortable(),
            Column::make('product_name', 'Ürün')->searchable()->sortable(),
            Column::make('location_name', 'Lokasyon')->sortable(),
            Column::make('quantity', 'Miktar')->quantity()->sortable(),
            Column::make('document_type', 'Belge Türü')->sortable(),
            Column::make('document_id', 'Belge')->sortable(),
            Column::make('document_line_id', 'Satır')->sortable(),
            Column::make('status', 'Durum')->sortable(),
            Column::make('created_at', 'Oluşturma')->sortable(),
        ];
    }

    /** @return list<SelectFilter> */
    public function filters(): array
    {
        return [
            SelectFilter::make('status', 'Durum')->options([
                'active' => 'Aktif',
                'released' => 'Çözüldü',
                'consumed' => 'Tüketildi',
            ]),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function rowActions(): array
    {
        if (! auth()->user()?->can('reservations.update')) {
            return [];
        }

        return [['label' => 'Rezervi Çöz', 'method' => 'releaseReservation']];
    }

    public function releaseReservation(int|string $id, ReleaseReservation $action): void
    {
        $reservation = StockReservation::query()->findOrFail((int) $id);

        if ($reservation->status !== 'active') {
            return;
        }

        $action->handle($reservation->id, $this->mutationKey('releaseReservation'));
        $this->completeMutation('releaseReservation');
    }
}
