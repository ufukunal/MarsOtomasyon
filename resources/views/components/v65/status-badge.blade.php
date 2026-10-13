@props(['tone' => 'neutral', 'dot' => false])
@php
    $tone = in_array($tone, ['neutral', 'success', 'danger', 'warning', 'info'], true) ? $tone : 'neutral';
@endphp
<span {{ $attributes->class(['v65-status-badge', 'v65-status-badge--'.$tone]) }}>
    @if($dot)<span class="v65-status-badge-dot" aria-hidden="true"></span>@endif
    {{ $slot }}
</span>
