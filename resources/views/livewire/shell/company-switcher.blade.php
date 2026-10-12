<label class="company-switcher">
    <span class="sr-only">{{ __('app.company') }}</span>
    <select wire:model.live="companyId" aria-label="{{ __('app.company') }}">
        <option value="">{{ __('app.select_company') }}</option>
        @foreach ($companies as $company)
            <option value="{{ $company->id }}" @selected((int) $companyId === (int) $company->id)>{{ $company->name }}</option>
        @endforeach
    </select>
</label>
