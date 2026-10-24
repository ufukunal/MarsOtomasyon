@props(['label', 'name' => null, 'options' => [], 'placeholder' => 'Seçin'])
@php
    $fieldKey = $name ?: ($attributes->get('wire:model') ?? $attributes->get('wire:model.live') ?? $attributes->get('wire:model.blur') ?? $attributes->get('wire:model.defer'));
    $hasError = is_string($fieldKey) && $errors->has($fieldKey);
@endphp
<label class="field">
    <span class="field-label">{{ $label }}</span>
    <select {{ $attributes->except(['label', 'options', 'placeholder'])->merge(['name' => $name, 'aria-invalid' => $hasError ? 'true' : 'false']) }}>
        <option value="">{{ $placeholder }}</option>
        @foreach ($options as $value => $text)
            <option value="{{ $value }}">{{ $text }}</option>
        @endforeach
    </select>
    @if ($hasError)<small class="field-error" role="alert">{{ $errors->first($fieldKey) }}</small>@endif
</label>
