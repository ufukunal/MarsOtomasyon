@props(['label', 'hint' => null, 'name' => null, 'prefix' => null, 'suffix' => null])
@php
    $fieldKey = $name ?: ($attributes->get('wire:model') ?? $attributes->get('wire:model.live') ?? $attributes->get('wire:model.blur') ?? $attributes->get('wire:model.defer'));
    $hasError = is_string($fieldKey) && $errors->has($fieldKey);
@endphp
<label class="field">
    <span class="field-label">{{ $label }}</span>
    <span class="field-control">
        @if ($prefix)<span class="field-addon">{{ $prefix }}</span>@endif
        <input {{ $attributes->except(['label', 'hint', 'prefix', 'suffix'])->merge(['name' => $name, 'aria-invalid' => $hasError ? 'true' : 'false']) }}>
        @if ($suffix)<span class="field-addon">{{ $suffix }}</span>@endif
    </span>
    @if ($hint)<small class="field-hint">{{ $hint }}</small>@endif
    @if ($hasError)<small class="field-error" role="alert">{{ $errors->first($fieldKey) }}</small>@endif
</label>
