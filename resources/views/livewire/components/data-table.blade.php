<div class="data-table-shell">
    <div class="table-toolbar">
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Ara…">

        @foreach ($filters as $filter)
            @if ($filter instanceof AppLivewireComponentsDataTableSelectFilter)
                <select wire:model.live="filterValues.{{ $filter->key }}">
                    <option value="">{{ $filter->label }} · Tümü</option>
                    @foreach ($filter->options as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            @endif
        @endforeach

        <select wire:change="setPerPage($event.target.value)">
            @foreach ([25,50,100] as $size)
                <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
            @endforeach
        </select>

        <button type="button" wire:click="exportCsv">CSV</button>
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
                                @if ($sort === $column->key)
                                    {{ $direction === 'asc' ? '↑' : '↓' }}
                                @endif
                            </button>
                        @else
                            {{ $column->label }}
                        @endif
                    </th>
                @endforeach
            </tr>
            </thead>
            <tbody>
            @forelse ($rows as $row)
                <tr wire:key="row-{{ $row->getKey() }}">
                    <td>
                        <input wire:model.live="selected" type="checkbox" value="{{ $row->getKey() }}">
                    </td>
                    @foreach ($columns as $column)
                        @continue($hiddenColumns[$column->key] ?? false)
                        <td @class(['align-end tabular' => $column->align === 'end'])>
                            @php($value = data_get($row, $column->key))
                            @if ($column->money && $value !== null)
                                {{ \App\Support\Formatting\TrFormatter::money((string) $value) }}
                            @else
                                {{ $value }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($columns) + 1 }}" class="empty-state">Kayıt bulunamadı.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="table-footer">
        <span>{{ $rows->total() }} kayıt</span>
        {{ $rows->links() }}
    </div>
</div>
