@props(['title', 'description' => null])
<div {{ $attributes->class(['v65-standard-list-heading']) }}>
    <div class="v65-standard-list-heading-copy">
        <strong>{{ $title }}</strong>
        @if($description)<small>{{ $description }}</small>@endif
    </div>
    @if(trim((string) $slot) !== '')
        <div class="v65-standard-list-heading-controls">{{ $slot }}</div>
    @endif
</div>
