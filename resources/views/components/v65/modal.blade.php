@props(['id', 'title', 'size' => 'md'])
@php
    $size = in_array($size, ['sm', 'md', 'lg'], true) ? $size : 'md';
@endphp
<dialog id="{{ $id }}" {{ $attributes->class(['v65-modal', 'v65-modal--'.$size]) }} data-v65-modal wire:ignore.self aria-labelledby="{{ $id }}-title">
    <header class="v65-modal-header">
        <h2 id="{{ $id }}-title">{{ $title }}</h2>
        <button type="button" class="v65-modal-close" data-v65-modal-close aria-label="Pencereyi kapat">×</button>
    </header>
    <div class="v65-modal-body">{{ $slot }}</div>
    @isset($footer)
        <footer class="v65-modal-footer">{{ $footer }}</footer>
    @endisset
</dialog>
