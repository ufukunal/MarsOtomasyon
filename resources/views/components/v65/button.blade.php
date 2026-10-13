@props(['variant' => 'primary', 'size' => 'md', 'href' => null, 'type' => 'button'])
@php
    $variant = in_array($variant, ['primary', 'secondary', 'ghost', 'danger', 'success', 'warning'], true) ? $variant : 'primary';
    $size = in_array($size, ['xs', 'sm', 'md', 'lg', 'xl'], true) ? $size : 'md';
    $classes = ['v65-btn', 'v65-btn--'.$variant, 'v65-btn--'.$size];
@endphp
@if($href !== null)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
