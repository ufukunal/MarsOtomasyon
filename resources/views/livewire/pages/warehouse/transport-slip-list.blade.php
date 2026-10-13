<div class="wsl-shell stack v65-list-page">
    <x-v65.active-filters :items="$this->v65ActiveFilterLabels()" />

    <section class="wsl-card panel v65-list-panel">
        <x-v65.list-heading title="Ambar Fişi Listesi">
            <div class="v65-filter-toolbar">
        <x-v65.filter-drawer :count="$this->activeV65FilterCount()" :tab="$v65FilterTab">
            <x-slot:filters>
                <x-v65.filter-field label="Genel Arama">
                    <input type="search" wire:model="v65DraftFilters.search" maxlength="255" placeholder="Fiş no, firma, ambar...">
                </x-v65.filter-field>
                <x-v65.filter-field label="Durum">
                    <select wire:model="v65DraftFilters.filters.status">
                        <option value="">Tümü</option>
                        @foreach($statusLabels as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                </x-v65.filter-field>
                <x-v65.filter-field label="Ambar / Nakliye">
                    <select wire:model="v65DraftFilters.filters.carrier">
                        <option value="">Tümü</option>
                        @foreach($carriers as $carrier)<option value="{{ $carrier }}">{{ $carrier }}</option>@endforeach
                    </select>
                </x-v65.filter-field>
                <x-v65.filter-field label="Cam Durumu">
                    <select wire:model="v65DraftFilters.filters.glass">
                        <option value="">Tümü</option><option value="yes">Cam Var</option><option value="no">Cam Yok</option>
                    </select>
                </x-v65.filter-field>
                <x-v65.filter-field label="Fiş Tarihi">
                    <div class="v65-filter-date-pair">
                        <input type="date" aria-label="Tarih başlangıç" wire:model="v65DraftFilters.filters.date.from">
                        <input type="date" aria-label="Tarih bitiş" wire:model="v65DraftFilters.filters.date.to">
                    </div>
                </x-v65.filter-field>
            </x-slot:filters>
            <x-slot:sorting>
                <x-v65.filter-field label="Sıralama Alanı">
                    <select wire:model="v65DraftFilters.sort">
                        <option value="">Varsayılan (en yeni)</option>
                        @foreach($this->v65AvailableSortOptions() as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </x-v65.filter-field>
                <x-v65.filter-field label="Sıralama Yönü">
                    <select wire:model="v65DraftFilters.direction">
                        <option value="asc">Artan</option><option value="desc">Azalan</option>
                    </select>
                </x-v65.filter-field>
            </x-slot:sorting>
        </x-v65.filter-drawer>
    </div>
        </x-v65.list-heading>
        <div class="table-scroll v65-list-table">
            <table class="data-table wsl-table">
                <thead><tr>
                    <th>Fiş No</th><th>Firma</th><th>Ambar / Nakliye</th><th>Koli</th>
                    <th>Cam</th><th>Kırılabilir</th><th>Tarih</th><th>Durum</th>
                </tr></thead>
                <tbody>
                    @forelse($slips as $slip)
                        <tr wire:key="transport-slip-{{ $slip->id }}">
                            <td><a class="v65-record-link" href="{{ route('warehouse.transport.show', ['id' => $slip->id]) }}">{{ $slip->number }}</a></td>
                            <td>
                                @if($slip->contact && auth()->user()?->can('contacts.view'))
                                    <a href="{{ route('contacts.show', ['contact' => $slip->contact_id]) }}">{{ $slip->contact->title }}</a>
                                @else
                                    {{ $slip->contact?->title ?? '—' }}
                                @endif
                            </td>
                            <td>{{ $slip->carrier }}</td>
                            <td><strong>{{ $slip->package_count }}</strong></td>
                            <td>@if($slip->contains_glass)<span class="wsl-badge warn">Cam Var</span>@else Hayır @endif</td>
                            <td>@if($slip->fragile)<span class="wsl-badge red">Hassas</span>@else Hayır @endif</td>
                            <td>{{ $slip->slip_date?->format('d.m.Y') }}</td>
                            <td><span @class(['wsl-badge', 'gray' => in_array($slip->status, ['draft', 'cancelled'], true), 'ok' => $slip->status === 'completed', 'warn' => $slip->status === 'ready'])>{{ $statusLabels[$slip->status] ?? $slip->status }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty-state">
                                <div>Kayıt bulunamadı.</div>
                                @can('warehouse_slips.create')
                                    <a class="button-link" href="{{ route('warehouse.transport.create') }}">+ Yeni Ambar Fişi</a>
                                @endcan
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-v65.list-footer :paginator="$slips" :per-page="$perPage" />
    </section>
</div>
