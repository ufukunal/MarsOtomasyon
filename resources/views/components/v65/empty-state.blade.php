@props(['title' => 'Kayıt bulunamadı', 'description' => null])
<div {{ $attributes->class(['v65-empty-state']) }} role="status">
    <strong class="v65-empty-state-title">{{ $title }}</strong>
    @if($description)
        <p class="v65-empty-state-description">{{ $description }}</p>
    @endif
    @if(trim((string) $slot) !== '')
        <div class="v65-empty-state-actions">{{ $slot }}</div>
    @endif
</div>
