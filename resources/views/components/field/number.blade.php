@props(['label', 'hint' => null, 'name' => null, 'kind' => 'money'])
<label class="field">
    <span class="field-label">{{ $label }}</span>
    <input data-tr-decimal inputmode="decimal" @class(['numeric-input', 'quantity-input' => $kind === 'quantity']) {{ $attributes->except(['label','hint','kind'])->merge(['name' => $name]) }}>
    @if ($hint)<small class="field-hint">{{ $hint }}</small>@endif
    @if ($name) @error($name)<small class="field-error">{{ $message }}</small>@enderror @endif
</label>
