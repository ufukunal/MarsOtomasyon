@props(['label', 'hint' => null])
<fieldset {{ $attributes->class(['v65-multi-select']) }}>
    <legend class="v65-multi-select-title">{{ $label }}</legend>
    <div class="v65-multi-select-options">{{ $slot }}</div>
    @if($hint)<small class="field-hint">{{ $hint }}</small>@endif
</fieldset>
