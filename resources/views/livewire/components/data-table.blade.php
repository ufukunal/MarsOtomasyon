<div @class(['data-table-shell', 'v65-list-page', 'v65-products' => $this instanceof \App\Livewire\Pages\Products\ProductList, 'v65-cari-list' => $this instanceof \App\Livewire\Pages\Contacts\ContactList, 'v65-stock-table' => str_starts_with(get_class($this), 'App\\Livewire\\Pages\\Stock\\') || $this instanceof \App\Livewire\Pages\Catalog\LocationList])>
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
                <span>Müşteri ve tedarikçi kartları</span>
                <div class="v65-cari-intro-filters">
                    @include('livewire.components.partials.v65-table-filter-controls')
                </div>
            </div>
            <div class="v65-cari-list-stats">
                <div><small>Toplam Cari</small><strong>{{ $cariStats['total'] }}</strong><span class="v65-cari-kpi-note">Tüm cari kartları</span></div>
                <div><small>Müşteri</small><strong>{{ $cariStats['customers'] }}</strong><span class="v65-cari-kpi-note">Müşteri kartları</span></div>
                <div><small>Tedarikçi</small><strong>{{ $cariStats['suppliers'] }}</strong><span class="v65-cari-kpi-note">Tedarikçi kartları</span></div>
                <div><small>Toplam Alacak</small><strong class="v65-cari-kpi-credit">{{ \App\Support\Formatting\TrFormatter::money($cariStats['credit']) }} ₺</strong><span class="v65-cari-kpi-note">TRY bazında</span></div>
                <div><small>Toplam Borç</small><strong class="v65-cari-kpi-debit">{{ \App\Support\Formatting\TrFormatter::money($cariStats['debit']) }} ₺</strong><span class="v65-cari-kpi-note">TRY bazında</span></div>
                <div><small>Aktif</small><strong>{{ $cariStats['active'] }}</strong><span class="v65-cari-kpi-note">Aktif cari kartları</span></div>
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
    @if($this instanceof \App\Livewire\Pages\Contacts\ContactList)
        @teleport('.page-actions.v65-page-actions')
            <div class="v65-cari-heading-actions">
                @if($emptyAction && \Illuminate\Support\Facades\Route::has($emptyAction['route']))
                    <a class="button-link" href="{{ route($emptyAction['route']) }}">+ Yeni Cari</a>
                @endif
                <button class="button-secondary" type="button" onclick="window.print()">Yazdır</button>
                <details class="v65-cari-export-menu page-action-menu">
                    <summary class="button-secondary">Dışa Aktar <span aria-hidden="true">⌄</span></summary>
                    <div class="v65-cari-export-options">
                        <button type="button" wire:click="exportCsv" wire:loading.attr="disabled" wire:target="exportCsv">CSV</button>
                        <button type="button" wire:click="exportXlsx" wire:loading.attr="disabled" wire:target="exportXlsx">Excel</button>
                    </div>
                </details>
            </div>
        @endteleport
    @elseif(!$isProductTable)
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
    @unless($this instanceof \App\Livewire\Pages\Contacts\ContactList)
        @include('livewire.components.partials.v65-table-filter-controls')
    @endunless
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

    @unless($this instanceof \App\Livewire\Pages\Contacts\ContactList)
        <div class="v65-shared-table-title">
            <strong>{{ $isProductTable ? 'Ürün Listesi' : $listLabel }}</strong>
            <small>{{ $rows->total() }} kayıt</small>
        </div>
    @endunless
    <div class="table-scroll v65-list-table">
        <table class="data-table">
            <thead>
            <tr>
                @unless($isProductTable)
                    <th class="select-column" scope="col">
                        @if($this instanceof \App\Livewire\Pages\Contacts\ContactList)
                            @php
                                $visibleCariIds = collect($rows->items())->map(fn ($item): string => (string) $item->getKey())->all();
                                $checkedCariIds = array_map('strval', $selected);
                                $allVisibleCarisSelected = $visibleCariIds !== [] && count(array_diff($visibleCariIds, $checkedCariIds)) === 0;
                            @endphp
                            <input type="checkbox" wire:click="toggleVisibleContacts" aria-label="Görünen carilerin tümünü seç" @checked($allVisibleCarisSelected)>
                        @else
                            <button type="button" class="link-button" wire:click="selectVisible">Tümü</button>
                        @endif
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
                                <a href="{{ $cellUrl }}" @class(['v65-record-link', 'v65-products-name-link' => $isProductTable && $column->key === 'name', 'v65-cari-record-title' => $this instanceof \App\Livewire\Pages\Contacts\ContactList && $column->key === 'title'])>{{ $this->formattedValue($row, $column) }}</a>
                            @elseif($this instanceof \App\Livewire\Pages\Contacts\ContactList && $column->key === 'balance_display')
                                @php
                                    $cariNet = (string) ($row->getAttribute('v65_try_net') ?? $row->balance());
                                @endphp
                                <strong @class(['v65-cari-balance', 'is-receivable' => bccomp($cariNet, '0', 4) > 0, 'is-payable' => bccomp($cariNet, '0', 4) < 0])>{{ $this->formattedValue($row, $column) }}</strong>
                            @elseif($this instanceof \App\Livewire\Pages\Contacts\ContactList && $column->key === 'is_active')
                                <span @class(['v65-cari-state', 'is-active' => (bool) $row->is_active, 'is-inactive' => ! $row->is_active])>{{ $this->formattedValue($row, $column) }}</span>
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
