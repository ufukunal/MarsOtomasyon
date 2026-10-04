@props(['label'])
<label class="toggle-field">
    <input type="checkbox" {{ $attributes->except('label') }}>
    <span>{{ $label }}</span>
</label>
