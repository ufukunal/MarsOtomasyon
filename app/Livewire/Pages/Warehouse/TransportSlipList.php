<?php

namespace App\Livewire\Pages\Warehouse;

use App\Livewire\Concerns\WithV65FilterDrawer;
use App\Models\Period\TransportSlip;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class TransportSlipList extends Component
{
    use WithPagination;
    use WithV65FilterDrawer;

    #[Url(as: 'ara')]
    public string $search = '';

    #[Url(as: 'liste')]
    public string $tableSearch = '';

    #[Url(as: 'durum')]
    public string $status = '';

    #[Url(as: 'tasiyici')]
    public string $carrier = '';

    #[Url(as: 'cam')]
    public string $glass = '';

    #[Url(as: 'baslangic')]
    public string $dateFrom = '';

    #[Url(as: 'bitis')]
    public string $dateTo = '';

    public int $perPage = 25;

    public string $v65WarehouseSort = '';

    public string $v65WarehouseDirection = 'desc';

    /** @return array{search:string,filters:array<string,mixed>,sort:string,direction:string} */
    protected function v65ReadFilterState(): array
    {
        return [
            'search' => $this->search !== '' ? $this->search : $this->tableSearch,
            'filters' => [
                'status' => $this->status,
                'carrier' => $this->carrier,
                'glass' => $this->glass,
                'date' => ['from' => $this->dateFrom, 'to' => $this->dateTo],
            ],
            'sort' => $this->v65WarehouseSort,
            'direction' => $this->v65WarehouseDirection,
        ];
    }

    /** @param array{search:string,filters:array<string,mixed>,sort:string,direction:string} $state */
    protected function v65WriteFilterState(array $state): void
    {
        $this->search = $state['search'];
        $this->tableSearch = '';
        $this->status = (string) ($state['filters']['status'] ?? '');
        $this->carrier = (string) ($state['filters']['carrier'] ?? '');
        $this->glass = (string) ($state['filters']['glass'] ?? '');
        $this->dateFrom = (string) ($state['filters']['date']['from'] ?? '');
        $this->dateTo = (string) ($state['filters']['date']['to'] ?? '');
        $this->v65WarehouseSort = $state['sort'];
        $this->v65WarehouseDirection = $state['direction'];
    }

    /** @return array<string,array{label:string,type:string,options?:array<int|string,string>}> */
    protected function v65FilterDefinitions(): array
    {
        return [
            'status' => ['label' => 'Durum', 'type' => 'select', 'options' => [
                'draft' => 'Taslak', 'ready' => 'Hazır', 'handed_over' => 'Ambara Teslim',
                'completed' => 'Tamamlandı', 'cancelled' => 'İptal',
            ]],
            'carrier' => ['label' => 'Ambar / Nakliye', 'type' => 'select',
                'options' => TransportSlip::query()->where('carrier', '!=', '')
                    ->distinct()->orderBy('carrier')->pluck('carrier', 'carrier')->all()],
            'glass' => ['label' => 'Cam Durumu', 'type' => 'select',
                'options' => ['yes' => 'Cam Var', 'no' => 'Cam Yok']],
            'date' => ['label' => 'Fiş Tarihi', 'type' => 'date-range'],
        ];
    }

    /** @return array<string,string> */
    protected function v65SortOptions(): array
    {
        return ['slip_date' => 'Tarih', 'number' => 'Fiş Numarası', 'carrier' => 'Ambar / Nakliye', 'status' => 'Durum'];
    }

    protected function v65DefaultSort(): string
    {
        return '';
    }

    protected function v65DefaultDirection(): string
    {
        return 'desc';
    }

    public function setPerPage(int $value): void
    {
        abort_unless(in_array($value, [25, 50, 100], true), 422);
        $this->perPage = $value;
        $this->resetPage();
    }

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('warehouse_slips.view'), 403);
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'tableSearch', 'status', 'carrier', 'glass', 'dateFrom', 'dateTo');
        $this->resetPage();
    }

    public function render(): View
    {
        abort_unless(auth()->user()?->can('warehouse_slips.view'), 403);

        $query = TransportSlip::query()->with('contact');

        if (trim($this->search) !== '') {
            $text = '%'.trim($this->search).'%';
            $query->where(function ($q) use ($text): void {
                $q->where('number', 'ilike', $text)
                    ->orWhere('carrier', 'ilike', $text)
                    ->orWhere('tracking_number', 'ilike', $text)
                    ->orWhereHas('contact', fn ($contacts) => $contacts->where('title', 'ilike', $text)
                        ->orWhere('code', 'ilike', $text));
            });
        }

        if (trim($this->tableSearch) !== '') {
            $needle = '%'.trim($this->tableSearch).'%';
            $query->where(function ($q) use ($needle): void {
                $q->where('number', 'ilike', $needle)
                    ->orWhere('carrier', 'ilike', $needle)
                    ->orWhere('tracking_number', 'ilike', $needle)
                    ->orWhereHas('contact', fn ($contacts) => $contacts
                        ->where('title', 'ilike', $needle));
            });
        }

        if (in_array($this->status, ['draft', 'ready', 'handed_over', 'completed', 'cancelled'], true)) {
            $query->where('status', $this->status);
        }
        if ($this->carrier !== '') {
            $query->where('carrier', $this->carrier);
        }
        if ($this->glass === 'yes' || $this->glass === 'no') {
            $query->where('contains_glass', $this->glass === 'yes');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->dateFrom)) {
            $query->whereDate('slip_date', '>=', $this->dateFrom);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->dateTo)) {
            $query->whereDate('slip_date', '<=', $this->dateTo);
        }

        if ($this->v65WarehouseSort !== '' && array_key_exists($this->v65WarehouseSort, $this->v65SortOptions())) {
            $query->orderBy($this->v65WarehouseSort, $this->v65WarehouseDirection === 'asc' ? 'asc' : 'desc')->orderByDesc('id');
        } else {
            $query->orderBy('slip_date', $this->v65WarehouseDirection === 'asc' ? 'asc' : 'desc')->orderByDesc('id');
        }

        return view('livewire.pages.warehouse.transport-slip-list', [
            'slips' => $query->paginate($this->perPage),
            'carriers' => TransportSlip::query()->where('carrier', '!=', '')->distinct()->orderBy('carrier')->pluck('carrier'),
            'statusLabels' => [
                'draft' => 'Taslak', 'ready' => 'Hazır', 'handed_over' => 'Ambara Teslim',
                'completed' => 'Tamamlandı', 'cancelled' => 'İptal',
            ],
        ])->layout('layouts.app', ['pageTitle' => 'Ambar Fişleri']);
    }
}
