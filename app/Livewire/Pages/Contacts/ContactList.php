<?php

namespace App\Livewire\Pages\Contacts;

use App\Livewire\Components\DataTable\Column;
use App\Livewire\Components\DataTable\DataTableComponent;
use App\Livewire\Components\DataTable\DateRangeFilter;
use App\Livewire\Components\DataTable\SelectFilter;
use App\Models\Period\Contact;
use App\Models\Period\ContactCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/** @extends DataTableComponent<Contact> */
class ContactList extends DataTableComponent
{
    public string $model = Contact::class;

    public function mount(): void
    {
        $this->authorize('viewAny', Contact::class);
    }

    /** @return Builder<Contact> */
    protected function baseQuery(): Builder
    {
        return Contact::query()->with(['people', 'categories']);
    }

    /** @return list<Column> */
    public function columns(): array
    {
        return [
            Column::make('code', 'Kod')->searchable()->sortable(),
            Column::make('title', 'Unvan')->searchable()->sortable(),
            Column::make('primary_contact_name', 'Yetkili'),
            Column::make('category_names', 'Kategori'),
            Column::make('city', 'İl')->searchable()->sortable(),
            Column::make('balance_display', 'Bakiye (TRY)')->money(),
            Column::make('risk_status', 'Risk'),
            Column::make('is_active', 'Durum')->sortable(),
        ];
    }

    /** @return list<SelectFilter|DateRangeFilter> */
    public function filters(): array
    {
        $categories = ContactCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        $cities = Contact::query()
            ->whereNotNull('city')
            ->where('city', '<>', '')
            ->distinct()
            ->orderBy('city')
            ->pluck('city', 'city')
            ->all();

        return [
            SelectFilter::make('category_id', 'Kategori')->options($categories),
            SelectFilter::make('city', 'İl')->options($cities),
            SelectFilter::make('is_active', 'Durum')->options([1 => 'Aktif', 0 => 'Pasif']),
            SelectFilter::make('risk_exceeded', 'Risk')->options([1 => 'Limit aşanlar']),
        ];
    }

    /** @param Builder<Contact> $query */
    protected function applyFilter(Builder $query, mixed $filter, mixed $value): void
    {
        if ($filter instanceof SelectFilter && $filter->key === 'category_id') {
            $query->whereHas('categories', fn (Builder $q) => $q->whereKey((int) $value));

            return;
        }

        if ($filter instanceof SelectFilter && $filter->key === 'risk_exceeded') {
            if (! Schema::connection('period')->hasTable('contact_transactions')) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->whereRaw(
                "(SELECT COALESCE(SUM(CASE WHEN direction = 'debit' THEN amount ELSE -amount END), 0)
                  FROM contact_transactions ct WHERE ct.contact_id = contacts.id AND ct.currency = 'TRY') > contacts.risk_limit"
            );

            return;
        }

        parent::applyFilter($query, $filter, $value);
    }

    /** @return array<string,string|int> */
    public function overviewStats(): array
    {
        $counts = Contact::query()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COUNT(*) FILTER (WHERE is_active = true) AS active_count')
            ->selectRaw('COUNT(*) FILTER (WHERE is_active = false) AS inactive_count')
            ->selectRaw("COUNT(DISTINCT NULLIF(city, '')) AS city_count")
            ->first();

        $debit = '0.0000';
        $credit = '0.0000';

        if (Schema::connection('period')->hasTable('contact_transactions')) {
            $netByContact = DB::connection('period')->table('contact_transactions')
                ->where('currency', 'TRY')
                ->groupBy('contact_id')
                ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'debit' THEN amount ELSE -amount END),0)::text AS net")
                ->get();

            foreach ($netByContact as $row) {
                $balance = (string) $row->net;
                if (bccomp($balance, '0', 4) > 0) {
                    $debit = bcadd($debit, $balance, 4);
                } elseif (bccomp($balance, '0', 4) < 0) {
                    $credit = bcadd($credit, bcmul($balance, '-1', 4), 4);
                }
            }
        }

        return [
            'total' => (int) ($counts?->total ?? 0),
            'active' => (int) ($counts?->active_count ?? 0),
            'inactive' => (int) ($counts?->inactive_count ?? 0),
            'cities' => (int) ($counts?->city_count ?? 0),
            // Positive net debits are receivables for the company (alacak).
            // Negative net balances are payables (borç).
            'debit' => $credit,
            'credit' => $debit,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function rowActions(): array
    {
        $actions = [['label' => 'Aç', 'method' => 'showContact']];

        if (auth()->user()?->can('contacts.update')) {
            $actions[] = ['label' => 'Düzenle', 'method' => 'editContact'];
        }

        return $actions;
    }

    public function columnUrl(Model $row, Column $column): ?string
    {
        return in_array($column->key, ['code', 'title'], true)
            ? route('contacts.show', ['contact' => $row->getKey()])
            : null;
    }

    public function showContact(int|string $id): mixed
    {
        abort_unless(auth()->user()?->can('contacts.view'), 403);

        return $this->redirectRoute('contacts.show', ['contact' => $id], navigate: false);
    }

    public function editContact(int|string $id): mixed
    {
        return $this->redirectRoute('contacts.edit', ['contact' => $id], navigate: false);
    }

    /** @return array<string, mixed>|null */
    public function emptyAction(): ?array
    {
        return auth()->user()?->can('contacts.create')
            ? ['label' => 'Yeni Cari', 'route' => 'contacts.create']
            : null;
    }
}
