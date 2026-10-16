@props(['label', 'name' => null, 'hint' => null, 'placeholder' => null, 'invalid' => false])
<label class="field v65-select-field">
    <span class="field-label">{{ $label }}</span>
    @php
        $hasError = $invalid || ($name !== null && $errors->has($name));
    @endphp
    <select {{ $attributes->except(['label', 'name', 'hint', 'placeholder', 'invalid'])->class(['v65-select-control', 'is-invalid' => $hasError])->merge(['name' => $name, 'aria-invalid' => $hasError ? 'true' : 'false']) }}>
        @if($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        {{ $slot }}
    </select>
    @if($hint)<small class="field-hint">{{ $hint }}</small>@endif
    @if($name) @error($name)<small class="field-error" role="alert">{{ $message }}</small>@enderror @endif
</label>
