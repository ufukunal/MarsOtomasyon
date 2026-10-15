<div class="stack v65-stock-workspace">
    <a class="button-ghost" href="{{ route('stock.reservations.index') }}">← Rezervasyonlar</a>
    <section class="panel stack v65-stock-document">
        <div class="form-row">
            <strong>Rezervasyon · #{{ $reservation->id }}</strong>
            <span class="badge">{{ \App\Support\Formatting\BusinessLabelFormatter::value('reservation_status', $reservation->status) }}</span>
        </div>
        <div class="form-grid">
            <div class="field"><span class="field-label">Ürün</span>
                @can('products.view')
                    <a href="{{ route('products.show', ['product' => $reservation->product_id]) }}">{{ $reservation->product?->code }} · {{ $reservation->product?->name }}</a>
                @else
                    <span>{{ $reservation->product?->name ?? '—' }}</span>
                @endcan
            </div>
            <div class="field"><span class="field-label">Depo</span>
                @can('locations.view')
                    <a href="{{ route('locations.show', ['location' => $reservation->location_id]) }}">{{ $reservation->location?->name ?? '—' }}</a>
                @else
                    <span>{{ $reservation->location?->name ?? '—' }}</span>
                @endcan
            </div>
            <div class="field"><span class="field-label">Rezerve Miktar</span><strong>{{ \App\Support\Formatting\TrFormatter::quantity((string) $reservation->quantity) }}</strong></div>
            <div class="field"><span class="field-label">Kaynak Belge</span>
                <span>{{ \App\Support\Formatting\BusinessLabelFormatter::documentType($reservation->document_type) }} · {{ $reservation->document_id ?: '—' }}</span>
            </div>
            <div class="field"><span class="field-label">Belge Satırı</span><span>{{ $reservation->document_line_id ?: '—' }}</span></div>
            <div class="field"><span class="field-label">Oluşturma</span><span>{{ $reservation->created_at?->format('d.m.Y H:i') }}</span></div>
            <div class="field"><span class="field-label">Oluşturan</span><span>{{ $reservation->created_by_name ?: '—' }}</span></div>
            <div class="field"><span class="field-label">Bırakılma</span><span>{{ $reservation->released_at?->format('d.m.Y H:i') ?? '—' }}</span></div>
        </div>
        <small>Bu ekran salt okunurdur. Rezervasyon işlemleri yetkili kullanıcı tarafından listeden yapılır.</small>
    </section>
</div>
