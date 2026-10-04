@props(['type' => 'success'])
<div {{ $attributes->class(['toast', 'toast-'.$type]) }} role="status">{{ $slot }}</div>
