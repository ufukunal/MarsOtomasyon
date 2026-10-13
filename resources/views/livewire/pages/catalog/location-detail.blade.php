<div class="stack v65-stock-workspace v65-list-page">
    <div class="form-row">
        <a class="button-ghost" href="{{ route('locations.index') }}">← Depolar / Lokasyonlar</a>
        @can('locations.update')
            <a class="button-link" href="{{ route('locations.edit', ['location' => $location->id]) }}">Düzenle</a>
        @endcan
    </div>
    <section class="panel stack v65-stock-document">
        <div class="form-grid">
            <div class="field"><span class="field-label">Depo / Lokasyon Kodu</span><strong>{{ $location->code }}</strong></div>
            <div class="field"><span class="field-label">Adı</span><strong>{{ $location->name }}</strong></div>
            <div class="field"><span class="field-label">Tür</span><span>{{ $location->kind->value }}</span></div>
            <div class="field"><span class="field-label">Durum</span><span class="badge">{{ $location->is_active ? 'Aktif' : 'Pasif' }}</span></div>
            <div class="field"><span class="field-label">Varsayılan</span><span>{{ $location->is_default ? 'Evet' : 'Hayır' }}</span></div>
            <div class="field"><span class="field-label">Araç Plakası</span><span>{{ $location->plate ?: '—' }}</span></div>
            <div class="field"><span class="field-label">Adres</span><span>{{ $location->address ?: '—' }}</span></div>
        </div>
    </section>
    @can('stock.view')
        <section class="panel stack">
            <strong>Depodaki Stoklar · İlk 100 Ürün</strong>
            <div class="table-scroll v65-grid-scroll v65-list-table"><table class="data-table">
                <thead><tr><th>Ürün Kodu</th><th>Ürün</th><th>Toplam</th><th>Rezerve</th><th>Karantina</th><th>Kullanılabilir</th></tr></thead>
                <tbody>
                @forelse($balances as $balance)
                    <tr>
                        <td>
                            @can('products.view')
                                <a href="{{ route('products.show', ['product' => $balance->product_id]) }}">{{ $balance->product?->code }}</a>
                            @else
                                {{ $balance->product?->code }}
                            @endcan
                        </td>
                        <td>{{ $balance->product?->name ?? '—' }}</td>
                        <td>{{ \App\Support\Formatting\TrFormatter::quantity((string) $balance->quantity) }}</td>
                        <td>{{ \App\Support\Formatting\TrFormatter::quantity((string) bcadd((string) $balance->reserved, (string) $balance->consignment_reserved, 3)) }}</td>
                        <td>{{ \App\Support\Formatting\TrFormatter::quantity((string) $balance->quarantine) }}</td>
                        <td>{{ \App\Support\Formatting\TrFormatter::quantity($balance->available()) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-state">Bu depoda stok kaydı bulunamadı.</td></tr>
                @endforelse
                </tbody>
            </table></div>
            <small>Tüm stoklar için Stok Durumu ekranındaki depo filtresini kullanın.</small>
        </section>
    @endcan
</div>
