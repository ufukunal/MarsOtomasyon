<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — {{ $document->number ?? ('#'.$document->id) }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm 10mm 14mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #111; font: 10px/1.45 Arial, "DejaVu Sans", sans-serif; }
        .slip-paper { width: 100%; }
        .slip-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12mm; margin-bottom: 10mm; }
        .slip-company { font-size: 16px; font-weight: 750; margin: 0 0 6px; overflow-wrap: anywhere; }
        .slip-contact { font-size: 10px; margin: 0; overflow-wrap: anywhere; }
        .slip-kind { display: block; margin: 0 0 5px; color: #444; font-size: 9px; }
        .slip-meta { flex: 0 0 44mm; text-align: left; font-size: 10px; }
        .slip-meta span { display: block; }
        .slip-lines { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .slip-lines thead { display: table-header-group; }
        .slip-lines th { padding: 0 4px 5px; font-size: 8px; text-align: left; font-weight: 600; color: #555; border-bottom: 1px solid #ddd; }
        .slip-lines td { padding: 4px; vertical-align: top; overflow-wrap: anywhere; }
        .slip-lines tr { break-inside: avoid; page-break-inside: avoid; }
        .slip-num { width: 9%; }
        .slip-description { width: 39%; }
        .slip-qty { width: 15%; }
        .slip-money { width: 14%; }
        .slip-rate { width: 9%; }
        .slip-lines.is-unpriced .slip-num { width: 15%; }
        .slip-lines.is-unpriced .slip-description { width: 65%; }
        .slip-lines.is-unpriced .slip-qty { width: 20%; }
        .slip-lines .numeric { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .slip-subline { display: block; color: #666; font-size: 9px; margin-top: 2px; }
        .slip-totals { margin: 6mm 0 0 auto; width: min(100%, 70mm); break-inside: avoid; page-break-inside: avoid; }
        .slip-totals dl { margin: 0; }
        .slip-totals dl div { display: flex; justify-content: space-between; align-items: baseline; gap: 8mm; padding: 3px 0; }
        .slip-totals dt { font-weight: 400; }
        .slip-totals dd { margin: 0; white-space: nowrap; text-align: right; font-variant-numeric: tabular-nums; }
        .slip-totals .slip-total { border-top: 1px double #111; border-bottom: 1px double #111; padding: 4px 0; margin-top: 2mm; font-weight: 700; }
        .slip-items-count { margin-top: 6mm; text-align: right; }
        .slip-balance { margin-top: 6mm; display: flex; gap: 15mm; align-items: baseline; break-inside: avoid; }
        .slip-notes { margin-top: 8mm; white-space: pre-wrap; overflow-wrap: anywhere; break-inside: avoid; }
        .screen-actions { margin: 12px 0; text-align: center; }
        .screen-actions button { padding: 8px 16px; background: #255c8c; color: white; border: 0; cursor: pointer; }
        @media screen {
            body { background: #e7ecf1; }
            .slip-paper { width: 210mm; min-height: 297mm; padding: 12mm 10mm 14mm; margin: 0 auto; background: #fff; box-shadow: 0 2px 15px #23394b1b; }
        }
        @media print {
            .screen-actions { display: none; }
            .slip-paper { width: 100%; padding: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
@php
    $money = \App\Support\Formatting\TrFormatter::class;
    $currency = $document->currency;
    $draft = $document->status === 'draft';
@endphp
@unless($embedded)
<div class="screen-actions"><button type="button" onclick="window.print()">Yazdır</button></div>
@endunless
<main class="slip-paper">
    <header class="slip-head">
        <div>
            <h1 class="slip-company">{{ $company->legal_name ?: $company->name }}</h1>
            <span class="slip-kind">{{ $title }}{{ $draft ? ' · Taslak / Kesinleşmemiş Belge' : '' }}</span>
            <p class="slip-contact">{{ $document->contact?->title ?? '—' }}</p>
        </div>
        <div class="slip-meta">
            <span>{{ $document->document_date?->format('d.m.Y') }}</span>
            <span>{{ $document->number ?? 'Taslak #'.$document->id }}</span>
        </div>
    </header>
    <table class="slip-lines {{ $showPrices ? '' : 'is-unpriced' }}">
        <thead>
            <tr>
                <th class="slip-num">Kod / No</th>
                <th class="slip-description">Ürün / Hizmet</th>
                <th class="slip-qty numeric">Miktar</th>
                @if($showPrices)
                    <th class="slip-money numeric">Birim Fiyat</th>
                    <th class="slip-rate numeric">İsk. %</th>
                    <th class="slip-money numeric">Tutar</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse($document->lines as $line)
                <tr>
                    <td>{{ $line->line_kind === 'custom' ? ($line->configuration['custom_code'] ?? $line->line_no) : ($line->product?->code ?: $line->line_no) }}</td>
                    <td>{{ $line->description ?: '—' }}</td>
                    <td class="numeric">{{ $money::quantity((string) $line->quantity) }} {{ $line->unit?->code ?? '' }}</td>
                    @if($showPrices)
                        <td class="numeric">{{ $money::money((string) $line->unit_price) }}</td>
                        <td class="numeric">{{ $money::money((string) $line->line_discount_rate) }}</td>
                        <td class="numeric">{{ $money::money((string) $line->line_total) }}</td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ $showPrices ? 6 : 3 }}">Belge satırı bulunamadı.</td></tr>
            @endforelse
        </tbody>
    </table>
    @if($showPrices)
        <section class="slip-totals" aria-label="Kaydedilmiş belge tutarları">
            <dl>
                @if(bccomp($totalDiscountAmount, '0', 4) > 0)
                    <div><dt>İSKONTOSUZ TUTAR</dt><dd>{{ $money::money($grossTotal) }} {{ $currency }}</dd></div>
                    <div><dt>TOPLAM İSKONTO</dt><dd>{{ $money::money($totalDiscountAmount) }} {{ $currency }}</dd></div>
                    <div><dt>İSKONTOLU TUTAR</dt><dd>{{ $money::money((string) $document->tax_base) }} {{ $currency }}</dd></div>
                @else
                    <div><dt>TOPLAM</dt><dd>{{ $money::money((string) $document->tax_base) }} {{ $currency }}</dd></div>
                @endif
                <div><dt>KDV TUTARI</dt><dd>{{ $money::money((string) $document->vat_amount) }} {{ $currency }}</dd></div>
                <div class="slip-total"><dt>GENEL TOPLAM</dt><dd>{{ $money::money((string) $document->grand_total) }} {{ $currency }}</dd></div>
            </dl>
        </section>
    @else
        <div class="slip-items-count">Toplam Kalem: {{ $document->lines->count() }}</div>
    @endif
    @if($showPrices && $contactBalance !== null)
        <div class="slip-balance">
            <span>Güncel Cari Hesap Bakiyesi (TRY):</span>
            <span>{{ $money::money($contactBalance) }}</span>
        </div>
    @endif
    @if($document->notes)
        <section class="slip-notes"><strong>Açıklama:</strong> {{ $document->notes }}</section>
    @endif
</main>
</body>
</html>
