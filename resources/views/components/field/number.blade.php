@props(['label', 'hint' => null, 'name' => null, 'kind' => 'money'])
@php
    $fieldKey = $name ?: ($attributes->get('wire:model') ?? $attributes->get('wire:model.live') ?? $attributes->get('wire:model.blur') ?? $attributes->get('wire:model.defer'));
    $hasError = is_string($fieldKey) && $errors->has($fieldKey);
@endphp
<label class="field">
    <span class="field-label">{{ $label }}</span>
    <input data-tr-decimal inputmode="decimal" @class(['numeric-input', 'quantity-input' => $kind === 'quantity']) {{ $attributes->except(['label', 'hint', 'kind'])->merge(['name' => $name, 'aria-invalid' => $hasError ? 'true' : 'false']) }}>
    @if ($hint)<small class="field-hint">{{ $hint }}</small>@endif
    @if ($hasError)<small class="field-error" role="alert">{{ $errors->first($fieldKey) }}</small>@endif
</label>
