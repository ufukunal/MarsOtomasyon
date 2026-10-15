@props(['risk'])
@if($risk)
@php
    $balanceSign = bccomp($risk['balance'], '0', 4);
    $balanceText = $balanceSign > 0 ? 'Cari borçlu' : ($balanceSign < 0 ? 'Cari alacaklı' : 'Dengede');
    $balanceTone = $balanceSign > 0 ? 'danger' : ($balanceSign < 0 ? 'success' : 'neutral');
@endphp
<section {{ $attributes->class(['v65-doc-risk']) }} aria-label="Cari TRY bakiyesi ve bilinen risk">
    <div class="v65-doc-risk-head">
        <strong>Cari Finansal Durum (TRY)</strong>
        <x-v65.status-badge :tone="$risk['exceeded'] ? 'danger' : 'neutral'">{{ $risk['exceeded'] ? 'Bilinen limit aşılıyor' : ($risk['configured'] ? 'Bilinen risk' : 'Limit tanımlı değil') }}</x-v65.status-badge>
    </div>
    <dl class="v65-doc-risk-values">
        <div><dt>Cari net bakiye</dt><dd>{{ \App\Support\Formatting\TrFormatter::money($risk['balance']) }} ₺ <x-v65.status-badge :tone="$balanceTone">{{ $balanceText }}</x-v65.status-badge></dd></div>
        <div><dt>Risk limiti</dt><dd>{{ $risk['configured'] ? \App\Support\Formatting\TrFormatter::money($risk['limit']).' ₺' : 'Tanımlanmamış' }}</dd></div>
        <div><dt>Mevcut kullanılabilir limit</dt><dd>{{ $risk['available'] !== null ? \App\Support\Formatting\TrFormatter::money($risk['available']).' ₺' : '—' }}</dd></div>
        <div><dt>{{ $risk['is_posted'] ? 'Cari mevcut bakiye' : 'Fatura dahil bilinen risk' }}</dt><dd>{{ \App\Support\Formatting\TrFormatter::money($risk['projected']) }} ₺</dd></div>
    </dl>
    <p class="v65-doc-risk-disclaimer">
        {{ $risk['is_preview'] ? 'Taslak tutarı önizleme hesabıdır. ' : '' }}
        Sadece TRY cari hareketleri ve bu fatura dikkate alınır; teminat ve diğer risk unsurları bu özetin dışında kalabilir. Bu panel kesinleştirme yetkisi veya risk onayı yerine geçmez.
    </p>
</section>
@endif
