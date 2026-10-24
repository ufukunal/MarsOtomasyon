@props(['label', 'name' => null])
@php
    $fieldKey = $name ?: ($attributes->get('wire:model') ?? $attributes->get('wire:model.live') ?? $attributes->get('wire:model.blur') ?? $attributes->get('wire:model.defer'));
    $hasError = is_string($fieldKey) && $errors->has($fieldKey);
@endphp
<label class="field">
    <span class="field-label">{{ $label }}</span>
    <input type="date" {{ $attributes->except('label')->merge(['name' => $name, 'aria-invalid' => $hasError ? 'true' : 'false']) }}>
    @if ($hasError)<small class="field-error" role="alert">{{ $errors->first($fieldKey) }}</small>@endif
</label>
