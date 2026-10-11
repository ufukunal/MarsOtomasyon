<div class="stack v65-stock-workspace">
    <div class="form-row">
        <a href="{{ route('stock.movements') }}">← Stok Hareketleri</a>
        <span class="badge">Salt okunur · Kesinleşmiş hareket</span>
    </div>
    <section class="panel stack v65-stock-document">
        <strong>Hareket · #{{ $movement->id }}</strong>
        <div class="form-grid">
            <div class="field"><span class="field-label">Tarih</span><strong>{{ $movement->movement_date?->format('d.m.Y') }}</strong></div>
            <div class="field"><span class="field-label">Hareket Yönü</span><strong>{{ $movement->direction === 'in' ? 'Giriş' : 'Çıkış' }}</strong></div>
            <div class="field"><span class="field-label">Sebep</span><strong>{{ $movement->reason }}</strong></div>
            <div class="field"><span class="field-label">Depo / Lokasyon</span>
                @can('locations.view')
                    <a href="{{ route('locations.show', ['location' => $movement->location_id]) }}">{{ $movement->location?->name ?? '—' }}</a>
                @else
                    <span>{{ $movement->location?->name ?? '—' }}</span>
                @endcan
            </div>
            <div class="field"><span class="field-label">Ürün</span>
                @can('products.view')
                    <a href="{{ route('products.show', ['product' => $movement->product_id]) }}">{{ $movement->product_code }} · {{ $movement->product?->name }}</a>
                @else
                    <span>{{ $movement->product_code }}</span>
                @endcan
            </div>
            <div class="field"><span class="field-label">Miktar</span><strong>{{ \App\Support\Formatting\TrFormatter::quantity((string) $movement->quantity) }}</strong></div>
            <div class="field"><span class="field-label">Hareket Sonrası Bakiye</span><strong>{{ \App\Support\Formatting\TrFormatter::quantity((string) $movement->balance_after) }}</strong></div>
            <div class="field"><span class="field-label">Belge</span>
                @if($sourceUrl)
                    <a href="{{ $sourceUrl }}">{{ $movement->document_no ?: '#'.$movement->document_id }}</a>
                @else
                    <span>{{ $movement->document_no ?: '—' }}</span>
                @endif
            </div>
            @if($canViewCost)
                <div class="field"><span class="field-label">Birim Maliyet</span><strong>{{ \App\Support\Formatting\TrFormatter::money((string) $movement->unit_cost) }}</strong></div>
                <div class="field"><span class="field-label">Toplam Maliyet</span><strong>{{ \App\Support\Formatting\TrFormatter::money((string) $movement->total_cost) }}</strong></div>
                <div class="field"><span class="field-label">Sonraki Ortalama</span><strong>{{ \App\Support\Formatting\TrFormatter::money((string) $movement->avg_cost_after) }}</strong></div>
            @endif
        </div>
        <div class="field"><span class="field-label">Açıklama</span><p>{{ $movement->note ?: 'Açıklama yok.' }}</p></div>
        <div class="form-row">
            <small>Kaydeden: {{ $movement->created_by_name ?: '—' }}</small>
            <small>Oluşturma: {{ $movement->created_at?->format('d.m.Y H:i') }}</small>
        </div>
    </section>
</div>
