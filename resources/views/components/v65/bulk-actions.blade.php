@props(['count' => 0, 'label' => 'kayıt seçildi'])
@if((int) $count > 0)
    <div {{ $attributes->class(['v65-bulk-actions']) }} role="status">
        <strong class="v65-bulk-actions-count">{{ (int) $count }} {{ $label }}</strong>
        <div class="v65-bulk-actions-controls">{{ $slot }}</div>
    </div>
@endif
