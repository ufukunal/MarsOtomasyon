@props(['title', 'description' => null])
<header class="page-header">
    <div>
        <h1>{{ $title }}</h1>
        @if($description)<p>{{ $description }}</p>@endif
    </div>
    @isset($actions)<div class="page-actions">{{ $actions }}</div>@endisset
</header>
