<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bayi Paneli — MarsOtomasyon</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <main class="auth-shell">
        <section class="auth-card" aria-labelledby="b2b-title">
            <div>
                <p class="eyebrow">Mars B2B</p>
                <h1 id="b2b-title" class="auth-title">Bayi Paneli</h1>
                <p class="auth-copy">{{ $account->legal_name }}</p>
                <p class="auth-copy">{{ $b2bUser->name }}</p>
            </div>

            <form method="POST" action="{{ route('b2b.logout') }}">
                @csrf
                <button type="submit" class="auth-submit">Çıkış Yap</button>
            </form>
        </section>
    </main>
</body>
</html>
