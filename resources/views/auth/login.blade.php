<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Giriş — MarsOtomasyon</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <main class="auth-shell">
        <section class="auth-card" aria-labelledby="login-title">
            <div>
                <p class="eyebrow">MarsOtomasyon</p>
                <h1 id="login-title" class="auth-title">Giriş</h1>
                <p class="auth-copy">Yetkili kullanıcı hesabınızla devam edin.</p>
            </div>

            <form method="POST" action="{{ route('login.store') }}" class="auth-form">
                @csrf

                <label class="auth-field">
                    <span>E-posta</span>
                    <input
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        autocomplete="username"
                        inputmode="email"
                        required
                        autofocus
                    >
                </label>

                <label class="auth-field">
                    <span>Parola</span>
                    <input
                        type="password"
                        name="password"
                        autocomplete="current-password"
                        required
                    >
                </label>

                @error('email')
                    <p class="auth-error" role="alert">{{ $message }}</p>
                @enderror

                @error('password')
                    <p class="auth-error" role="alert">{{ $message }}</p>
                @enderror

                <button type="submit" class="auth-submit">Giriş Yap</button>
            </form>
        </section>
    </main>
</body>
</html>
