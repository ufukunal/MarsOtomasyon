@props(['items' => []])
@if(count($items) > 0)
    <div class="v65-active-filters" aria-label="Uygulanan filtreler">
        @foreach($items as $item)
            <button type="button" class="v65-filter-chip" wire:key="v65-chip-{{ $item['key'] }}" wire:click="removeV65Filter('{{ $item['key'] }}')" aria-label="{{ $item['label'] }} filtresini kaldır">
                <span>{{ $item['label'] }}</span><span aria-hidden="true">×</span>
            </button>
        @endforeach
    </div>
@endif
