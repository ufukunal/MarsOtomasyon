@props(['calculation' => null, 'line' => [], 'showStored' => false])
@php
    $net = $calculation?->lineTotal
        ?? ($showStored ? ($line['line_total'] ?? null) : null);
    $discount = $calculation?->discountAmount
        ?? ($showStored ? ($line['line_discount_amount'] ?? null) : null);
    $vatRate = $calculation?->vatRate ?? ($line['vat_rate'] ?? '0');
    $vatInfo = $net !== null && is_numeric((string) $vatRate)
        ? \App\Support\Money\Money::of((string) $net)->percent((string) $vatRate)->amount
        : null;
@endphp
<td class="align-end v65-doc-line-discount">{{ $discount !== null ? \App\Support\Formatting\TrFormatter::money((string) $discount) : '—' }}</td>
<td class="align-end v65-doc-line-vat" title="Belge iskontosu öncesi, bilgi amaçlı hesap">
    {{ $vatInfo !== null ? \App\Support\Formatting\TrFormatter::money($vatInfo) : '—' }}
</td>
