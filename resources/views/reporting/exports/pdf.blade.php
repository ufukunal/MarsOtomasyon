<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>{{ $dataset->title }}</title>
    <style>
        @page { size: A4 landscape; margin: 18mm 10mm 18mm 10mm; }
        body { font-family: Arial, sans-serif; font-size: 9px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        .meta { color: #555; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #bbb; padding: 4px 5px; vertical-align: top; overflow-wrap: anywhere; }
        th { background: #eee; text-align: left; }
        tr { break-inside: avoid; }
        .totals { margin-top: 10px; display: flex; gap: 12px; flex-wrap: wrap; }
        .total { border: 1px solid #bbb; padding: 5px 8px; }
    </style>
</head>
<body>
    <h1>{{ $dataset->title }}</h1>
    <div class="meta">
        {{ $dataset->totalRows }} kayıt · Tanım v{{ $dataset->definitionVersion }} ·
        {{ $generatedAt->format('d.m.Y H:i:s') }}
    </div>

    <table>
        <thead>
        <tr>
            @foreach($dataset->columns as $column)
                <th>{{ $column->label }}</th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @foreach($dataset->rows as $row)
            <tr>
                @foreach($dataset->columns as $column)
                    @php($value = $row[$column->key] ?? null)
                    <td>{{ is_scalar($value) ? (string) $value : '' }}</td>
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table>

    @if($dataset->totals !== [])
        <div class="totals">
            @foreach($dataset->totals as $key => $value)
                <div class="total"><strong>{{ $key }}</strong>: {{ is_scalar($value) ? (string) $value : '' }}</div>
            @endforeach
        </div>
    @endif
</body>
</html>
