@props(['label', 'hint' => null])
<fieldset {{ $attributes->class(['v65-filter-field']) }}>
    <legend class="v65-filter-field-label">{{ $label }}</legend>
    {{ $slot }}
    @if($hint)<small class="v65-filter-field-hint">{{ $hint }}</small>@endif
</fieldset>
