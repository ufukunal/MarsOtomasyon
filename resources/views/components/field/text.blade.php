@props(['label', 'hint' => null, 'name' => null, 'prefix' => null, 'suffix' => null])
<label class="field">
    <span class="field-label">{{ $label }}</span>
    <div class="field-control">
        @if ($prefix)<span class="field-addon">{{ $prefix }}</span>@endif
        <input {{ $attributes->except(['label','hint','prefix','suffix'])->merge(['name' => $name]) }}>
        @if ($suffix)<span class="field-addon">{{ $suffix }}</span>@endif
    </div>
    @if ($hint)<small class="field-hint">{{ $hint }}</small>@endif
    @if ($name) @error($name)<small class="field-error">{{ $message }}</small>@enderror @endif
</label>
