<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 · Yetkisiz</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="guest-body">
<main class="guest-card">
    <h1>403 · Yetkisiz</h1>
    <p>Bu işlemi yapmaya yetkiniz yok.</p>
    <a href="{{ url('/') }}">Ana sayfaya dön</a>
</main>
</body>
</html>
