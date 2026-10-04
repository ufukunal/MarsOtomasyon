<form wire:submit="select" class="auth-form">
    <h1>Şirket ve Dönem</h1>

    <label>
        Şirket
        <select wire:model.live="companyId" required>
            <option value="">Şirket seçin</option>
            @foreach ($companies as $company)
                <option value="{{ $company->id }}">{{ $company->name }}</option>
            @endforeach
        </select>
    </label>

    <label>
        Dönem
        <select wire:model.live="periodId" required @disabled(! $companyId)>
            <option value="">Dönem seçin</option>
            @foreach ($periods as $period)
                <option value="{{ $period->id }}" @disabled($period->status === 'archived')>
                    {{ $period->year }} · {{ $period->database_name }} · {{ $period->status }}
                </option>
            @endforeach
        </select>
    </label>

    @if ($periodId && ($selected = $periods->firstWhere('id', (int) $periodId)))
        <div class="context-preview">
            Bağlanılacak veritabanı: <strong>{{ $selected->database_name }}</strong>
            @if ($selected->status === 'closed')
                <div class="alert alert-warning">Bu dönem salt okunur açılacaktır.</div>
            @elseif ($selected->status === 'archived')
                <div class="alert alert-warning">Arşiv dönem önce geri yüklenmelidir.</div>
            @endif
        </div>
    @endif

    @error('companyId') <div class="field-error">{{ $message }}</div> @enderror
    @error('periodId') <div class="field-error">{{ $message }}</div> @enderror

    <button type="submit">Devam</button>
</form>
