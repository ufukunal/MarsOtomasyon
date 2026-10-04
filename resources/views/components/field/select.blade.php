@props(['label', 'name' => null, 'options' => [], 'placeholder' => 'Seçin'])
<label class="field">
    <span class="field-label">{{ $label }}</span>
    <select {{ $attributes->except(['label','options','placeholder'])->merge(['name' => $name]) }}>
        <option value="">{{ $placeholder }}</option>
        @foreach ($options as $value => $text)
            <option value="{{ $value }}">{{ $text }}</option>
        @endforeach
    </select>
    @if ($name) @error($name)<small class="field-error">{{ $message }}</small>@enderror @endif
</label>
