@props(['label', 'name' => null, 'hint' => null, 'placeholder' => null])
<label class="field v65-select-field">
    <span class="field-label">{{ $label }}</span>
    <select {{ $attributes->except(['label', 'name', 'hint', 'placeholder'])->merge(['name' => $name]) }}>
        @if($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        {{ $slot }}
    </select>
    @if($hint)<small class="field-hint">{{ $hint }}</small>@endif
    @if($name) @error($name)<small class="field-error" role="alert">{{ $message }}</small>@enderror @endif
</label>
