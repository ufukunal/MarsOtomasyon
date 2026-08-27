<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number }}</title>
    <style>
        @page { margin: 28px 32px 38px; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #18181b; }
        h1, h2, p { margin: 0; }
        .header { width: 100%; border-bottom: 1px solid #d4d4d8; padding-bottom: 12px; margin-bottom: 16px; }
        .header td { vertical-align: top; }
        .brand { font-size: 18px; font-weight: 700; }
        .right { text-align: right; }
        .muted { color: #71717a; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .meta td { width: 25%; border: 1px solid #e4e4e7; padding: 7px; vertical-align: top; }
        .label { font-size: 8px; color: #71717a; text-transform: uppercase; margin-bottom: 3px; }
        table.lines { width: 100%; border-collapse: collapse; }
        .lines th, .lines td { border-bottom: 1px solid #e4e4e7; padding: 6px 4px; vertical-align: top; }
        .lines th { text-align: left; font-size: 8px; text-transform: uppercase; color: #52525b; }
        .amount { text-align: right; white-space: nowrap; }
        .totals { width: 44%; margin-left: 56%; margin-top: 12px; border-collapse: collapse; }
        .totals td { padding: 5px 4px; border-bottom: 1px solid #e4e4e7; }
        .totals .grand { font-size: 12px; font-weight: 700; }
        .note { margin-top: 16px; border: 1px solid #e4e4e7; padding: 8px; }
        .footer { position: fixed; bottom: -24px; left: 0; right: 0; font-size: 7px; color: #71717a; border-top: 1px solid #e4e4e7; padding-top: 5px; }
    </style>
</head>
<body>
<table class="header"><tr>
    <td><div class="brand">{{ $invoice->company?->name ?? 'MarsOtomasyon' }}</div><div class="muted">Kesinleşmiş Satış Faturası</div></td>
    <td class="right"><h1>{{ $invoice->number }}</h1><div>{{ $invoice->invoice_date?->format('d.m.Y') }}</div></td>
</tr></table>

<table class="meta"><tr>
    <td><div class="label">Müşteri</div>{{ $invoice->customer_legal_name }}@if($invoice->customer_tax_number)<br>{{ $invoice->customer_tax_number }}@endif</td>
    <td><div class="label">Adres</div>{{ $invoice->address_line1 }}<br>@if($invoice->district){{ $invoice->district }} / @endif{{ $invoice->city }}</td>
    <td><div class="label">Para Birimi</div>{{ $invoice->currency_code }}</td>
    <td><div class="label">Kesinleşme</div>{{ $invoice->finalized_at?->format('d.m.Y H:i:s') }}</td>
</tr></table>

<table class="lines"><thead><tr>
    <th>#</th><th>Ürün</th><th class="amount">Miktar</th><th class="amount">Birim Fiyat</th><th class="amount">KDV</th><th class="amount">Net</th><th class="amount">Toplam</th>
</tr></thead><tbody>
@foreach($invoice->lines as $line)
<tr>
    <td>{{ $line->position }}</td>
    <td>{{ $line->product_code }}<br><span class="muted">{{ $line->product_name }}</span>@if($line->description)<br>{{ $line->description }}@endif</td>
    <td class="amount">{{ $line->quantity }}</td>
    <td class="amount">{{ $line->unit_price }}</td>
    <td class="amount">%{{ $line->tax_rate }}</td>
    <td class="amount">{{ $line->net_total }}</td>
    <td class="amount">{{ $line->gross_total }}</td>
</tr>
@endforeach
</tbody></table>

<table class="totals">
<tr><td>Net</td><td class="amount">{{ $invoice->net_total }}</td></tr>
<tr><td>KDV</td><td class="amount">{{ $invoice->tax_total }}</td></tr>
<tr class="grand"><td>Genel Toplam</td><td class="amount">{{ $invoice->gross_total }} {{ $invoice->currency_code }}</td></tr>
</table>

@if($invoice->note)<div class="note"><div class="label">Not</div>{{ $invoice->note }}</div>@endif
<div class="footer">Renderer {{ $rendererVersion }} · Source {{ $sourceFingerprint }}</div>
</body>
</html>
