<form wire:submit="select" class="auth-form">
    <h1>Şirket Seçimi</h1>
    <p>DOC2 — dönem veya yıl seçimi olmadan şirketinizin kalıcı veritabanına bağlanın.</p>

    <label>
        Şirket
        <select wire:model="companyId" required>
            <option value="">Şirket seçin</option>
            @foreach ($companies as $company)
                <option value="{{ $company->id }}">{{ $company->name }}</option>
            @endforeach
        </select>
    </label>

    @error('companyId')
        <div class="field-error">{{ $message }}</div>
    @enderror

    <button type="submit">Devam</button>
</form>
