<div @class(['data-table-shell', 'v65-products' => $this instanceof \App\Livewire\Pages\Products\ProductList, 'v65-stock-table' => str_starts_with(get_class($this), 'App\\Livewire\\Pages\\Stock\\') || $this instanceof \App\Livewire\Pages\Catalog\LocationList])>
    @php
        $stockListMeta = [
            \App\Livewire\Pages\Stock\StockStatus::class => ['Stok Durumu', 'Depo bazlı anlık stok, rezervasyon ve kullanılabilir miktarlar.'],
            \App\Livewire\Pages\Stock\StockMovements::class => ['Stok Hareketleri', 'Ürünlerin dönem içindeki giriş, çıkış ve belge hareketleri.'],
            \App\Livewire\Pages\Stock\StockCost::class => ['Stok Maliyeti', 'Yetkili kullanıcılar için güncel hareketli ortalama ve stok değeri.'],
            \App\Livewire\Pages\Stock\TransferList::class => ['Depo Transferleri', 'Depolar arası stok transferleri ve teslimat durumları.'],
            \App\Livewire\Pages\Stock\WarehouseSlipList::class => ['Ambar Fişleri', 'Stok giriş ve çıkış fişleri.'],
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
    @if($this instanceof \App\Livewire\Pages\Products\ProductList)
        <div class="alert v65-products-hint"><strong>Kısaca:</strong> Ürün kartlarını, satış fiyatlarını ve stok durumunu buradan yönetebilirsin.</div>
        <header class="v65-products-heading">
            <div class="stack">
                <strong>Ürün Listesi</strong>
                <small>Ürün kartları, fiyat ve stok özeti.</small>
            </div>
            <div class="form-row">
                @can('products.create')
                    <a class="button-link" href="{{ route('products.create') }}">＋ Yeni Ürün</a>
                @endcan
                <button class="button-secondary" type="button" wire:click="exportXlsx">Excel</button>
                <button class="button-secondary" type="button" wire:click="exportCsv">CSV</button>
            </div>
        </header>
    @endif
    @if(! $stockMeta && ! $isProductTable)
        <header class="panel form-row v65-shared-list-heading" aria-label="{{ $listLabel }} listesi">
            <strong>{{ $listLabel }}</strong>
            <small>Yetkili olduğunuz kayıtlar listelenir.</small>
        </header>
    @endif
    @if($stockMeta)
        @if($this instanceof \App\Livewire\Pages\Stock\StockStatus && !($this instanceof \App\Livewire\Pages\Stock\StockCost))
            <div class="alert"><strong>Kısaca:</strong> Kullanılabilir stok, rezerve ve bloke miktarları ayrı gösterilir.</div>
        @endif
        <header class="panel form-row" aria-label="{{ $stockMeta[0] }} özeti">
            <div class="stack">
                <strong>{{ $stockMeta[0] }}</strong>
                <small>{{ $stockMeta[1] }}</small>
            </div>
        </header>
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
        </div>
        @if($this instanceof \App\Livewire\Pages\Products\ProductList)
            <button class="button-secondary v65-products-filter" type="button" wire:click="$refresh">Filtrele</button>
        @else
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
        @endif
    </div>

    @if($selected !== [] && $bulkActions !== [])
        <div class="bulk-bar">
            <strong>{{ count($selected) }} kayıt seçili</strong>
            @foreach($bulkActions as $action)
                <button type="button" wire:click="runBulkAction('{{ $action['method'] }}')">{{ $action['label'] }}</button>
            @endforeach
            <button type="button" wire:click="clearSelection">Seçimi temizle</button>
        </div>
    @endif

    @if($isProductTable)
        <details class="v65-products-advanced">
            <summary>Diğer Filtreler: Ürün Tipi / Stok Tipi</summary>
            <div class="form-row">
                @foreach($filters as $filter)
                    @if($filter instanceof \App\Livewire\Components\DataTable\SelectFilter && in_array($filter->key, ['kind', 'channel_stock_mode'], true))
                        <label class="table-filter">
                            <span class="sr-only">{{ $filter->label }}</span>
                            <select wire:model.live="filterValues.{{ $filter->key }}">
                                <option value="">{{ $filter->label }} · Tümü</option>
                                @foreach($filter->options as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif
                @endforeach
            </div>
        </details>
    @endif
    <div class="v65-shared-table-title">
        <strong>{{ $listLabel }}</strong>
        <small>{{ $rows->total() }} kayıt</small>
    </div>
    <div class="table-scroll">
        <table class="data-table">
            <thead>
            <tr>
                <th class="select-column">
                    <button type="button" class="link-button" wire:click="selectVisible">Tümü</button>
                </th>
                @foreach ($columns as $column)
                    @continue($hiddenColumns[$column->key] ?? false)
                    <th @class(['align-end' => $column->align === 'end'])>
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
                @if($rowActions !== [])<th>Eylemler</th>@endif
            </tr>
            </thead>
            <tbody>
            @forelse ($rows as $row)
                <tr wire:key="row-{{ $row->getKey() }}">
                    <td><input wire:model.live="selected" type="checkbox" value="{{ $row->getKey() }}"></td>
                    @foreach ($columns as $column)
                        @continue($hiddenColumns[$column->key] ?? false)
                        <td @class(['align-end tabular' => $column->align === 'end'])>
                            @php($cellUrl = $this->columnUrl($row, $column))
                            @if($cellUrl)
                                <a href="{{ $cellUrl }}">{{ $this->formattedValue($row, $column) }}</a>
                            @else
                                {{ $this->formattedValue($row, $column) }}
                            @endif
                        </td>
                    @endforeach
                    @if($rowActions !== [])
                        <td class="row-actions">
                            @foreach($rowActions as $action)
                                <button type="button" wire:click="runRowAction('{{ $action['method'] }}', '{{ $row->getKey() }}')">{{ $action['label'] }}</button>
                            @endforeach
                        </td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($columns) + 2 }}" class="empty-state">
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
                    <td></td>
                    @foreach ($columns as $column)
                        @continue($hiddenColumns[$column->key] ?? false)
                        <td @class(['align-end tabular' => $column->align === 'end'])>
                            <strong>{{ $summary[$column->key] ?? '' }}</strong>
                        </td>
                    @endforeach
                    @if($rowActions !== [])<td></td>@endif
                </tr>
                </tfoot>
            @endif
        </table>
    </div>

    <div class="table-footer">
        <span>{{ $rows->total() }} kayıt</span>
        @if($this instanceof \App\Livewire\Pages\Products\ProductList)
            <label class="page-size-control"><span>Satır</span>
                <select wire:change="setPerPage($event.target.value)">
                    @foreach([25, 50, 100] as $size)
                        <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        {{ $rows->links() }}
    </div>
</div>
