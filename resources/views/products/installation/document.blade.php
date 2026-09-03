<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>{{ $guideData['title'] ?? 'Kurulum Rehberi' }}</title>
    <style>
        @page { size: A4; margin: 16mm; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #172033; font-size: 11px; line-height: 1.45; margin: 0; background: {{ $isPreview ? '#eef1f5' : '#fff' }}; }
        .page { background: #fff; max-width: 178mm; min-height: 265mm; margin: {{ $isPreview ? '12px auto' : '0' }}; padding: {{ $isPreview ? '16mm' : '0' }}; }
        h1 { font-size: 23px; margin: 0 0 5px; }
        h2 { font-size: 15px; border-bottom: 1px solid #cbd3df; padding-bottom: 4px; margin-top: 18px; }
        .meta { color: #566176; margin-bottom: 16px; }
        .warning { border-left: 4px solid #7c2d12; padding: 6px 10px; margin: 6px 0; background: #fff7ed; }
        ol, ul { padding-left: 20px; }
        li { margin-bottom: 6px; }
        .columns { width: 100%; }
        .columns td { width: 50%; vertical-align: top; padding-right: 12px; }
        .image { margin: 12px 0; page-break-inside: avoid; text-align: center; }
        .image img { max-width: 100%; max-height: 105mm; }
        .caption { font-size: 9px; color: #667085; }
        .footer { margin-top: 24px; padding-top: 7px; border-top: 1px solid #d8dee8; font-size: 8px; color: #667085; }
    </style>
</head>
<body>
<div class="page">
    <h1>{{ $guideData['title'] ?? 'Kurulum Rehberi' }}</h1>
    <div class="meta">
        {{ $productData['code'] ?? '' }} · {{ $productData['name'] ?? '' }}
        @if (!empty($productData['brand']))
            · {{ $productData['brand'] }}
        @endif
    </div>

    @if (!empty($guideData['intro']))
        <p>{{ $guideData['intro'] }}</p>
    @endif

    @if (!empty($guideData['warnings']))
        <h2>Uyarılar</h2>
        @foreach ($guideData['warnings'] as $warning)
            <div class="warning">{{ $warning }}</div>
        @endforeach
    @endif

    <table class="columns">
        <tr>
            <td>
                <h2>Gerekli Araçlar</h2>
                @if (!empty($guideData['tools']))
                    <ul>
                        @foreach ($guideData['tools'] as $tool)
                            <li>{{ $tool }}</li>
                        @endforeach
                    </ul>
                @else
                    <p>—</p>
                @endif
            </td>
            <td>
                <h2>Parçalar / Sarf</h2>
                @if (!empty($guideData['parts']))
                    <ul>
                        @foreach ($guideData['parts'] as $part)
                            <li>{{ $part }}</li>
                        @endforeach
                    </ul>
                @else
                    <p>—</p>
                @endif
            </td>
        </tr>
    </table>

    <h2>Kurulum Adımları</h2>
    @if (!empty($guideData['steps']))
        <ol>
            @foreach ($guideData['steps'] as $step)
                <li>{{ $step }}</li>
            @endforeach
        </ol>
    @else
        <p>Henüz kurulum adımı girilmedi.</p>
    @endif

    @if (!empty($guideData['images']))
        <h2>Görseller</h2>
        @foreach ($guideData['images'] as $image)
            <div class="image">
                <img src="{{ $image['data_uri'] }}" alt="{{ $image['original_name'] }}">
                <div class="caption">{{ $image['original_name'] }}</div>
            </div>
        @endforeach
    @endif

    <div class="footer">
        {{ $rendererVersion }}
        @if ($version !== null)
            · v{{ $version }}
        @endif
        @if ($sourceFingerprint)
            · kaynak {{ substr($sourceFingerprint, 0, 16) }}
        @endif
        @if ($isPreview)
            · A4 önizleme
        @endif
    </div>
</div>
</body>
</html>
