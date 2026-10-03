@props(['label', 'name' => null])
<label class="field">
    <span class="field-label">{{ $label }}</span>
    <input type="file" {{ $attributes->except('label')->merge(['name' => $name]) }}>
    @if ($name) @error($name)<small class="field-error">{{ $message }}</small>@enderror @endif
</label>
