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
        .heading-meta { display:flex; align-items:flex-start; justify-content:flex-end; gap:9px; }
        .document-qr { width:23mm; flex:0 0 23mm; }
        .document-qr .label-qr { display:block; width:100%; height:auto; }
        .print-kind { margin-top:3px; color:#536b83; font-size:9px; }
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
        .source-references { display:flex; gap:8px; flex-wrap:wrap; padding:0 1px 9px; font-size:10px; color:#536d83; }
        .line-location { display:block; color:#73869a; font-size:9px; margin-top:2px; }
        .lines { width: 100%; border-collapse: collapse; font-size: 10px; }
        .lines thead { display: table-header-group; }
        .lines th { background: #f1f5f9; color: #254560; font-size: 9px; padding: 7px 5px; text-align: left; border-bottom: 1px solid #c3d1df; }
        .lines td { border-bottom: 1px solid #e5ebf1; padding: 7px 5px; vertical-align: top; overflow-wrap: anywhere; }
        .lines tr { break-inside: avoid; page-break-inside: avoid; }
        .numeric { text-align: right !important; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .lines .line-discount-rate { display: block; color: #41698e; font-size: 9px; font-weight: 700; line-height: 1.3; }
        .lines .line-discount-amount { display: block; margin-top: 2px; font-size: 10px; line-height: 1.3; }
        .desc { width: 33%; min-width:0; }
        .product-thumb { width:17mm; }
        .product-thumb img { width:15mm; height:15mm; max-width:100%; object-fit:contain; display:block; }
        .lines td.product-thumb { width:17mm; }
        .heading-meta { min-width:0; }
        .product-thumb-empty { width:15mm; height:15mm; display:flex; align-items:center; justify-content:center; border:1px solid #e2e8ef; font-size:8px; color:#8c9dae; text-align:center; }
        .address-panels { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin:-4px 0 15px; }
        .address-panels .box { min-height:27mm; }
        .unpriced-count { margin-top:11px; color:#536b83; font-weight:700; text-align:right; }
        .signatures { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:0; margin-top:18px; break-inside:avoid; }
        .signatures div { min-height:22mm; border:1px solid #ced8e3; margin-right:-1px; padding:5px; text-align:center; font-size:9px; color:#607991; }
        .summary { width: min(100%, 88mm); margin: 14px 0 0 auto; border: 1px solid #d3deea; border-radius: 5px; padding: 10px 14px; break-inside: avoid; }
        .summary dl { margin: 0; }
        .summary dl div { display: flex; gap: 16px; justify-content: space-between; align-items: baseline; padding: 6px 0; }
        .summary dt { color: #56708b; font-size: 11px; font-weight: 600; }
        .summary dd { margin: 0; text-align: right; color: #213650; font-size: 11px; font-weight: 650; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .summary .grand { border-top: 1px solid #bdcbda; padding-top: 10px; margin-top: 6px; }
        .summary .grand dt { color: #24517d; font-size: 12px; font-weight: 750; }
        .summary .grand dd { color: #164e81; font-size: 14px; font-weight: 800; }
        .notes { margin-top: 14px; padding: 10px 12px; border: 1px solid #d7e0ea; border-radius: 4px; white-space: pre-wrap; overflow-wrap: anywhere; break-inside: avoid; }
        .footer { margin-top: 15px; padding-top: 7px; border-top: 1px solid #d7e0ea; color: #6a7c8d; font-size: 9px; }
        /* Quote print only: use the first page for product rows, not blank header space. */
        .v65-quote-print .header { gap: 12px; padding-bottom: 7px; }
        .v65-quote-print .company-name { font-size: 16px; margin-bottom: 3px; }
        .v65-quote-print .company-line { margin: 1px 0; line-height: 1.3; }
        .v65-quote-print .heading-meta { gap: 7px; }
        .v65-quote-print .heading { min-width: 46mm; }
        .v65-quote-print .heading h1 { font-size: 16px; margin-bottom: 2px; }
        .v65-quote-print .heading strong { font-size: 11px; }
        .v65-quote-print .heading small { margin-top: 2px; font-size: 9px; }
        .v65-quote-print .document-qr { width: 20mm; flex-basis: 20mm; }
        .v65-quote-print .details { gap: 9px; margin: 9px 0 7px; }
        .v65-quote-print .box { padding: 7px 9px; }
        .v65-quote-print .box h2 { font-size: 10px; margin-bottom: 4px; }
        .v65-quote-print .box p { margin: 1px 0; line-height: 1.3; }
        .v65-quote-print .detail-table th, .v65-quote-print .detail-table td { padding: 1px 0; line-height: 1.35; }
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
    $isQuote = $document->document_type === \App\Enums\DocumentType::Quote;
@endphp
<div class="screen-actions"><button type="button" onclick="window.print()">Yazdır</button></div>
<main class="paper {{ $isQuote ? 'v65-quote-print' : '' }}">
    <header class="header">
        <div>
            <p class="company-name">{{ $company->legal_name ?: $company->name }}</p>
            @if($company->address)<p class="company-line">{{ $company->address }}</p>@endif
            @if($company->city)<p class="company-line">{{ $company->city }}</p>@endif
            @if($company->tax_office || $company->tax_number)<p class="company-line">Vergi: {{ $company->tax_office }} {{ $company->tax_number }}</p>@endif
            @if($company->phone || $company->email)<p class="company-line">{{ $company->phone }} {{ $company->email }}</p>@endif
        </div>
        <div class="heading-meta">
            <div class="heading">
                <h1>{{ $title }}</h1>
                <strong>{{ $document->number ?? 'Taslak #'.$document->id }}</strong>
                <small>{{ $document->document_date?->format('d.m.Y') }}</small>
                @unless($isQuote)
                    <span class="print-kind">{{ $printVariant->label() }}</span>
                    @if($draft)<div class="draft-stamp">Taslak · Kesinleşmemiş Belge</div>@endif
                @endunless
            </div>
            <div class="document-qr" aria-label="Belge QR kodu">{!! \App\Support\Printing\Label\OpaqueQrSvg::render($documentQrToken) !!}</div>
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
                <tr><th>Durum</th><td>{{ \App\Support\Formatting\BusinessLabelFormatter::status($document->status) }}</td></tr>
                @if($showPrices)
                    <tr><th>Para Birimi</th><td>{{ $currency }}</td></tr>
                    @if($document->exchange_rate && $currency !== 'TRY')<tr><th>Kur</th><td>{{ $document->exchange_rate }}</td></tr>@endif
                @endif
                @php
                    $extraLabels = [
                        'delivery_terms' => 'Teslim Süresi', 'payment_terms' => 'Ödeme Şartı',
                        'planned_delivery' => 'Planlanan Teslim', 'customer_order_no' => 'Müşteri Sipariş No',
                        'customer_reference_no' => 'Müşteri Referans No',
                        'vehicle_plate' => 'Araç / Plaka', 'payment_method' => 'Ödeme Şekli',
                        'quote_valid_until' => 'Teklif Geçerlilik', 'supplier_quote_no' => 'Tedarikçi Teklif No',
                        'expected_delivery' => 'Beklenen Teslim', 'supplier_dispatch_no' => 'Tedarikçi İrsaliye No',
                        'supplier_invoice_no' => 'Tedarikçi Fatura No',
                        'goods_receipt_reference' => 'Kabul / Kaynak Notu',
                    ];
                @endphp
                @foreach(($document->ui_meta ?? []) as $key => $value)
                    @if(isset($extraLabels[$key]) && $value !== null && $value !== '')
                        <tr><th>{{ $extraLabels[$key] }}</th><td>{{ $value }}</td></tr>
                    @endif
                @endforeach
            </table>
        </div>
    </section>
    @unless($isQuote)
    <section class="address-panels" aria-label="Fatura ve sevk adresleri">
        <div class="box">
            <h2>Fatura Adresi</h2>
            <p><strong>{{ $document->contact?->title ?? '—' }}</strong></p>
            <p>{{ $billing['address'] ?: '—' }}</p>
            <p>{{ collect([$billing['district'], $billing['city']])->filter()->implode(' / ') }}</p>
        </div>
        <div class="box">
            <h2>Sevk / Teslimat Adresi</h2>
            <p><strong>{{ $delivery['company'] ?: '—' }}</strong></p>
            <p>{{ $delivery['address'] ?: '—' }}</p>
            <p>{{ collect([$delivery['district'], $delivery['city']])->filter()->implode(' / ') }}</p>
            @if($delivery['person'])<p>Yetkili: {{ $delivery['person'] }}</p>@endif
            @if($delivery['phone'])<p>Tel: {{ $delivery['phone'] }}</p>@endif
        </div>
    </section>
    @endunless
    @if($sourceReferences->isNotEmpty())
        <div class="source-references"><strong>Kaynak Belgeler:</strong>
            @foreach($sourceReferences as $sourceNumber)
                <span>{{ $sourceNumber }}</span>
            @endforeach
        </div>
    @endif
    <table class="lines">
        <thead>
            <tr>
                <th>#</th>
                @if($showImages)<th class="product-thumb">Resim</th>@endif
                <th class="desc">Ürün / Hizmet</th><th>Birim</th>
                <th class="numeric">Miktar</th>
                @if($showPrices)
                    <th class="numeric">Birim Fiyat</th>
                    <th class="numeric">İskonto</th>
                    <th class="numeric">KDV %</th>
                    <th class="numeric">Net Tutar</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse($document->lines as $line)
                <tr>
                    <td>{{ $line->line_no }}</td>
                    @if($showImages)
                        <td class="product-thumb">
                            @if($line->line_kind === 'custom'
                                && isset($customImages[$line->configuration['custom_code'] ?? '']))
                                <img src="{{ $customImages[$line->configuration['custom_code']] }}"
                                    alt="{{ $line->description ?: 'Özel üretim görseli' }}">
                            @elseif($line->product_id && isset($productImages[$line->product_id]))
                                <img src="{{ $productImages[$line->product_id] }}"
                                    alt="{{ $line->description ?: 'Ürün görseli' }}">
                            @else
                                <span class="product-thumb-empty">Görsel yok</span>
                            @endif
                        </td>
                    @endif
                    <td class="desc">
                        {{ $line->description ?: '—' }}
                        @if($line->line_kind === 'custom')
                            <small class="line-location"><strong>{{ $line->configuration['custom_code'] ?? 'Özel Üretim' }}</strong></small>
                            @if(! empty($line->configuration['technical_notes']))
                                <small class="line-location">{{ $line->configuration['technical_notes'] }}</small>
                            @endif
                        @elseif($line->product?->code)
                            <small class="line-location">{{ $line->product->code }}</small>
                        @endif
                        @if($line->location)<small class="line-location">Depo: {{ $line->location->name }}</small>@endif
                    </td>
                    <td>{{ $line->unit?->code ?? '—' }}</td>
                    <td class="numeric">{{ \App\Support\Formatting\TrFormatter::quantity((string) $line->quantity) }}</td>
                    @if($showPrices)
                        <td class="numeric">{{ $money::money((string) $line->unit_price) }}</td>
                        <td class="numeric line-discount">
                            <span class="line-discount-rate">{{ $money::money((string) $line->line_discount_rate) }}%</span>
                            <span class="line-discount-amount">{{ $money::money((string) $line->line_discount_amount) }}</span>
                        </td>
                        <td class="numeric">{{ $line->vat_rate }}</td>
                        <td class="numeric">{{ $money::money((string) $line->line_total) }}</td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ ($showImages ? 1 : 0) + ($showPrices ? 8 : 4) }}">Belge satırı bulunamadı.</td></tr>
            @endforelse
        </tbody>
    </table>
    @if($showPrices)
    <section class="summary" aria-label="Kaydedilmiş belge tutarları">
        <dl>
            @if(bccomp($totalDiscountAmount, '0', 4) > 0)
                <div><dt>İskontosuz Tutar</dt><dd>{{ $money::money($grossTotal) }} {{ $currency }}</dd></div>
                <div><dt>Toplam İskonto Tutarı</dt><dd>{{ $money::money($totalDiscountAmount) }} {{ $currency }}</dd></div>
                <div><dt>İskontolu Tutar</dt><dd>{{ $money::money((string) $document->tax_base) }} {{ $currency }}</dd></div>
                <div><dt>KDV Tutarı</dt><dd>{{ $money::money((string) $document->vat_amount) }} {{ $currency }}</dd></div>
            @else
                <div><dt>Ara Toplam</dt><dd>{{ $money::money((string) $document->tax_base) }} {{ $currency }}</dd></div>
                <div><dt>Toplam KDV</dt><dd>{{ $money::money((string) $document->vat_amount) }} {{ $currency }}</dd></div>
            @endif
            <div class="grand"><dt>GENEL TOPLAM</dt><dd>{{ $money::money((string) $document->grand_total) }} {{ $currency }}</dd></div>
        </dl>
    </section>
    @else
        <div class="unpriced-count">Toplam Kalem: {{ $document->lines->count() }}</div>
    @endif
    @if($document->notes)<section class="notes"><strong>Açıklama / Notlar</strong>

{{ $document->notes }}</section>@endif
    <section class="signatures" aria-label="İmza alanları">
        @if($document->document_type === \App\Enums\DocumentType::Dispatch
            || $document->document_type === \App\Enums\DocumentType::PurchaseDispatch)
            <div>Düzenleyen</div><div>Teslim Eden</div><div>Teslim Alan / Kaşe</div>
        @else
            <div>Düzenleyen</div><div>Kontrol</div><div>Onay / Kaşe</div>
        @endif
    </section>
    <footer class="footer">
        <p>Oluşturma: {{ $generatedAt->format('d.m.Y H:i') }} · Belge kayıt numarası: {{ $document->id }}</p>
    </footer>
</main>
</body>
</html>
