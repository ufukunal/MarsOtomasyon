<div class="period-switcher">
    {{-- The company switcher is already rendered once in layouts/app.blade.php.
         This component owns only the current company's period selection. --}}
    <label>
        <span class="sr-only">Dönem</span>
        <select wire:model.live="periodId" aria-label="Dönem" @disabled(! $companyId)>
            <option value="">Dönem seçin</option>
            @foreach ($periods as $period)
                <option value="{{ $period->id }}" @selected((int) $periodId === (int) $period->id)>
                    {{ $period->year }}{{ $period->status === 'closed' ? ' · kapalı' : '' }}
                </option>
            @endforeach
        </select>
    </label>
</div>
