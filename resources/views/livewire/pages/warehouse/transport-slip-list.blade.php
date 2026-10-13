<div class="wsl-shell stack v65-list-page">
    <section class="wsl-card panel v65-list-filter-panel">
        <x-v65.list-filters>
            <div class="v65-list-filter-fields">
            <label class="wsl-field"><span class="field-label">Genel Arama</span><input wire:model.live.debounce.300ms="search" placeholder="Fiş no, firma, ambar..."></label>
            <label class="wsl-field"><span class="field-label">Durum</span>
                <select wire:model.live="status">
                    <option value="">Tümü</option>
                    @foreach($statusLabels as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
            </label>
            <label class="wsl-field"><span class="field-label">Ambar / Nakliye</span>
                <select wire:model.live="carrier"><option value="">Tümü</option>
                    @foreach($carriers as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
                </select>
            </label>
            <label class="wsl-field"><span class="field-label">Cam Durumu</span>
                <select wire:model.live="glass"><option value="">Tümü</option><option value="yes">Cam Var</option><option value="no">Cam Yok</option></select>
            </label>
            <label class="wsl-field"><span class="field-label">Tarih Başlangıç</span><input type="date" wire:model.live="dateFrom"></label>
            <label class="wsl-field"><span class="field-label">Tarih Bitiş</span><input type="date" wire:model.live="dateTo"></label>
            </div>
            <button type="button" class="button-secondary v65-list-clear" wire:click="clearFilters">Temizle</button>
        </x-v65.list-filters>
    </section>

    <section class="wsl-card panel v65-list-panel">
        <div class="wsl-card-head"><strong>Ambar Fişi Listesi</strong></div>
        <div class="table-scroll v65-list-table">
            <table class="data-table wsl-table">
                <thead><tr>
                    <th>Fiş No</th><th>Firma</th><th>Ambar / Nakliye</th><th>Koli</th>
                    <th>Cam</th><th>Kırılabilir</th><th>Tarih</th><th>Durum</th>
                </tr></thead>
                <tbody>
                    @forelse($slips as $slip)
                        <tr wire:key="transport-slip-{{ $slip->id }}">
                            <td><a href="{{ route('warehouse.transport.show', ['id' => $slip->id]) }}">{{ $slip->number }}</a></td>
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
