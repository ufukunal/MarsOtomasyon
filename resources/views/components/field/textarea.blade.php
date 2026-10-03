@props(['label', 'name' => null])
<label class="field">
    <span class="field-label">{{ $label }}</span>
    <textarea {{ $attributes->except('label')->merge(['name' => $name]) }}>{{ $slot }}</textarea>
    @if ($name) @error($name)<small class="field-error">{{ $message }}</small>@enderror @endif
</label>
