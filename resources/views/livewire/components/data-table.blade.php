<div class="data-table-shell">
    <div class="table-toolbar">
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Ara…">

        @foreach ($filters as $filter)
            @if ($filter instanceof \App\Livewire\Components\DataTable\SelectFilter)
                <select wire:model.live="filterValues.{{ $filter->key }}">
                    <option value="">{{ $filter->label }} · Tümü</option>
                    @foreach ($filter->options as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            @elseif ($filter instanceof \App\Livewire\Components\DataTable\DateRangeFilter)
                <div class="date-range-filter">
                    <span>{{ $filter->label }}</span>
                    <input type="date" wire:model.live="filterValues.{{ $filter->key }}.from" aria-label="{{ $filter->label }} başlangıç">
                    <input type="date" wire:model.live="filterValues.{{ $filter->key }}.to" aria-label="{{ $filter->label }} bitiş">
                </div>
            @endif
        @endforeach

        <select wire:change="setPerPage($event.target.value)">
            @foreach ([25,50,100] as $size)
                <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
            @endforeach
        </select>

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

        <button type="button" wire:click="exportCsv">CSV</button>
        <button type="button" wire:click="exportXlsx">Excel</button>
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
        {{ $rows->links() }}
    </div>
</div>
