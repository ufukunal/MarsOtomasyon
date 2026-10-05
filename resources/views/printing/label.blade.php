<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 3mm; }
        body { font-family: Arial, sans-serif; margin: 0; font-size: 10pt; }
        .label-section { margin: 0 0 2mm; white-space: pre-wrap; overflow-wrap: anywhere; }
        .label-section:last-child { margin-bottom: 0; }
    </style>
</head>
<body>
@foreach($sections as $section)
    @php($text = (string) ($section['settings']['text'] ?? ''))
    @if($text !== '')
        <div class="label-section">{!! $text !!}</div>
    @endif
@endforeach
</body>
</html>
