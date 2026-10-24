<div class="report-center v65-list-page">
    <aside class="report-catalog panel">
        <h2>Rapor Kataloğu</h2>

        @forelse($catalogGroups as $category => $items)
            <section class="report-catalog-group">
                <h3>{{ $category }}</h3>
                @foreach($items as $item)
                    <button
                        type="button"
                        wire:click="selectReport('{{ $item->key }}')"
                        @class(['report-catalog-item', 'is-active' => $reportKey === $item->key])
                    >
                        {{ $item->title }}
                    </button>
                @endforeach
            </section>
        @empty
            <div class="empty-state">Yetkinize uygun rapor bulunamadı.</div>
        @endforelse
    </aside>

    <div class="report-workspace stack">
        @if(!$selectedReport)
            <section class="panel empty-state">
                Sol katalogdan bir rapor seçin.
            </section>
        @else
            <section class="panel stack">
                <div class="report-heading">
                    <div>
                        <div class="report-category">{{ $selectedReport->category }}</div>
                        <h2>{{ $selectedReport->title }}</h2>
                    </div>
                    <div class="report-meta">Tanım v{{ $selectedReport->version }}</div>
                </div>

                @if($selectedReport->key === 'finance.cash_flow_calendar')
                    <div class="alert alert-warning" role="note">
                        Nakit akış takvimi yalnız seçili şirket ve muhasebe dönemini kapsar.
                        Geçmiş günlerde muhasebeleşmiş kasa ve banka defter hareketleri; hesaplama tarihinden
                        sonraki günlerde ise carilerin mevcut net borç/alacak bakiyesinin vade dağılımı gösterilir.
                        Vadesi geçmiş açık tutarlar ilk tahmin gününe taşınır.
                        Çek/senet sütunları yalnız referanstır, tahmini bakiyeye ikinci kez eklenmez.
                        Beklenen tutarlar kesin tahsilat/ödeme taahhüdü değildir; fatura bazlı kapama takibi
                        yerine cari bazında yaklaşık FIFO dağılımı kullanılır. Para birimleri birleştirilmez.
                        Gelecek tarihli kesinleşmiş kasa/banka hareketleri tahmine ayrıca eklenmez.
                        Çek/senet referansları bugünkü belge durumuna göre gösterilir; geriye dönük kesin durum arşivi değildir.
                        Haftalık/aylık görünümde dönem içinde bir gün bile nakit açığı oluşmuşsa uyarı korunur.
                    </div>
                @endif

                @if($selectedReport->key === 'imports.conversion')
                    <div class="alert alert-warning" role="note">
                        Birleşik ithalat / konteyner dönüşüm hesabı yalnız yetkili kullanıcı tarafından kaynak belgesine
                        doğrulanarak bağlanan satış, iade, üretim firesi ve hurda kayıtlarından oluşur.
                        İthalat stokunun tamamı eşleştirilmiş sayılmaz. “Eşleştirme Dışı / Kalan” fiziksel güncel stok değildir.
                        Eksik kaynaklar ve eski döneme ait işlemler yüzdeleri olduğundan düşük gösterebilir.
                        <a href="{{ route('reports.import-sources') }}">Kaynak eşleştirmelerini yönet</a>
                    </div>
                @endif

                @if($selectedReport->key === 'imports.loss')
                    <div class="alert alert-warning" role="note">
                        Bu rapor ithalat dosyalarında bulunan ürün kodlarının şirket genelindeki stok çıkışı,
                        üretim firesi ve hurda çıkışı paylarını gösterir. Fire ve hurda oranlarının paydası,
                        seçili çeyrekte aynı ürün kodunun bütün fiziksel stok çıkışlarıdır.
                        Bu değerler ithalat veya konteyner bazında doğrulanmış kayıp oranları değildir:
                        stok çıkışları ithalat partisine bağlanmadığı için aynı ürünün yerli alışları da etkili olabilir.
                        “İthalat Dosya No” filtresi yalnız ürün kümesini daraltır, fire/hurda hareketlerini o dosyaya atamaz.
                        Kırık/hasarlı stok fişleri otomatik olarak hurdaya dahil edilmez.
                    </div>
                @endif

                @if($selectedReport->key === 'imports.sales')
                    <div class="alert alert-warning" role="note">
                        Bu döküm ithalat dosyalarında yer alan ürünlerin kesinleşmiş satış faturalarını ve iadelerini listeler.
                        Satış faturalarında kesin ithalat partisi ve konteyner bağlantısı tutulmadığından
                        “Olası İthalat Dosyaları” ve “Olası İthalat / Konteyner” alanları doğrulanmış kaynak değildir.
                        Aynı ürün yerli alımdan veya başka ithalattan gelmiş olabilir. Konteyner filtresi olası eşleşmeleri listeler;
                        konteynere kesin satış ataması yapmaz. Tutarlar fatura para birimindedir;
                        satır KDV ve toplamında belge yuvarlama farkı olabilir.
                    </div>
                @endif

                <div class="report-toolbar">
                    <label>
                        Preset
                        <select wire:change="applyPreset($event.target.value)">
                            <option value="">Preset seçin</option>
                            @foreach($presets as $preset)
                                <option value="{{ $preset->id }}" @selected($selectedPresetId === $preset->id)>
                                    {{ $preset->is_shared ? '[Ortak] ' : '' }}{{ $preset->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        Preset adı
                        <input type="text" maxlength="120" wire:model.defer="presetName">
                    </label>

                    @can('reports.presets.share')
                        <label>
                            <input type="checkbox" wire:model.defer="presetShared">
                            Şirketle paylaş
                        </label>
                    @endcan

                    <button type="button" class="button-secondary" wire:click="savePreset">Preset Kaydet</button>

                    @if($selectedPresetId)
                        <button type="button" class="button-danger" wire:click="deletePreset">Preset Sil</button>
                    @endif
                </div>

                @if($presetMessage)
                    <div class="alert">{{ $presetMessage }}</div>
                @endif

                @if($selectedReport->filters !== [])
                    <div class="report-filter-grid">
                        @foreach($selectedReport->filters as $filter)
                            <label class="field">
                                <span class="field-label">{{ $filter->label }}</span>

                                @if($filter->type === 'date')
                                    <input type="date" wire:model.defer="filterValues.{{ $filter->key }}">
                                @elseif($filter->type === 'boolean')
                                    <select wire:model.defer="filterValues.{{ $filter->key }}">
                                        <option value="">Tümü</option>
                                        <option value="1">Evet</option>
                                        <option value="0">Hayır</option>
                                    </select>
                                @elseif($filter->type === 'select')
                                    <select wire:model.defer="filterValues.{{ $filter->key }}">
                                        <option value="">Tümü</option>
                                        @foreach($filter->allowedValues as $value)
                                            <option value="{{ $value }}">{{ \App\Support\Formatting\BusinessLabelFormatter::report($selectedReport->key, $filter->key, $value) }}</option>
                                        @endforeach
                                    </select>
                                @elseif(in_array($filter->type, ['integer', 'positive_integer'], true))
                                    <input type="number" step="1" wire:model.defer="filterValues.{{ $filter->key }}">
                                @elseif($filter->type === 'decimal')
                                    <input type="number" step="0.0001" wire:model.defer="filterValues.{{ $filter->key }}">
                                @else
                                    <input type="text" wire:model.defer="filterValues.{{ $filter->key }}">
                                @endif
                            </label>
                        @endforeach
                    </div>
                @endif

                <div class="report-toolbar">
                    <button type="button" wire:click="apply">Uygula</button>
                    <button type="button" class="button-secondary" wire:click="clearFilters">Filtreleri Temizle</button>

                    <label>
                        Sırala
                        <select wire:model.defer="sortKey">
                            @foreach($selectedReport->columns as $column)
                                @if($column->sortable)
                                    <option value="{{ $column->key }}">{{ $column->label }}</option>
                                @endif
                            @endforeach
                        </select>
                    </label>

                    <select wire:model.defer="sortDirection" aria-label="Sıralama yönü">
                        <option value="asc">Artan</option>
                        <option value="desc">Azalan</option>
                    </select>

                    <select wire:change="setPerPage($event.target.value)" aria-label="Sayfa boyutu">
                        @foreach([25,50,100] as $size)
                            <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }} / sayfa</option>
                        @endforeach
                    </select>

                    <details class="column-picker v65-standard-action-menu" data-v65-action-menu>
                        <summary>Kolonlar</summary>
                        <div class="column-picker-menu v65-standard-action-menu-panel" data-v65-action-menu-panel>
                            @foreach($selectedReport->columns as $column)
                                <label>
                                    <input
                                        type="checkbox"
                                        value="{{ $column->key }}"
                                        wire:model.defer="selectedColumns"
                                    >
                                    {{ $column->label }}
                                </label>
                            @endforeach
                        </div>
                    </details>

                    <x-v65.action-menu label="Dışa Aktar">
                    @foreach(['pdf' => 'PDF', 'xlsx' => 'XLSX', 'csv' => 'CSV'] as $format => $label)
                        @if(in_array($format, $selectedReport->exporters, true))
                            <button
                                type="button"
                                class="button-secondary"
                                wire:click="export('{{ $format }}')"
                                wire:loading.attr="disabled"
                                wire:target="export"
                            >
                                {{ $label }}
                            </button>
                            <button
                                type="button"
                                class="button-secondary"
                                wire:click="queueExport('{{ $format }}')"
                                wire:loading.attr="disabled"
                                wire:target="queueExport"
                            >
                                {{ $label }} Kuyruk
                            </button>
                        @endif
                    @endforeach
                    </x-v65.action-menu>
                </div>

                @if($queueMessage)
                    <div class="alert">{{ $queueMessage }} <a href="{{ route('reports.exports') }}">Export Merkezi</a></div>
                @endif

                @if($reportError)
                    <div class="alert alert-warning">{{ $reportError }}</div>
                @endif
            </section>

            @if($result)
                @if($result->totals !== [])
                    <section class="report-totals">
                        @foreach($selectedReport->totals as $total)
                            @if(array_key_exists($total->key, $result->totals))
                                <div class="report-total-card">
                                    <span>{{ $total->label }}</span>
                                    <strong>{{ $result->totals[$total->key] }}</strong>
                                </div>
                            @endif
                        @endforeach
                    </section>
                @endif

                @if($selectedReport->key === 'finance.cash_flow_calendar' && $result->rows !== [])
                    <section class="v65-cashflow-calendar" aria-label="Nakit akış takvimi görünümü">
                        <div class="v65-cashflow-calendar-heading">
                            <h3>Nakit Akış Takvimi</h3>
                            <p>Sayfa {{ $page }} / {{ $totalPages }} · Günlük / haftalık / aylık dönem özeti · Ayrıntılar aşağıdaki tabloda</p>
                        </div>
                        <div class="v65-cashflow-grid">
                            @foreach($result->rows as $rowIndex => $row)
                                @if(isset($row['period_start'], $row['currency'], $row['closing_balance']))
                                    @php
                                        $shortage = ($row['cash_alert'] ?? '') === 'Nakit açığı';
                                        $start = \Carbon\CarbonImmutable::parse($row['period_start'])->format('d.m.Y');
                                        $end = isset($row['period_end']) ? \Carbon\CarbonImmutable::parse($row['period_end'])->format('d.m.Y') : $start;
                                    @endphp
                                    <article @class(['v65-cashflow-day', 'is-shortage' => $shortage])
                                        wire:key="cashflow-card-{{ $page }}-{{ $rowIndex }}-{{ $row['currency'] }}">
                                        <div class="v65-cashflow-day-head">
                                            <span class="v65-cashflow-day-date">{{ $start }}@if($end !== $start) – {{ $end }}@endif</span>
                                            <span class="v65-cashflow-day-currency">{{ $row['currency'] }}</span>
                                        </div>
                                        <dl>
                                            @foreach([
                                                'opening_balance' => 'Açılış',
                                                'actual_in' => 'Gerçekleşen giriş',
                                                'actual_out' => 'Gerçekleşen çıkış',
                                                'expected_in' => 'Beklenen tahsilat',
                                                'expected_out' => 'Beklenen ödeme',
                                            ] as $field => $fieldTitle)
                                                @if(array_key_exists($field, $row))
                                                    <dt>{{ $fieldTitle }}</dt>
                                                    <dd>{{ $row[$field] }}</dd>
                                                @endif
                                            @endforeach
                                            <dt><strong>Tahmini kapanış</strong></dt>
                                            <dd class="v65-cashflow-day-closing">{{ $row['closing_balance'] }}</dd>
                                        </dl>
                                        @if($shortage)
                                            <div class="v65-cashflow-day-alert">Nakit açığı</div>
                                        @endif
                                    </article>
                                @endif
                            @endforeach
                        </div>
                    </section>
                @endif

                <section class="panel">
                    <div class="table-scroll v65-list-table">
                        <table class="data-table report-table">
                            <thead>
                            <tr>
                                @foreach($result->columns as $column)
                                    <th @class(['align-end' => $this->isNumericColumn($column)])>
                                        {{ $column->label }}
                                    </th>
                                @endforeach
                                @if($result->drillDowns !== [])
                                    <th>Detay</th>
                                @endif
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($result->rows as $rowIndex => $row)
                                <tr wire:key="report-row-{{ $page }}-{{ $rowIndex }}">
                                    @foreach($result->columns as $column)
                                        <td @class(['align-end tabular' => $this->isNumericColumn($column)])>
                                            {{ $this->formatCell($row, $column) }}
                                        </td>
                                    @endforeach
                                    @if($result->drillDowns !== [])
                                        @php
                                            $drillDown = $this->drillDownLink($row, $result->drillDowns);
                                        @endphp
                                        <td>
                                            @if($drillDown)
                                                <a href="{{ $drillDown['url'] }}">{{ $drillDown['label'] }}</a>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ max(1, count($result->columns) + ($result->drillDowns !== [] ? 1 : 0)) }}" class="empty-state">
                                        Bu filtrelerle kayıt bulunamadı.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="table-footer">
                        <span>
                            {{ $result->totalRows }} kayıt · Sayfa {{ $page }} / {{ $totalPages }}
                        </span>
                        <div class="report-pagination">
                            <button
                                type="button"
                                class="button-secondary"
                                wire:click="goToPage({{ max(1, $page - 1) }})"
                                @disabled($page <= 1)
                            >
                                Önceki
                            </button>
                            <button
                                type="button"
                                wire:click="goToPage({{ min($totalPages, $page + 1) }})"
                                @disabled($page >= $totalPages)
                            >
                                Sonraki
                            </button>
                        </div>
                    </div>
                </section>
            @endif
        @endif
    </div>
</div>
