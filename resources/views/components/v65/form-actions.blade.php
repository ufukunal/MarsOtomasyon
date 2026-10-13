@props(['align' => 'end'])
@php
    $align = in_array($align, ['start', 'end', 'between'], true) ? $align : 'end';
@endphp
<div {{ $attributes->class(['v65-form-actions', 'v65-form-actions--'.$align]) }}>
    {{ $slot }}
</div>
