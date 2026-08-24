<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Firma Seçimi · MarsOtomasyon</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<main class="auth-shell">
    <section class="auth-card context-card">
        <p class="eyebrow">MarsOtomasyon</p>
        <h1 class="auth-title">Firma Seçimi</h1>
        <p class="auth-copy">Çalışmak istediğiniz firmayı seçin. Firma değiştirildiğinde aktif şube seçimi sıfırlanır.</p>

        <div class="context-company-list">
            @foreach ($memberships as $membership)
                @if ($membership->company !== null)
                    <form method="POST" action="{{ route('context.companies.select', $membership->company_id) }}">
                        @csrf
                        <button type="submit" class="context-company-button">
                            <strong>{{ $membership->company->name }}</strong>
                            <span>{{ $membership->company->code }}</span>
                        </button>
                    </form>
                @endif
            @endforeach
        </div>

        <form method="POST" action="{{ route('logout') }}" class="context-logout">
            @csrf
            <button type="submit" class="button-link">Çıkış Yap</button>
        </form>
    </section>
</main>
</body>
</html>
