<div @class(['data-table-shell', 'v65-products' => $this instanceof \App\Livewire\Pages\Products\ProductList, 'v65-stock-table' => str_starts_with(get_class($this), 'App\\Livewire\\Pages\\Stock\\') || $this instanceof \App\Livewire\Pages\Catalog\LocationList])>
    @php
        $stockListMeta = [
            \App\Livewire\Pages\Stock\StockStatus::class => ['Stok Durumu', 'Depo bazlı anlık stok, rezervasyon ve kullanılabilir miktarlar.'],
            \App\Livewire\Pages\Stock\StockMovements::class => ['Stok Hareketleri', 'Ürünlerin dönem içindeki giriş, çıkış ve belge hareketleri.'],
            \App\Livewire\Pages\Stock\StockCost::class => ['Stok Maliyeti', 'Yetkili kullanıcılar için güncel hareketli ortalama ve stok değeri.'],
            \App\Livewire\Pages\Stock\TransferList::class => ['Depo Transferleri', 'Depolar arası stok transferleri ve teslimat durumları.'],
            \App\Livewire\Pages\Stock\WarehouseSlipList::class => ['Stok Düzeltme Fişleri', 'Yalnızca fiziksel stok giriş ve çıkış düzeltmeleri.'],
            \App\Livewire\Pages\Stock\StockCountList::class => ['Stok Sayımları', 'Depo sayımları ve sistem–sayım farkları.'],
            \App\Livewire\Pages\Stock\StockCountResults::class => ['Stok Sayım Listesi', 'Kesinleşmiş sayımların ürün bazlı sistem, sayılan ve fark kayıtları.'],
            \App\Livewire\Pages\Stock\ReservationList::class => ['Rezervasyonlar', 'Ayrılan stoklar ve rezervasyonların güncel durumu.'],
            \App\Livewire\Pages\Catalog\LocationList::class => ['Depolar / Lokasyonlar', 'Stokların izlendiği depo ve lokasyon kartları.'],
        ];
        $stockMeta = $stockListMeta[get_class($this)] ?? null;
        $isProductTable = $this instanceof \App\Livewire\Pages\Products\ProductList;
        $activeRoute = \Illuminate\Support\Facades\Route::currentRouteName();
        $navigationItem = collect(config('navigation', []))
            ->flatMap(fn (array $group): array => $group['items'] ?? [])
            ->first(fn (array $item): bool => $item['route'] === $activeRoute);
        $listLabel = $stockMeta[0] ?? ($navigationItem['label'] ?? 'Kayıt Listesi');
    @endphp
    @php($visibleColumnCount = collect($columns)->filter(fn ($column): bool => ! ($hiddenColumns[$column->key] ?? false))->count())
    @if($isProductTable)
        <div class="alert v65-products-hint"><strong>Kısaca:</strong> Ürün kartlarını, satış fiyatlarını ve stok durumunu buradan yönetin.</div>
    @elseif($this instanceof \App\Livewire\Pages\Stock\StockStatus && !($this instanceof \App\Livewire\Pages\Stock\StockCost))
        <div class="alert"><strong>Kısaca:</strong> Kullanılabilir stok, rezerve ve bloke miktarları ayrı gösterilir.</div>
    @endif
    @if($isProductTable)
        <div class="v65-products-list-heading">
            <div class="v65-products-list-intro">
                <strong>Ürün Listesi</strong>
                <small>Ürün kartları, fiyat ve stok özeti.</small>
            </div>
            <div class="v65-products-heading-actions">
                @if($emptyAction)
                    <a class="button-link v65-products-create" href="{{ route($emptyAction['route']) }}">+ {{ $emptyAction['label'] }}</a>
                @endif
                <details class="v65-products-export-menu">
                    <summary>Excel / CSV</summary>
                    <div class="v65-products-export-options">
                        <button type="button" wire:click="exportXlsx" data-v65-product-export-xlsx>Excel (.xlsx)</button>
                        <button type="button" wire:click="exportCsv" data-v65-product-export-csv>CSV (.csv)</button>
                    </div>
                </details>
            </div>
        </div>
    @endif
    <div class="table-toolbar">
        <div class="table-toolbar-main">
            <label class="table-search">
                <span class="sr-only">Tabloda ara</span>
                <span class="table-search-icon" aria-hidden="true">⌕</span>
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ $this instanceof \App\Livewire\Pages\Products\ProductList ? 'Ürün kodu / adı' : 'Tabloda ara…' }}">
            </label>

            @foreach ($filters as $filter)
                @if($isProductTable && in_array($filter->key, ['kind', 'channel_stock_mode'], true))
                    @continue
                @endif
                @if ($filter instanceof \App\Livewire\Components\DataTable\SelectFilter)
                    <label class="table-filter">
                        <span class="sr-only">{{ $filter->label }}</span>
                        <select wire:model.live="filterValues.{{ $filter->key }}">
                            <option value="">{{ $filter->label }} · Tümü</option>
                            @foreach ($filter->options as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                @elseif ($filter instanceof \App\Livewire\Components\DataTable\DateRangeFilter)
                    <div class="date-range-filter">
                        <span>{{ $filter->label }}</span>
                        <input type="date" wire:model.live="filterValues.{{ $filter->key }}.from" aria-label="{{ $filter->label }} başlangıç">
                        <input type="date" wire:model.live="filterValues.{{ $filter->key }}.to" aria-label="{{ $filter->label }} bitiş">
                    </div>
                @endif
            @endforeach
            @if($isProductTable)
                <details class="v65-products-filter-popover">
                    <summary>Filtrele</summary>
                    <div class="v65-products-filter-popover-body">
                        <strong>Diğer Filtreler</strong>
                        @foreach($filters as $filter)
                            @if($filter instanceof \App\Livewire\Components\DataTable\SelectFilter && in_array($filter->key, ['kind', 'channel_stock_mode'], true))
                                <label class="table-filter">
                                    <span>{{ $filter->label }}</span>
                                    <select wire:model.live="filterValues.{{ $filter->key }}">
                                        <option value="">{{ $filter->label }} · Tümü</option>
                                        @foreach($filter->options as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endif
                        @endforeach
                        <button type="button" class="button-secondary" wire:click="$refresh">Uygula</button>
                    </div>
                </details>
            @endif
        </div>
        @unless($isProductTable)
        <div class="table-toolbar-actions">
            <label class="page-size-control">
                <span>Satır</span>
                <select wire:change="setPerPage($event.target.value)">
                    @foreach ([25,50,100] as $size)
                        <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </label>
            <details class="column-picker">
                <summary>Kolonlar</summary>
                <div class="column-picker-menu">
                    @foreach($columns as $column)
                        <label>
                            <input type="checkbox"
                                   @checked(!($hiddenColumns[$column->key] ?? false))
                                   wire:click="toggleColumn('{{ $column->key }}')">
                            {{ $column->label }}
                        </label>
                    @endforeach
                </div>
            </details>
            @if($emptyAction)
                <a class="button-link table-add-action" href="{{ route($emptyAction['route']) }}">+ {{ $emptyAction['label'] }}</a>
            @endif
            <button class="button-secondary" type="button" wire:click="exportCsv">CSV</button>
            <button class="button-secondary" type="button" wire:click="exportXlsx">Excel</button>
        </div>
        @endunless
    </div>

    @if(!$isProductTable && $selected !== [] && $bulkActions !== [])
        <div class="bulk-bar">
            <strong>{{ count($selected) }} kayıt seçili</strong>
            @foreach($bulkActions as $action)
                <button type="button" wire:click="runBulkAction('{{ $action['method'] }}')">{{ $action['label'] }}</button>
            @endforeach
            <button type="button" wire:click="clearSelection">Seçimi temizle</button>
        </div>
    @endif

    <div class="v65-shared-table-title">
        <strong>{{ $isProductTable ? 'Ürün Listesi' : 'Sonuçlar' }}</strong>
        <small>{{ $rows->total() }} kayıt</small>
    </div>
    <div class="table-scroll">
        <table class="data-table">
            <thead>
            <tr>
                @unless($isProductTable)
                    <th class="select-column" scope="col">
                        <button type="button" class="link-button" wire:click="selectVisible">Tümü</button>
                    </th>
                @endunless
                @foreach ($columns as $column)
                    @continue($hiddenColumns[$column->key] ?? false)
                    <th scope="col" @class(['align-end' => $column->align === 'end'])>
                        @if ($column->sortable)
                            <button type="button" class="sort-button" wire:click="sortBy('{{ $column->key }}')">
                                {{ $column->label }}
                                @if ($sort === $column->key){{ $direction === 'asc' ? '↑' : '↓' }}@endif
                            </button>
                        @else
                            {{ $column->label }}
                        @endif
                    </th>
                @endforeach
                @if(!$isProductTable && $rowActions !== [])<th scope="col">Eylemler</th>@endif
            </tr>
            </thead>
            <tbody>
            @forelse ($rows as $row)
                <tr wire:key="row-{{ $row->getKey() }}">
                    @unless($isProductTable)
                        <td><input wire:model.live="selected" type="checkbox" value="{{ $row->getKey() }}" aria-label="Kayıt {{ $row->getKey() }} seç"></td>
                    @endunless
                    @foreach ($columns as $column)
                        @continue($hiddenColumns[$column->key] ?? false)
                        <td @class(['align-end tabular' => $column->align === 'end'])>
                            @php($cellUrl = $this->columnUrl($row, $column))
                            @if($cellUrl)
                                <a href="{{ $cellUrl }}">{{ $this->formattedValue($row, $column) }}</a>
                            @elseif($isProductTable && $column->key === 'is_active')
                                <span @class(['v65-products-status', 'is-active' => (bool) $row->getAttribute('is_active'), 'is-inactive' => ! $row->getAttribute('is_active')])>{{ $this->formattedValue($row, $column) }}</span>
                            @elseif($isProductTable && in_array($column->key, ['reserved_v65', 'available_v65'], true))
                                <strong @class(['v65-products-reserved' => $column->key === 'reserved_v65', 'v65-products-available' => $column->key === 'available_v65' && (float) $row->getAttribute('available_v65') > 0, 'v65-products-unavailable' => $column->key === 'available_v65' && (float) $row->getAttribute('available_v65') <= 0])>{{ $this->formattedValue($row, $column) }}</strong>
                            @elseif($isProductTable && in_array($column->key, ['list_price', 'on_hand_v65'], true))
                                <strong>{{ $this->formattedValue($row, $column) }}{{ $column->key === 'list_price' ? ' ₺' : '' }}</strong>
                            @else
                                {{ $this->formattedValue($row, $column) }}
                            @endif
                        </td>
                    @endforeach
                    @if(!$isProductTable && $rowActions !== [])
                        <td class="row-actions">
                            @foreach($rowActions as $action)
                                <button type="button" class="button-secondary" wire:click="runRowAction('{{ $action['method'] }}', '{{ $row->getKey() }}')">{{ $action['label'] }}</button>
                            @endforeach
                        </td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $visibleColumnCount + ($isProductTable ? 0 : 1 + (count($rowActions) > 0 ? 1 : 0)) }}" class="empty-state">
                        <div>Kayıt bulunamadı.</div>
                        @if($emptyAction)
                            <a class="button-link" href="{{ route($emptyAction['route']) }}">{{ $emptyAction['label'] }}</a>
                        @endif
                    </td>
                </tr>
            @endforelse
            </tbody>
            @if($summary !== [])
                <tfoot>
                <tr class="table-summary">
                    @unless($isProductTable)<td></td>@endunless
                    @foreach ($columns as $column)
                        @continue($hiddenColumns[$column->key] ?? false)
                        <td @class(['align-end tabular' => $column->align === 'end'])>
                            <strong>{{ $summary[$column->key] ?? '' }}</strong>
                        </td>
                    @endforeach
                    @if(!$isProductTable && $rowActions !== [])<td></td>@endif
                </tr>
                </tfoot>
            @endif
        </table>
    </div>

    <div class="table-footer">
        <span>{{ $rows->total() }} kayıt</span>
        @if($isProductTable)
        <div class="v65-products-footer-right">
            <label class="page-size-control">
                    <span>Satır</span>
                    <select wire:change="setPerPage($event.target.value)">
                        @foreach ([25,50,100] as $size)
                            <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </label>
                <details class="column-picker">
                    <summary>Kolonlar</summary>
                    <div class="column-picker-menu">
                        @foreach($columns as $column)
                            <label>
                                <input type="checkbox" @checked(!($hiddenColumns[$column->key] ?? false)) wire:click="toggleColumn('{{ $column->key }}')">
                                {{ $column->label }}
                            </label>
                        @endforeach
                    </div>
                </details>
            {{ $rows->links() }}
        </div>
        @else
            {{ $rows->links() }}
        @endif
    </div>
</div>
