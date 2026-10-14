@props(['document' => null])
<section {{ $attributes->class(['v65-doc-summary']) }} aria-label="Belge toplamları">
    <div class="v65-doc-summary-head">
        <strong>Fatura / Belge Toplamları</strong>
        <small>{{ $document ? 'Son kaydedilen değerler' : 'Henüz kaydedilmedi' }}</small>
    </div>
    @if($document)
        @php($money = \App\Support\Formatting\TrFormatter::class)
        <dl class="v65-doc-summary-rows">
            <div><dt>Satırlar Toplamı (İskonto Sonrası)</dt><dd>{{ $money::money((string) $document->subtotal) }} {{ $document->currency }}</dd></div>
            <div><dt>Belge İskontosu</dt><dd>{{ $money::money((string) $document->discount_amount) }} {{ $document->currency }}</dd></div>
            <div><dt>KDV Matrahı</dt><dd>{{ $money::money((string) $document->tax_base) }} {{ $document->currency }}</dd></div>
            <div><dt>KDV Toplamı</dt><dd>{{ $money::money((string) $document->vat_amount) }} {{ $document->currency }}</dd></div>
            <div><dt>Yuvarlama Farkı</dt><dd>{{ $money::money((string) $document->rounding_difference) }} {{ $document->currency }}</dd></div>
            <div class="v65-doc-grand-total"><dt>GENEL TOPLAM</dt><dd>{{ $money::money((string) $document->grand_total) }} {{ $document->currency }}</dd></div>
        </dl>
        @if($document->status === 'draft')
            <small class="v65-doc-summary-warning">Bu toplamlar son kaydedilen sürüme aittir. Değişikliklerin etkisini görmek için Kaydet'e basın; Kaydetmeden kesinleştirmeyin.</small>
        @endif
    @else
        <p class="v65-doc-summary-pending">İlk kayıt sonrasında sistem tarafından hesaplanan tutarlar burada gösterilir.</p>
    @endif
</section>
