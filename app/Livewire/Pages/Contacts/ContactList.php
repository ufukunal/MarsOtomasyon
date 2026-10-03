<?php

namespace App\Livewire\Pages\Contacts;

use App\Livewire\Components\DataTable\Column;
use App\Livewire\Components\DataTable\DataTableComponent;
use App\Livewire\Components\DataTable\SelectFilter;
use App\Models\Period\Contact;
use App\Models\Period\ContactCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class ContactList extends DataTableComponent
{
    public string $model = Contact::class;

    public function mount(): void
    {
        $this->authorize('viewAny', Contact::class);
    }

    protected function baseQuery(): Builder
    {
        return Contact::query()->with(['people', 'categories']);
    }

    public function columns(): array
    {
        return [
            Column::make('code', 'Kod')->searchable()->sortable(),
            Column::make('title', 'Unvan')->searchable()->sortable(),
            Column::make('primary_contact_name', 'Yetkili'),
            Column::make('category_names', 'Kategori'),
            Column::make('city', 'İl')->searchable()->sortable(),
            Column::make('balance_display', 'Bakiye')->money(),
            Column::make('risk_status', 'Risk'),
            Column::make('is_active', 'Durum')->sortable(),
        ];
    }

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
                  FROM contact_transactions ct WHERE ct.contact_id = contacts.id) > contacts.risk_limit"
            );
            return;
        }

        parent::applyFilter($query, $filter, $value);
    }

    public function rowActions(): array
    {
        return auth()->user()?->can('contacts.update')
            ? [['label' => 'Düzenle', 'method' => 'editContact']]
            : [];
    }

    public function editContact(int|string $id): mixed
    {
        return $this->redirectRoute('contacts.edit', ['contact' => $id], navigate: false);
    }

    public function emptyAction(): ?array
    {
        return auth()->user()?->can('contacts.create')
            ? ['label' => 'Yeni Cari', 'route' => 'contacts.create']
            : null;
    }
}
