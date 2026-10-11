<div class="wsl-shell stack">
    <div class="wsl-head">
        <div>
            <h2>Ambar Fişleri</h2>
            <p class="wsl-sub">Bağımsız taşıma belgeleri · Firma, ambar, koli, teslimat ve durum takibi</p>
        </div>
        @can('warehouse_slips.create')
            <a class="button-link" href="{{ route('warehouse.transport.create') }}">+ Yeni Ambar Fişi</a>
        @endcan
    </div>

    <section class="wsl-card panel">
        <div class="wsl-card-head"><strong>Filtreler</strong><button type="button" class="button-secondary" wire:click="clearFilters">Temizle</button></div>
        <div class="wsl-card-body wsl-grid">
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
    </section>

    <section class="wsl-card panel">
        <div class="wsl-card-head"><strong>Ambar Fişi Listesi</strong><span class="badge">{{ $slips->total() }} kayıt</span></div>
        <div class="wsl-table-search"><input wire:model.live.debounce.300ms="tableSearch" placeholder="Listede ara..." aria-label="Ambar fiş listesinde ara"></div>
        <div class="table-scroll">
            <table class="data-table wsl-table">
                <thead><tr>
                    <th>Fiş No</th><th>Firma</th><th>Ambar / Nakliye</th><th>Koli</th>
                    <th>Cam</th><th>Kırılabilir</th><th>Tarih</th><th>Durum</th><th>Aksiyon</th>
                </tr></thead>
                <tbody>
                    @forelse($slips as $slip)
                        <tr wire:key="transport-slip-{{ $slip->id }}">
                            <td><a href="{{ route('warehouse.transport.show', ['id' => $slip->id]) }}">{{ $slip->number }}</a></td>
                            <td>{{ $slip->contact?->title ?? '—' }}</td>
                            <td>{{ $slip->carrier }}</td>
                            <td><strong>{{ $slip->package_count }}</strong></td>
                            <td>@if($slip->contains_glass)<span class="wsl-badge warn">Cam Var</span>@else Hayır @endif</td>
                            <td>@if($slip->fragile)<span class="wsl-badge red">Hassas</span>@else Hayır @endif</td>
                            <td>{{ $slip->slip_date?->format('d.m.Y') }}</td>
                            <td><span @class(['wsl-badge','ok' => $slip->status === 'completed','warn' => $slip->status === 'ready'])>{{ $statusLabels[$slip->status] ?? $slip->status }}</span></td>
                            <td class="wsl-actions">
                                <a class="button-secondary" href="{{ route('warehouse.transport.show', ['id' => $slip->id]) }}">Aç</a>
                                @if(in_array($slip->status, ['draft', 'ready'], true))
                                    @can('warehouse_slips.update')
                                        <a class="button-secondary" href="{{ route('warehouse.transport.edit', ['id' => $slip->id]) }}">Düzenle</a>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="empty-state">Kayıt bulunamadı. Yeni Ambar Fişi ile ilk taşıma belgenizi oluşturabilirsiniz.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="table-footer"><span>{{ $slips->total() }} kayıt</span>{{ $slips->links() }}</div>
    </section>
</div>
