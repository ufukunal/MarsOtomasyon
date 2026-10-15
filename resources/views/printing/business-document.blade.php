<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} — {{ $document->number ?? ('#'.$document->id) }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        @page { size: A4 portrait; margin: 12mm 10mm 14mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #213650; font: 11px/1.45 Arial, "DejaVu Sans", sans-serif; }
        .paper { width: 100%; }
        .header { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; padding-bottom: 12px; border-bottom: 2px solid #254a70; }
        .company-name { margin: 0 0 5px; font-weight: 750; font-size: 18px; overflow-wrap: anywhere; }
        .company-line { color: #52687f; max-width: 105mm; overflow-wrap: anywhere; }
        .heading { text-align: right; min-width: 56mm; }
        .heading h1 { font-size: 19px; margin: 0 0 5px; color: #1a456d; line-height: 1.2; }
        .heading strong { font-size: 12px; }
        .heading small { display: block; color: #668097; margin-top: 5px; }
        .draft-stamp { padding: 6px 9px; border: 1px solid #b88642; color: #875819; font-weight: 700; margin-top: 9px; text-transform: uppercase; }
        .details { display: grid; grid-template-columns: 1.3fr 1fr; gap: 14px; margin: 15px 0; }
        .box { border: 1px solid #d2dce8; border-radius: 4px; padding: 11px 13px; min-width: 0; }
        .box h2 { margin: 0 0 7px; font-size: 11px; text-transform: uppercase; letter-spacing: .03em; color: #40617d; }
        .box p { margin: 2px 0; overflow-wrap: anywhere; }
        .detail-table { width: 100%; border-collapse: collapse; }
        .detail-table th, .detail-table td { padding: 3px 0; vertical-align: top; }
        .detail-table th { color: #5a728b; font-weight: 600; text-align: left; width: 44%; }
        .detail-table td { text-align: right; overflow-wrap: anywhere; }
        .lines { width: 100%; border-collapse: collapse; font-size: 10px; }
        .lines thead { display: table-header-group; }
        .lines th { background: #f1f5f9; color: #254560; font-size: 9px; padding: 7px 5px; text-align: left; border-bottom: 1px solid #c3d1df; }
        .lines td { border-bottom: 1px solid #e5ebf1; padding: 7px 5px; vertical-align: top; overflow-wrap: anywhere; }
        .lines tr { break-inside: avoid; page-break-inside: avoid; }
        .numeric { text-align: right !important; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .desc { width: 33%; }
        .summary { width: min(100%, 88mm); margin: 14px 0 0 auto; border: 1px solid #d3deea; border-radius: 4px; padding: 7px 12px; break-inside: avoid; }
        .summary dl { margin: 0; }
        .summary dl div { display: flex; gap: 10px; justify-content: space-between; align-items: baseline; padding: 5px 0; }
        .summary dt { color: #56708b; }
        .summary dd { margin: 0; text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .summary .grand { border-top: 1px solid #bdcbda; padding-top: 10px; margin-top: 6px; color: #164e81; font-size: 14px; font-weight: 750; }
        .notes { margin-top: 14px; padding: 10px 12px; border: 1px solid #d7e0ea; border-radius: 4px; white-space: pre-wrap; overflow-wrap: anywhere; break-inside: avoid; }
        .footer { margin-top: 15px; padding-top: 7px; border-top: 1px solid #d7e0ea; color: #6a7c8d; font-size: 9px; }
        .screen-actions { margin-bottom: 15px; }
        .screen-actions button { padding: 9px 16px; color: #fff; background: #245b90; border: 0; cursor: pointer; }
        @media print { .screen-actions { display: none; } .paper { width: 100%; } }
    </style>
</head>
<body>
@php
    $money = \App\Support\Formatting\TrFormatter::class;
    $currency = $document->currency;
    $draft = $document->status === 'draft';
    $gross = '0.0000';
    $lineDiscount = '0.0000';
    foreach ($document->lines as $line) {
        $gross = bcadd($gross, bcmul((string) $line->quantity, (string) $line->unit_price, 4), 4);
        $lineDiscount = bcadd($lineDiscount, (string) $line->line_discount_amount, 4);
    }
@endphp
<div class="screen-actions"><button type="button" onclick="window.print()">Yazdır</button></div>
<main class="paper">
    <header class="header">
        <div>
            <p class="company-name">{{ $company->legal_name ?: $company->name }}</p>
            @if($company->address)<p class="company-line">{{ $company->address }}</p>@endif
            @if($company->city)<p class="company-line">{{ $company->city }}</p>@endif
            @if($company->tax_office || $company->tax_number)<p class="company-line">Vergi: {{ $company->tax_office }} {{ $company->tax_number }}</p>@endif
            @if($company->phone || $company->email)<p class="company-line">{{ $company->phone }} {{ $company->email }}</p>@endif
        </div>
        <div class="heading">
            <h1>{{ $title }}</h1>
            <strong>{{ $document->number ?? 'Taslak #'.$document->id }}</strong>
            <small>{{ $document->document_date?->format('d.m.Y') }}</small>
            @if($draft)<div class="draft-stamp">Taslak · Kesinleşmemiş Belge</div>@endif
        </div>
    </header>
    <section class="details">
        <div class="box">
            <h2>{{ in_array($document->document_type, [\App\Enums\DocumentType::PurchaseOrder, \App\Enums\DocumentType::GoodsReceipt, \App\Enums\DocumentType::SupplierInvoice], true) ? 'Tedarikçi' : 'Müşteri / Cari' }}</h2>
            <p><strong>{{ $document->contact?->title ?? '—' }}</strong></p>
            @if($document->contact?->code)<p>Kod: {{ $document->contact->code }}</p>@endif
            @if($document->contact?->address)<p>{{ $document->contact->address }}</p>@endif
            @if($document->contact?->city)<p>{{ $document->contact->city }}</p>@endif
            @if($document->contact?->tax_number)<p>Vergi No: {{ $document->contact->tax_number }}</p>@endif
        </div>
        <div class="box">
            <h2>Belge Bilgileri</h2>
            <table class="detail-table">
                <tr><th>Belge Tarihi</th><td>{{ $document->document_date?->format('d.m.Y') }}</td></tr>
                @if($document->due_date)<tr><th>Vade</th><td>{{ $document->due_date->format('d.m.Y') }}</td></tr>@endif
                @if($document->valid_until)<tr><th>Geçerlilik</th><td>{{ $document->valid_until->format('d.m.Y') }}</td></tr>@endif
                <tr><th>Durum</th><td>{{ $draft ? 'Taslak' : ($document->status === 'posted' ? 'Kesinleşti' : ucfirst($document->status)) }}</td></tr>
                <tr><th>Para Birimi</th><td>{{ $currency }}</td></tr>
                @if($document->exchange_rate && $currency !== 'TRY')<tr><th>Kur</th><td>{{ $document->exchange_rate }}</td></tr>@endif
            </table>
        </div>
    </section>
    <table class="lines">
        <thead>
            <tr>
                <th>#</th><th class="desc">Ürün / Hizmet</th><th>Birim</th>
                <th class="numeric">Miktar</th><th class="numeric">Birim Fiyat</th>
                <th class="numeric">İskonto</th><th class="numeric">KDV %</th><th class="numeric">Net Tutar</th>
            </tr>
        </thead>
        <tbody>
            @forelse($document->lines as $line)
                <tr>
                    <td>{{ $line->line_no }}</td><td class="desc">{{ $line->description ?: '—' }}</td>
                    <td>{{ $line->unit?->code ?? '—' }}</td>
                    <td class="numeric">{{ \App\Support\Formatting\TrFormatter::quantity((string) $line->quantity) }}</td>
                    <td class="numeric">{{ $money::money((string) $line->unit_price) }}</td>
                    <td class="numeric">{{ $money::money((string) $line->line_discount_amount) }}</td>
                    <td class="numeric">{{ $line->vat_rate }}</td>
                    <td class="numeric">{{ $money::money((string) $line->line_total) }}</td>
                </tr>
            @empty
                <tr><td colspan="8">Belge satırı bulunamadı.</td></tr>
            @endforelse
        </tbody>
    </table>
    <section class="summary" aria-label="Kaydedilmiş belge tutarları">
        <dl>
            <div><dt>Mal / Hizmet Toplamı</dt><dd>{{ $money::money($gross) }} {{ $currency }}</dd></div>
            <div><dt>Satır İskontoları</dt><dd>{{ $money::money($lineDiscount) }} {{ $currency }}</dd></div>
            <div><dt>Satır Netleri</dt><dd>{{ $money::money((string) $document->subtotal) }} {{ $currency }}</dd></div>
            <div><dt>Belge İskontosu</dt><dd>{{ $money::money((string) $document->discount_amount) }} {{ $currency }}</dd></div>
            <div><dt>KDV Matrahı</dt><dd>{{ $money::money((string) $document->tax_base) }} {{ $currency }}</dd></div>
            <div><dt>Toplam KDV</dt><dd>{{ $money::money((string) $document->vat_amount) }} {{ $currency }}</dd></div>
            <div><dt>Yuvarlama Farkı</dt><dd>{{ $money::money((string) $document->rounding_difference) }} {{ $currency }}</dd></div>
            <div class="grand"><dt>GENEL TOPLAM</dt><dd>{{ $money::money((string) $document->grand_total) }} {{ $currency }}</dd></div>
        </dl>
    </section>
    @if($document->notes)<section class="notes"><strong>Açıklama / Notlar</strong>

{{ $document->notes }}</section>@endif
    <footer class="footer">
        <p>Oluşturma: {{ $generatedAt->format('d.m.Y H:i') }} · Belge kayıt numarası: {{ $document->id }}</p>
        <p>Bu çıktı kayıtlı belgeye ait kurumsal görünümdür; e-Fatura/e-Arşiv veya UBL elektronik belgesinin yerine geçmez.</p>
    </footer>
</main>
</body>
</html>
