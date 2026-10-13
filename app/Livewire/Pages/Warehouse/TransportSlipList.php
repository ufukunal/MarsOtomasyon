<?php

namespace App\Livewire\Pages\Warehouse;

use App\Models\Period\TransportSlip;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class TransportSlipList extends Component
{
    use WithPagination;

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

        $query = TransportSlip::query()->with('contact')->orderByDesc('slip_date')->orderByDesc('id');

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
