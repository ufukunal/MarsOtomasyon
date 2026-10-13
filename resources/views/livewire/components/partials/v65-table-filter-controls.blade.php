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
