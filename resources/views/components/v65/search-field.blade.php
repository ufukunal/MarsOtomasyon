@props(['label' => 'Ara', 'placeholder' => 'Kod veya ad ile ara'])
<label {{ $attributes->only(['class'])->class(['field', 'v65-search-field']) }}>
    <span class="field-label">{{ $label }}</span>
    <span class="v65-search-control">
        <span class="v65-search-icon" aria-hidden="true">⌕</span>
        <input type="search" autocomplete="off" placeholder="{{ $placeholder }}"
            {{ $attributes->except(['class', 'label', 'placeholder']) }}>
    </span>
</label>
