<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $slip->number }} · {{ $format === 'labels' ? 'Koli Etiketleri' : 'Ambar Fişi' }}</title>
<style>
*{box-sizing:border-box}
body{font:11px Arial,sans-serif;color:#15242d;background:#edf1f4;margin:0}
.toolbar{display:flex;justify-content:space-between;align-items:center;gap:14px;padding:12px 18px;background:#fff;position:sticky;top:0;z-index:10;border-bottom:1px solid #cad4dd}
button{border:1px solid #b5c6d2;background:#fff;border-radius:4px;padding:10px 15px;cursor:pointer}
.sheet{background:#fff;margin:20px auto;box-shadow:0 2px 12px #10203522}
.a4{width:297mm;height:210mm;display:grid;grid-template-columns:148.5mm 148.5mm;break-after:page;page-break-after:always}.a4:last-child{break-after:auto;page-break-after:auto}
.copy{width:148.5mm;height:210mm;overflow:hidden;padding:5mm 8mm;position:relative}
.copy:first-child{border-right:1px dashed #6e8794}
.cut{position:absolute;right:1mm;top:50%;font-size:7px;color:#6e8794;white-space:nowrap;writing-mode:vertical-rl;transform:translateY(-50%)}
.head{display:flex;justify-content:flex-end;border-bottom:2px solid #15242d;padding-bottom:5px;margin-bottom:5px;min-height:17mm}
.head-meta{text-align:right;margin-left:auto}
.head-date{font-size:12px;font-weight:800}
.head-token{display:block;margin-top:4px;font:8px/1.25 "Courier New",monospace;letter-spacing:.2px}
.approval-row{display:grid;grid-template-columns:30mm minmax(0,1fr);gap:8mm;align-items:stretch;margin-top:8mm}
.approval-row .label-qr{width:29mm;height:29mm;margin:0}
.stamp-approval{min-height:29mm;border:1.5px solid #15242d;display:flex;align-items:flex-start;justify-content:center;padding:6px;font-weight:800}
table{width:100%;border-collapse:collapse}
td,th{border:1px solid #d1dce5;padding:4px;text-align:left;vertical-align:top;overflow-wrap:anywhere;word-break:break-word}
th{background:#f1f5f9}
.details td:nth-child(odd){font-weight:bold;width:19%}
.warning{border:2px solid #15242d;text-align:center;padding:6px;font-weight:800;margin:6px 0}
.label-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:5mm;padding:5mm;background:#fff}
.label{min-width:0;border:2px solid #15242d;padding:6mm;min-height:130mm;break-inside:avoid;page-break-inside:avoid;overflow-wrap:anywhere}
.label-header{display:flex;align-items:center;justify-content:space-between;gap:8px;border-bottom:2px solid #15242d;padding-bottom:8px;min-height:29mm;min-width:0}
.label-header .label-qr{width:25mm;height:25mm;flex:0 0 25mm;max-width:none;margin:0}
.label-header strong{display:flex;align-items:center;justify-content:flex-end;min-width:0;min-height:25mm;font-size:72px;line-height:1;font-weight:900;white-space:nowrap;letter-spacing:-2px;font-variant-numeric:tabular-nums}
.label-header strong.parcel-count-wide{font-size:54px}
.label-header strong.parcel-count-xwide{font-size:38px}
.label-company{font-weight:850;font-size:29px;line-height:1.15;margin:12px 0 8px;overflow-wrap:anywhere}
.label-location{border-bottom:1px solid #d1dce5;padding:5px 0 9px;margin-bottom:9px}
.label-city{font-weight:900;font-size:33px;line-height:1.1;text-transform:uppercase;overflow-wrap:anywhere}
.label-district{font-weight:750;font-size:18px;line-height:1.25;margin-top:3px;overflow-wrap:anywhere}
.label-qr{display:block;margin:7mm auto 0;width:40mm;height:40mm;max-width:100%}
.copy .label-qr{width:29mm;height:29mm;margin:3mm auto 0}
@page{size:A4 {{ $format === 'slip' ? 'landscape' : 'portrait' }};margin:0}
@media print{
 body{background:#fff}
 .toolbar{display:none}
 .sheet{margin:0;box-shadow:none}
 .label-grid{padding:6mm}
 .a4:last-child{page-break-after:avoid}
 .label{break-inside:avoid}
}
</style>
<script src="{{ asset('js/mars-print-actions.js') }}" defer></script>
</head>
<body data-mars-autoprint="{{ $autoprint ? 'true' : 'false' }}">
<div class="toolbar">
  <div><b>{{ $slip->number }}</b> · {{ $format === 'labels' ? 'Koli Etiketleri' : 'Ambar Fişi (Yatay A4 / Yan Yana 2 × Dik A5)' }}</div>
  <button type="button" data-mars-print>Yazdır / PDF Olarak Kaydet</button>
</div>
@if($format === 'slip')
@php
    $slipQrToken = \App\Support\Printing\Label\OpaqueQrSvg::token($qrScope, 0, (string) config('app.key'));
@endphp
<main class="sheet a4">
    @for($copy = 0; $copy < 2; $copy++)
    <section class="copy">
        <header class="head">
            <div class="head-meta">
                <div class="head-date">{{ $slip->slip_date->format('d.m.Y') }}</div>
                <code class="head-token">{{ $slipQrToken }}</code>
            </div>
        </header>
        <table class="details">
            <tr><td>Taşıyıcı / Ambar</td><td>{{ $slip->carrier }}</td><td>Gönderim</td><td>{{ $slip->shipping_type }}</td></tr>
            <tr><td>Koli / Ağırlık</td><td colspan="3">{{ $slip->package_count }} koli · {{ $slip->weight_kg ?? '0' }} kg</td></tr>
            <tr><td>Teslim Firma</td><td>{{ $slip->delivery_company }}</td><td>Yetkili / Telefon</td><td>{{ $slip->delivery_person ?: '—' }} · {{ $slip->delivery_phone }}</td></tr>
            <tr><td>İl / İlçe</td><td>{{ $slip->delivery_city }} / {{ $slip->delivery_district }}</td><td>Adres</td><td>{{ $slip->delivery_address }}</td></tr>
            <tr><td>Not</td><td colspan="3">{{ $slip->note ?: '—' }}</td></tr>
        </table>
        @if($warnings)<div class="warning">{{ $warnings }}</div>@endif
        <div class="approval-row">
            {!! \App\Support\Printing\Label\OpaqueQrSvg::render($slipQrToken) !!}
            <div class="stamp-approval">Kaşe / Onay</div>
        </div>
        @if($copy === 0)<span class="cut">KESİM ÇİZGİSİ</span>@endif
    </section>
    @endfor
</main>
@else
<main class="sheet" style="width:210mm">
    <div class="label-grid">
        @for($i = 1; $i <= $slip->package_count; $i++)
            <section class="label">
                <header class="label-header">
                    {!! \App\Support\Printing\Label\OpaqueQrSvg::render(\App\Support\Printing\Label\OpaqueQrSvg::token($qrScope, $i, (string) config('app.key'))) !!}
                    <strong @class(['parcel-count-wide' => $slip->package_count >= 10 && $slip->package_count < 100, 'parcel-count-xwide' => $slip->package_count >= 100])>{{ $i }}/{{ $slip->package_count }}</strong>
                </header>
                <div class="label-company">{{ $slip->delivery_company }}</div>
                <div class="label-location">
                    <div class="label-city">{{ $slip->delivery_city }}</div>
                    <div class="label-district">{{ $slip->delivery_district }}</div>
                </div>
                <table>
                    <tr><th>Taşıyıcı / Ambar</th><td>{{ $slip->carrier }}</td></tr>
                    <tr><th>Teslim Alacak</th><td>{{ $slip->delivery_person ?: '—' }}</td></tr>
                    <tr><th>Telefon</th><td>{{ $slip->delivery_phone }}</td></tr>
                    <tr><th>Adres</th><td>{{ $slip->delivery_address }}</td></tr>
                </table>
                @if($warnings)<div class="warning">{{ $warnings }}</div>@endif
                <p>{{ $slip->note }}</p>
            </section>
        @endfor
    </div>
</main>
@endif
</body>
</html>
