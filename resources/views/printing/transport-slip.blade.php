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
.a4{width:210mm;height:297mm;display:grid;grid-template-rows:148.5mm 148.5mm;break-after:page;page-break-after:always}.a4:last-child{break-after:auto;page-break-after:auto}
.copy{height:148.5mm;overflow:hidden;padding:5mm 8mm;position:relative}
.copy:first-child{border-bottom:1px dashed #6e8794}
.cut{position:absolute;right:8mm;bottom:1mm;font-size:7px;color:#6e8794}
.head{display:flex;justify-content:space-between;border-bottom:2px solid #15242d;padding-bottom:5px;margin-bottom:5px}
.head h1{margin:0;font-size:19px}
.head small{display:block;margin-top:4px}
table{width:100%;border-collapse:collapse}
td,th{border:1px solid #d1dce5;padding:4px;text-align:left;vertical-align:top}
th{background:#f1f5f9}
.details td:nth-child(odd){font-weight:bold;width:19%}
.items{margin-top:5px}
.items td,.items th{padding:2px 3px;font-size:10px}
.warning{border:2px solid #15242d;text-align:center;padding:6px;font-weight:800;margin:6px 0}
.signatures{display:grid;grid-template-columns:1fr 1fr;gap:8mm;margin:5mm 0 0}
.signatures>div{border-top:1px solid #15242d;text-align:center;padding:4px}
.label-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:5mm;padding:5mm;background:#fff}
.label{border:2px solid #15242d;padding:6mm;min-height:130mm;break-inside:avoid;page-break-inside:avoid}
.label-header{display:flex;align-items:start;justify-content:space-between;border-bottom:2px solid #15242d;padding-bottom:10px}
.label-header b{font-size:17px}
.label-header strong{font-size:30px}
.label-company{font-weight:800;font-size:19px;margin:12px 0}
.barcode-text{font-weight:700;letter-spacing:2px;text-align:center;margin:10px 0}
@page{size:A4 portrait;margin:0}
@media print{
 body{background:#fff}
 .toolbar{display:none}
 .sheet{margin:0;box-shadow:none}
 .label-grid{padding:6mm}
 .a4:last-child{page-break-after:avoid}
 .label{break-inside:avoid}
}
</style>
</head>
<body>
<div class="toolbar">
  <div><b>{{ $slip->number }}</b> · {{ $format === 'labels' ? 'Koli Etiketleri' : 'Ambar Fişi (A4 / 2 × A5)' }}</div>
  <button onclick="window.print()">Yazdır / PDF Olarak Kaydet</button>
</div>
@if($format === 'slip')
@foreach($slip->items->chunk(5) as $itemPage)
<main class="sheet a4">
    @for($copy = 0; $copy < 2; $copy++)
    <section class="copy">
        <header class="head">
            <div><h1>AMBAR FİŞİ</h1><small>{{ $slip->number }} · {{ $slip->slip_date->format('d.m.Y') }}</small></div>
            <div style="text-align:right"><b>{{ $slip->contact?->title }}</b><small>{{ $slip->contact?->code }}</small></div>
        </header>
        <table class="details">
            <tr><td>Taşıyıcı / Ambar</td><td>{{ $slip->carrier }}</td><td>Gönderim</td><td>{{ $slip->shipping_type }}</td></tr>
            <tr><td>Gideceği Ambar</td><td>{{ $slip->destination_warehouse }}</td><td>Koli / Ağırlık</td><td>{{ $slip->package_count }} koli · {{ $slip->weight_kg ?? '0' }} kg</td></tr>
            <tr><td>Takip / Teslim No</td><td>{{ $slip->tracking_number ?: '—' }}</td><td>Sipariş / İrsaliye</td><td>{{ $slip->order_reference ?: '—' }} / {{ $slip->dispatch_reference ?: '—' }}</td></tr>
            <tr><td>Teslim Firma</td><td>{{ $slip->delivery_company }}</td><td>Yetkili / Telefon</td><td>{{ $slip->delivery_person ?: '—' }} · {{ $slip->delivery_phone }}</td></tr>
            <tr><td>İl / İlçe</td><td>{{ $slip->delivery_city }} / {{ $slip->delivery_district }}</td><td>Adres</td><td>{{ $slip->delivery_address }}</td></tr>
            <tr><td>Not</td><td colspan="3">{{ $slip->note ?: '—' }}</td></tr>
        </table>
        <table class="items">
            <thead><tr><th>Ürün</th><th>Adı</th><th>Miktar</th></tr></thead>
            <tbody>
                @foreach($itemPage as $item)
                    <tr><td>{{ $item->product?->code }}</td><td>{{ $item->product?->name }}</td><td>{{ \App\Support\Formatting\TrFormatter::quantity((string) $item->quantity) }} {{ $item->product?->unit?->name }}</td></tr>
                @endforeach
            </tbody>
        </table>
        @if($slip->items->count() > 5)<small>Sayfa {{ $loop->iteration }} / {{ $slip->items->chunk(5)->count() }} · Toplam {{ $slip->items->count() }} ürün satırı</small>@endif
        @if($warnings)<div class="warning">{{ $warnings }}</div>@endif
        <div class="signatures"><div>Teslim Eden<br>Ad / İmza</div><div>Teslim Alan<br>Ad / İmza</div></div>
        {!! \App\Support\Printing\Label\Code39Svg::render($slip->number) !!}
        @if($copy === 0)<span class="cut">KESİM ÇİZGİSİ</span>@endif
    </section>
    @endfor
</main>
@endforeach
@else
<main class="sheet" style="width:210mm">
    <div class="label-grid">
        @for($i = 1; $i <= $slip->package_count; $i++)
            <section class="label">
                <header class="label-header"><div><b>SEVKİYAT ETİKETİ</b><p>{{ $slip->number }}</p></div><strong>{{ $i }}/{{ $slip->package_count }}</strong></header>
                <div class="label-company">{{ $slip->delivery_company }}</div>
                <table>
                    <tr><th>Gideceği Ambar</th><td>{{ $slip->destination_warehouse }}</td></tr>
                    <tr><th>Teslim Alacak</th><td>{{ $slip->delivery_person ?: '—' }}</td></tr>
                    <tr><th>Telefon</th><td>{{ $slip->delivery_phone }}</td></tr>
                    <tr><th>İl / İlçe</th><td>{{ $slip->delivery_city }} / {{ $slip->delivery_district }}</td></tr>
                    <tr><th>Adres</th><td>{{ $slip->delivery_address }}</td></tr>
                </table>
                @if($warnings)<div class="warning">{{ $warnings }}</div>@endif
                {!! \App\Support\Printing\Label\Code39Svg::render($slip->number.'-'.$i.'-'.$slip->package_count) !!}
                <p>{{ $slip->note }}</p>
            </section>
        @endfor
    </div>
</main>
@endif
@if($autoprint)
<script>window.addEventListener('load', () => { window.print(); });</script>
@endif
</body>
</html>
