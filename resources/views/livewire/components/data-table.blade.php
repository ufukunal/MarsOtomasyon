<div @class(['data-table-shell', 'v65-list-page', 'v65-products' => $this instanceof \App\Livewire\Pages\Products\ProductList, 'v65-stock-table' => str_starts_with(get_class($this), 'App\\Livewire\\Pages\\Stock\\') || $this instanceof \App\Livewire\Pages\Catalog\LocationList])>
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
    @php
        $visibleColumnCount = collect($columns)
            ->filter(fn ($column): bool => ! ($hiddenColumns[$column->key] ?? false))
            ->count();
    @endphp
    @if(!$isProductTable && $this instanceof \App\Livewire\Pages\Stock\StockStatus && !($this instanceof \App\Livewire\Pages\Stock\StockCost))
        <div class="alert"><strong>Kısaca:</strong> Kullanılabilir stok, rezerve ve bloke miktarları ayrı gösterilir.</div>
    @endif

    @if($this instanceof \App\Livewire\Pages\Contacts\ContactList)
        @php
            $cariStats = $this->overviewStats();
        @endphp
        <section class="v65-cari-list-intro" aria-label="Cari liste özeti">
            <div class="v65-cari-list-intro-title">
                <strong>Cari Listesi</strong>
                <span>Müşteri, tedarikçi ve diğer cari kartları</span>
            </div>
            <div class="v65-cari-list-stats">
                <div><small>Toplam Cari</small><strong>{{ $cariStats['total'] }}</strong></div>
                <div><small>Aktif</small><strong>{{ $cariStats['active'] }}</strong></div>
                <div><small>Pasif</small><strong>{{ $cariStats['inactive'] }}</strong></div>
                <div><small>Şehir</small><strong>{{ $cariStats['cities'] }}</strong></div>
                <div><small>Toplam Borç (TRY)</small><strong>{{ \App\Support\Formatting\TrFormatter::money($cariStats['debit']) }} ₺</strong></div>
                <div><small>Toplam Alacak (TRY)</small><strong>{{ \App\Support\Formatting\TrFormatter::money($cariStats['credit']) }} ₺</strong></div>
            </div>
        </section>
    @endif
    @if($isProductTable)
        @teleport('.page-actions.v65-page-actions')
            <div class="v65-products-heading-actions">
                @if($emptyAction)
                    <a class="button-link v65-products-create" href="{{ route($emptyAction['route']) }}">+ {{ $emptyAction['label'] }}</a>
                @endif
                <button type="button" class="button-secondary" data-v65-products-print-open>Yazdır</button>
                <button type="button" class="button-secondary" data-v65-products-export-open>Dışa Aktar</button>
            </div>
        @endteleport
        <dialog class="v65-products-dialog" data-v65-products-print-dialog aria-label="Yazdırma şablonları">
            <div class="v65-products-dialog-head"><strong>Yazdırma Şablonları</strong><button type="button" data-v65-products-dialog-close aria-label="Kapat">×</button></div>
            <p>Yazdırılacak ürün listesi görünümünü seçin.</p>
            <label>Şablon
                <select data-v65-products-print-template>
                    <option value="landscape">Ürün Listesi — A4 Yatay</option>
                    <option value="portrait">Ürün Listesi — A4 Dikey</option>
                </select>
            </label>
            <div class="v65-products-dialog-actions"><button type="button" class="button-secondary" data-v65-products-dialog-close>İptal</button><button type="button" class="button-link" data-v65-products-print-run>Yazdır / PDF</button></div>
        </dialog>
        <dialog wire:ignore.self class="v65-products-dialog v65-products-export-dialog" data-v65-products-export-dialog aria-label="Ürün Listesi Dışa Aktar">
            <div class="v65-products-dialog-head"><strong>Ürün Listesi Dışa Aktar</strong><button type="button" data-v65-products-dialog-close aria-label="Kapat">×</button></div>
            <label>Dosya biçimi
                <select wire:model="exportFormat">
                    <option value="xlsx">Excel (.xlsx)</option>
                    <option value="csv">CSV (.csv)</option>
                    <option value="json">JSON (.json)</option>
                </select>
            </label>
            <label>Çıktı kapsamı
                <select wire:model="exportScope">
                    <option value="filtered">Filtrelenmiş tüm kayıtlar</option>
                    <option value="page">Görünen sayfa</option>
                    <option value="all">Tüm ürünler</option>
                </select>
            </label>
            @php
                $exportKeys = collect($columns)->pluck('key');
                $exportSelectedCount = $exportKeys->filter(fn (string $key): bool => (bool) ($exportFields[$key] ?? false))->count();
            @endphp
            <div class="v65-products-export-fields" role="group" aria-label="Dışa aktarılacak alanlar">
                <div class="v65-products-export-fields-head">
                    <label class="v65-products-export-field">
                        <input type="checkbox" wire:click="toggleAllExportFields" @checked($exportSelectedCount === $exportKeys->count()) aria-label="Tüm alanları seç veya kaldır">
                        <span>Dışa Aktar</span>
                    </label>
                    <strong>Alan Adı</strong>
                </div>
                <div class="v65-products-export-fields-scroll">
                    @foreach($columns as $column)
                        <label class="v65-products-export-field-row" wire:key="export-field-{{ $column->key }}">
                            <input type="checkbox" wire:model.live="exportFields.{{ $column->key }}">
                            <span>{{ $column->label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="v65-products-dialog-actions">
                <small>{{ $exportSelectedCount }} alan seçili</small>
                <button type="button" class="button-secondary" data-v65-products-dialog-close>Kapat</button>
                <button type="button" class="button-link" wire:click="exportProducts" @disabled($exportSelectedCount === 0)>Dışa Aktar</button>
            </div>
        </dialog>
    @endif
    @unless($isProductTable)
        {{-- The header is the only place for create/export actions on ordinary DataTables.
             Never duplicate them inside the search and filter row. --}}
        @teleport('.page-actions.v65-page-actions')
            <div class="v65-list-heading-actions v65-data-table-heading-actions">
                @if($emptyAction && \Illuminate\Support\Facades\Route::has($emptyAction['route']))
                    <a class="button-link table-add-action" href="{{ route($emptyAction['route']) }}">+ {{ $emptyAction['label'] }}</a>
                @endif
                <button class="button-secondary" type="button" wire:click="exportCsv" wire:loading.attr="disabled" wire:target="exportCsv">CSV</button>
                <button class="button-secondary" type="button" wire:click="exportXlsx" wire:loading.attr="disabled" wire:target="exportXlsx">Excel</button>
            </div>
        @endteleport
    @endunless
    <div class="v65-filter-toolbar">
        <x-v65.filter-drawer :count="$this->activeV65FilterCount()" :tab="$v65FilterTab">
            <x-slot:filters>
                <x-v65.filter-field label="Genel Arama">
                    <input type="search" wire:model="v65DraftFilters.search" maxlength="255" placeholder="Kod, ad veya numara ara">
                </x-v65.filter-field>
                @foreach($filters as $filter)
                    @if($filter instanceof \App\Livewire\Components\DataTable\SelectFilter)
                        <x-v65.filter-field :label="$filter->label">
                            <select wire:model="v65DraftFilters.filters.{{ $filter->key }}">
                                <option value="">Tümü</option>
                                @foreach($filter->options as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-v65.filter-field>
                    @elseif($filter instanceof \App\Livewire\Components\DataTable\TextFilter)
                        <x-v65.filter-field :label="$filter->label">
                            <input type="text" wire:model="v65DraftFilters.filters.{{ $filter->key }}" maxlength="255" placeholder="{{ $filter->label }} ara">
                        </x-v65.filter-field>
                    @elseif($filter instanceof \App\Livewire\Components\DataTable\NumberRangeFilter)
                        <x-v65.filter-field :label="$filter->label">
                            <div class="v65-filter-date-pair">
                                <input type="number" step="0.0001" aria-label="{{ $filter->label }} en az" placeholder="En az" wire:model="v65DraftFilters.filters.{{ $filter->key }}.min">
                                <input type="number" step="0.0001" aria-label="{{ $filter->label }} en çok" placeholder="En çok" wire:model="v65DraftFilters.filters.{{ $filter->key }}.max">
                            </div>
                        </x-v65.filter-field>
                    @elseif($filter instanceof \App\Livewire\Components\DataTable\DateRangeFilter)
                        <x-v65.filter-field :label="$filter->label">
                            <div class="v65-filter-date-pair">
                                <input type="date" aria-label="{{ $filter->label }} başlangıç" wire:model="v65DraftFilters.filters.{{ $filter->key }}.from">
                                <input type="date" aria-label="{{ $filter->label }} bitiş" wire:model="v65DraftFilters.filters.{{ $filter->key }}.to">
                            </div>
                        </x-v65.filter-field>
                    @endif
                @endforeach
            </x-slot:filters>
            <x-slot:sorting>
                <x-v65.filter-field label="Sıralama Alanı">
                    <select wire:model="v65DraftFilters.sort">
                        <option value="{{ $this->v65DefaultSortKey() }}">Varsayılan</option>
                        @foreach($this->v65AvailableSortOptions() as $key => $label)
                            @if($key !== $this->v65DefaultSortKey())<option value="{{ $key }}">{{ $label }}</option>@endif
                        @endforeach
                    </select>
                </x-v65.filter-field>
                <x-v65.filter-field label="Sıralama Yönü">
                    <select wire:model="v65DraftFilters.direction">
                        <option value="asc">Artan</option>
                        <option value="desc">Azalan</option>
                    </select>
                </x-v65.filter-field>
            </x-slot:sorting>
        </x-v65.filter-drawer>
    </div>
    <x-v65.active-filters :items="$this->v65ActiveFilterLabels()" />

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
        <strong>{{ $isProductTable ? 'Ürün Listesi' : $listLabel }}</strong>
        <small>{{ $rows->total() }} kayıt</small>
    </div>
    <div class="table-scroll v65-list-table">
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
                            @php
                                $cellUrl = $this->columnUrl($row, $column);
                            @endphp
                            @if($cellUrl)
                                <a href="{{ $cellUrl }}" @class(['v65-record-link', 'v65-products-name-link' => $isProductTable && $column->key === 'name'])>{{ $this->formattedValue($row, $column) }}</a>
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

    <x-v65.list-footer :paginator="$rows" :per-page="$perPage">
        <x-slot:columnControls>
            <x-v65.column-picker>
                @foreach($columns as $column)
                    <label class="v65-column-picker-option" wire:key="column-pick-{{ $column->key }}">
                        <input type="checkbox"
                               @checked(!($hiddenColumns[$column->key] ?? false))
                               wire:click="toggleColumn('{{ $column->key }}')">
                        <span>{{ $column->label }}</span>
                    </label>
                @endforeach
            </x-v65.column-picker>
        </x-slot:columnControls>
    </x-v65.list-footer>
</div>
