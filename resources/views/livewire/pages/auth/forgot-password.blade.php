<form wire:submit="send" class="auth-form">
    <h1>Parola Sıfırlama</h1>
    @if ($status) <div class="alert">{{ $status }}</div> @endif
    <label>
        E-posta
        <input wire:model="email" type="email" required>
    </label>
    @error('email') <div class="field-error">{{ $message }}</div> @enderror
    <button type="submit">Bağlantı Gönder</button>
    <a href="{{ route('login') }}">Girişe dön</a>
</form>
