<!doctype html>
<html lang="tr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sistem hatası</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="guest-body"><main class="guest-card"><h1>İşlem tamamlanamadı</h1><p>Hata kodu: <strong>{{ $errorCode }}</strong></p><a href="{{ url('/') }}">Ana sayfaya dön</a></main></body>
</html>
