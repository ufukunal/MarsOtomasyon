<div class="period-switcher">
    <label>
        <span class="sr-only">Şirket</span>
        <select wire:model.live="companyId" aria-label="Şirket">
            <option value="">Şirket seçin</option>
            @foreach ($companies as $company)
                <option value="{{ $company->id }}">{{ $company->name }}</option>
            @endforeach
        </select>
    </label>

    <label>
        <span class="sr-only">Dönem</span>
        <select wire:model.live="periodId" aria-label="Dönem" @disabled(! $companyId)>
            <option value="">Dönem seçin</option>
            @foreach ($periods as $period)
                <option value="{{ $period->id }}">
                    {{ $period->year }}{{ $period->status === 'closed' ? ' · kapalı' : '' }}
                </option>
            @endforeach
        </select>
    </label>
</div>
