@props(['label' => 'Dışa Aktar', 'ariaLabel' => null])
<details {{ $attributes->class(['page-action-menu', 'v65-standard-action-menu']) }} data-v65-action-menu>
    <summary class="button-secondary" aria-label="{{ $ariaLabel ?: $label }}">{{ $label }} <span aria-hidden="true">⌄</span></summary>
    <div class="page-action-menu-items v65-standard-action-menu-panel" data-v65-action-menu-panel>
        {{ $slot }}
    </div>
</details>
