<form wire:submit="authenticate" class="auth-form">
    <h1>MarsOtomasyon</h1>
    <p>Hesabınızla giriş yapın.</p>

    <label>
        E-posta
        <input wire:model="email" type="email" autocomplete="username" required>
    </label>
    @error('email') <div class="field-error">{{ $message }}</div> @enderror

    <label>
        Parola
        <input wire:model="password" type="password" autocomplete="current-password" required>
    </label>
    @error('password') <div class="field-error">{{ $message }}</div> @enderror

    <label class="inline-check">
        <input wire:model="remember" type="checkbox">
        Beni hatırla
    </label>

    <button type="submit">Giriş Yap</button>

    <a href="{{ route('password.request') }}">Parolamı unuttum</a>
</form>
