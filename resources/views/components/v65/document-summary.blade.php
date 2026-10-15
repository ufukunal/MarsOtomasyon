@props(['document' => null, 'preview' => null, 'currency' => null])
@php
    $source = ($document === null || $document->status === 'draft') && $preview !== null ? $preview : $document;
    $isPreview = $source === $preview && $preview !== null;
    // Never pair persisted totals with an unsaved currency selection.
    $currency = strtoupper((string) ($isPreview
        ? ($currency ?: ($document?->currency ?: 'TRY'))
        : ($document?->currency ?: ($currency ?: 'TRY'))));
    $money = \App\Support\Formatting\TrFormatter::class;
    $grossAmount = '0.0000';
    $lineDiscountAmount = '0.0000';
    if ($isPreview) {
        foreach ($preview->lines as $line) {
            $grossAmount = bcadd($grossAmount, $line->gross, 4);
            $lineDiscountAmount = bcadd($lineDiscountAmount, $line->discountAmount, 4);
        }
    } elseif ($document) {
        foreach ($document->lines as $line) {
            $grossAmount = bcadd(
                $grossAmount,
                \App\Support\Money\Money::of((string) $line->unit_price)->times((string) $line->quantity)->amount,
                4
            );
            $lineDiscountAmount = bcadd($lineDiscountAmount, (string) $line->line_discount_amount, 4);
        }
    }
@endphp
<section {{ $attributes->class(['v65-doc-summary']) }} aria-label="Belge toplamları">
    <div class="v65-doc-summary-head">
        <strong>Belge Toplamları</strong>
        <small>{{ $isPreview ? 'Taslak hesap önizlemesi' : ($document ? 'Son kaydedilen değerler' : 'Henüz hesaplanmadı') }}</small>
    </div>
    @if($source)
        <dl class="v65-doc-summary-rows">
            <div><dt>Mal / Hizmet Brüt</dt><dd>{{ $money::money($grossAmount) }} {{ $currency }}</dd></div>
            <div><dt>Satır İskontoları</dt><dd>{{ $money::money($lineDiscountAmount) }} {{ $currency }}</dd></div>
            <div><dt>Satırlar Toplamı (İskonto Sonrası)</dt><dd>{{ $money::money((string) $source->subtotal) }} {{ $currency }}</dd></div>
            <div><dt>Belge İskontosu</dt><dd>{{ $money::money((string) ($isPreview ? $source->discountAmount : $source->discount_amount)) }} {{ $currency }}</dd></div>
            <div><dt>KDV Matrahı</dt><dd>{{ $money::money((string) ($isPreview ? $source->taxBase : $source->tax_base)) }} {{ $currency }}</dd></div>
            <div><dt>KDV Toplamı</dt><dd>{{ $money::money((string) ($isPreview ? $source->vatAmount : $source->vat_amount)) }} {{ $currency }}</dd></div>
            <div><dt>Yuvarlama Farkı</dt><dd>{{ $money::money((string) ($isPreview ? $source->roundingDifference : $source->rounding_difference)) }} {{ $currency }}</dd></div>
            <div class="v65-doc-grand-total"><dt>GENEL TOPLAM</dt><dd>{{ $money::money((string) ($isPreview ? $source->grandTotal : $source->grand_total)) }} {{ $currency }}</dd></div>
        </dl>
        @if($isPreview)
            <small class="v65-doc-summary-warning">Bu tutarlar kaydedilmemiş taslak önizlemesidir. Kesin tutarlar kayıtta yeniden doğrulanır.</small>
        @elseif($document?->status === 'draft')
            <small class="v65-doc-summary-warning">Bu değerler son kaydedilen sürüme aittir. Değişikliklerden sonra tekrar Kaydet kullanın.</small>
        @endif
    @else
        <p class="v65-doc-summary-pending">Miktar ve fiyat alanları tamamlandığında hesap özeti burada gösterilir.</p>
    @endif
</section>
