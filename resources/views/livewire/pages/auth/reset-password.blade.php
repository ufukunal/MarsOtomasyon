<form wire:submit="resetPassword" class="auth-form">
    <h1>Yeni Parola</h1>

    <label>
        E-posta
        <input wire:model="email" type="email" required>
    </label>
    @error('email') <div class="field-error">{{ $message }}</div> @enderror

    <label>
        Yeni parola
        <input wire:model="password" type="password" required>
    </label>

    <label>
        Parola tekrarı
        <input wire:model="password_confirmation" type="password" required>
    </label>
    @error('password') <div class="field-error">{{ $message }}</div> @enderror

    <button type="submit">Parolayı Güncelle</button>
</form>
