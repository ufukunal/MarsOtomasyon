<?php

namespace App\Livewire\Pages\Contacts;

use App\Livewire\Components\DataTable\Column;
use App\Livewire\Components\DataTable\DataTableComponent;
use App\Livewire\Components\DataTable\DateRangeFilter;
use App\Livewire\Components\DataTable\SelectFilter;
use App\Models\Period\Contact;
use App\Models\Period\ContactCategory;
use App\Models\Period\ContactTransaction;
use App\Livewire\Components\DataTable\TextFilter;
use App\Support\Formatting\TrFormatter;
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
        $query = Contact::query()->with(['people', 'categories'])->select('contacts.*');

        // One scalar subquery per page instead of extra per-row ledger requests.
        if (Schema::connection('period')->hasTable('contact_transactions')) {
            $query->selectSub(
                ContactTransaction::query()
                    ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'debit' THEN amount ELSE -amount END), 0)")
                    ->whereColumn('contact_id', 'contacts.id')
                    ->where('currency', 'TRY'),
                'v65_try_net',
            )->selectSub(
                ContactTransaction::query()
                    ->selectRaw('MAX(transaction_date)')
                    ->whereColumn('contact_id', 'contacts.id'),
                'v65_last_movement',
            );
        }

        return $query;
    }

    /** @return list<Column> */
    public function columns(): array
    {
        return [
            Column::make('code', 'Cari Kodu')->searchable()->sortable(),
            Column::make('title', 'Ünvan')->searchable()->sortable(),
            Column::make('primary_contact_name', 'Yetkili'),
            Column::make('category_names', 'Tip'),
            Column::make('city_district', 'İl / İlçe'),
            Column::make('phone', 'Telefon')->searchable(),
            Column::make('tax_number', 'Vergi No')->searchable(),
            Column::make('balance_display', 'Bakiye'),
            Column::make('risk_usage', 'Risk Kullanımı'),
            Column::make('last_movement', 'Son Hareket'),
            Column::make('is_active', 'Durum')->sortable(),
        ];
    }

    /** @return list<SelectFilter|DateRangeFilter|TextFilter> */
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
            TextFilter::make('code', 'Cari Kodu'),
            TextFilter::make('title', 'Ünvan'),
            TextFilter::make('phone', 'Telefon'),
            TextFilter::make('tax_number', 'Vergi No'),
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

            $query->where('contacts.risk_limit', '>', 0)
                ->whereRaw(
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

        // A mixed-role cari is counted in both groups, but only once in total.
        $customers = Contact::query()->whereHas('categories', fn (Builder $q) =>
            $q->where('name', 'ilike', 'Müşteri'))->count();
        $suppliers = Contact::query()->whereHas('categories', fn (Builder $q) =>
            $q->where('name', 'ilike', 'Tedarikçi'))->count();

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
            'customers' => $customers,
            'suppliers' => $suppliers,
            'active' => (int) ($counts?->active_count ?? 0),
            'inactive' => (int) ($counts?->inactive_count ?? 0),
            'cities' => (int) ($counts?->city_count ?? 0),
            // Positive net debits are receivables for the company (alacak).
            // Negative net balances are payables (borç).
            'debit' => $credit,
            'credit' => $debit,
        ];
    }

    public function formattedValue(Model $row, Column $column): string
    {
        if (! $row instanceof Contact) {
            return parent::formattedValue($row, $column);
        }

        if ($column->key === 'category_names') {
            $roles = $row->categories->pluck('name')
                ->map(fn (string $name): string => mb_strtolower(trim($name)));
            if ($roles->contains('müşteri') && $roles->contains('tedarikçi')) {
                return 'Müşteri + Tedarikçi';
            }

            return $row->getCategoryNamesAttribute() ?: '—';
        }

        if ($column->key === 'city_district') {
            return collect([$row->city, $row->district])
                ->filter(fn ($part): bool => filled($part))->implode(' / ') ?: '—';
        }

        if (in_array($column->key, ['balance_display', 'risk_usage'], true)) {
            $balance = (string) ($row->getAttribute('v65_try_net') ?? $row->balance());
        }

        if ($column->key === 'balance_display') {
            $absolute = bccomp($balance, '0', 4) < 0 ? bcmul($balance, '-1', 4) : $balance;

            return TrFormatter::money($absolute).' ₺'.match (bccomp($balance, '0', 4)) {
                1 => ' Alacaklı',
                -1 => ' Borçlu',
                default => '',
            };
        }

        if ($column->key === 'risk_usage') {
            $limit = (string) ($row->risk_limit ?? '0');
            if (bccomp($limit, '0', 4) <= 0) {
                return '—';
            }

            $receivable = bccomp($balance, '0', 4) > 0 ? $balance : '0';

            return TrFormatter::money(bcdiv(bcmul($receivable, '100', 4), $limit, 4), 0).'%';
        }

        if ($column->key === 'last_movement') {
            $date = $row->getAttribute('v65_last_movement');

            return $date ? \Illuminate\Support\Carbon::parse($date)->format('d.m.Y') : '—';
        }

        if ($column->key === 'is_active') {
            return $row->is_active ? 'Aktif' : 'Pasif';
        }

        return parent::formattedValue($row, $column) ?: '—';
    }

    public function toggleVisibleContacts(): void
    {
        $ids = collect($this->rows()->items())
            ->map(fn (Contact $contact): string => (string) $contact->getKey())
            ->all();
        $selected = array_map('strval', $this->selected);
        $allSelected = $ids !== [] && count(array_diff($ids, $selected)) === 0;

        $this->selected = $allSelected
            ? array_values(array_diff($selected, $ids))
            : array_values(array_unique(array_merge($selected, $ids)));
    }

    /** @return list<array<string, mixed>> */
    public function rowActions(): array
    {
        // Records open via their linked identifiers, not redundant row buttons.
        return [];
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
