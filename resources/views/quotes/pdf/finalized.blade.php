<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>{{ $revision->quote_number }} R{{ $revision->revision_number }}</title>
    <style>
        @page { margin: 28px 32px 38px; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #18181b; }
        h1, h2, p { margin: 0; }
        .header { width: 100%; border-bottom: 1px solid #d4d4d8; padding-bottom: 12px; margin-bottom: 16px; }
        .header td { vertical-align: top; }
        .brand { font-size: 18px; font-weight: 700; }
        .right { text-align: right; }
        .muted { color: #71717a; }
        .status { display: inline-block; border: 1px solid #a1a1aa; padding: 4px 8px; font-weight: 700; }
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
<table class="header">
    <tr>
        <td>
            <div class="brand">{{ $quote->company->name }}</div>
            <div class="muted">MarsOtomasyon · Finalized Teklif</div>
        </td>
        <td class="right">
            <h1>{{ $revision->quote_number }}</h1>
            <div>R{{ $revision->revision_number }}</div>
            <div class="status">{{ $decisionOutcome === 'rejected' ? 'REDDEDİLDİ' : 'ONAYLANDI' }}</div>
        </td>
    </tr>
</table>

<table class="meta">
    <tr>
        <td><div class="label">Cari</div>{{ $revision->account_code }}<br>{{ $revision->account_name }}</td>
        <td><div class="label">Teklif Tarihi</div>{{ $revision->quote_date->format('d.m.Y') }}</td>
        <td><div class="label">Geçerlilik</div>{{ $revision->valid_until?->format('d.m.Y') ?? '—' }}</td>
        <td><div class="label">Para Birimi</div>{{ $revision->currency_code }}</td>
    </tr>
</table>

<table class="lines">
    <thead>
        <tr>
            <th>#</th><th>Ürün</th><th>Açıklama</th><th class="amount">Miktar</th><th class="amount">Birim Fiyat</th><th class="amount">KDV</th><th class="amount">Toplam</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($revision->lines as $line)
            <tr>
                <td>{{ $line->position }}</td>
                <td>{{ $line->product_code }}<br><span class="muted">{{ $line->product_name }}</span></td>
                <td>{{ $line->description }}</td>
                <td class="amount">{{ $line->quantity }}</td>
                <td class="amount">{{ $line->unit_price }}</td>
                <td class="amount">%{{ $line->tax_rate }}</td>
                <td class="amount">{{ $line->gross_total }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="totals">
    <tr><td>Net</td><td class="amount">{{ $revision->net_total }}</td></tr>
    <tr><td>Vergi</td><td class="amount">{{ $revision->tax_total }}</td></tr>
    <tr class="grand"><td>Genel Toplam</td><td class="amount">{{ $revision->gross_total }} {{ $revision->currency_code }}</td></tr>
</table>

@if ($revision->note)
    <div class="note"><div class="label">Not</div>{{ $revision->note }}</div>
@endif

<div class="footer">Renderer {{ $rendererVersion }} · Source {{ $sourceFingerprint }} · Revision {{ $revision->content_fingerprint }}</div>
</body>
</html>
