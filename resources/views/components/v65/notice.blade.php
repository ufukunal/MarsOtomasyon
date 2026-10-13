@props(['tone' => 'info', 'title' => null])
@php
    $tone = in_array($tone, ['info', 'success', 'warning', 'danger'], true) ? $tone : 'info';
@endphp
<div {{ $attributes->class(['v65-notice', 'v65-notice--'.$tone]) }} role="{{ in_array($tone, ['warning', 'danger'], true) ? 'alert' : 'status' }}">
    @if($title)<strong class="v65-notice-title">{{ $title }}</strong>@endif
    <div class="v65-notice-content">{{ $slot }}</div>
</div>
