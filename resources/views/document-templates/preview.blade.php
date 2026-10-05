<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>{{ $name }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; color: #111; }
        .section { border: 1px solid #d0d0d0; margin: 0 0 8px; padding: 10px; }
        .section-label { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #555; }
        .settings { margin-top: 6px; font-size: 12px; white-space: pre-wrap; }
        .hidden { opacity: .45; }
    </style>
</head>
<body>
    <h1>{{ $name }}</h1>
    @forelse($sections as $section)
        <section @class(['section', 'hidden' => !$section['visible']])>
            <div class="section-label">{{ $section['type'] }}{{ $section['visible'] ? '' : ' · gizli' }}</div>
            @if($section['settings'] !== [])
                <div class="settings">{{ json_encode($section['settings'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</div>
            @endif
        </section>
    @empty
        <p>Section bulunmuyor.</p>
    @endforelse
</body>
</html>
