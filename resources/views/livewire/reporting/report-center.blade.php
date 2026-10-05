<div class="report-center">
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
                                            <option value="{{ $value }}">{{ $value }}</option>
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

                    <details class="column-picker">
                        <summary>Kolonlar</summary>
                        <div class="column-picker-menu">
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
                </div>

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

                <section class="panel">
                    <div class="table-scroll">
                        <table class="data-table report-table">
                            <thead>
                            <tr>
                                @foreach($result->columns as $column)
                                    <th @class(['align-end' => $this->isNumericColumn($column)])>
                                        {{ $column->label }}
                                    </th>
                                @endforeach
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
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ max(1, count($result->columns)) }}" class="empty-state">
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
