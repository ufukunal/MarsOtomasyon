@props(['label', 'hint' => null])
<label {{ $attributes->class(['v65-filter-field']) }}>
    <span class="v65-filter-field-label">{{ $label }}</span>
    {{ $slot }}
    @if($hint)<small class="v65-filter-field-hint">{{ $hint }}</small>@endif
</label>
