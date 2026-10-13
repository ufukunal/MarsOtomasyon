@props(['count' => 0, 'tab' => 'filters'])
<div class="v65-filter-control">
    <button type="button" class="v65-filter-trigger" data-v65-filter-open wire:click="openV65Filters" aria-haspopup="dialog" aria-label="Filtre panelini aç">
        <span aria-hidden="true">⚲</span> Filtreler
        @if($count > 0)<span class="v65-filter-count">{{ $count }}</span>@endif
    </button>
    <dialog class="v65-filter-dialog" data-v65-filter-dialog wire:ignore.self aria-label="Liste filtreleri">
        <div class="v65-filter-panel">
            <header class="v65-filter-header">
                <div><strong>Filtreler</strong><small>Liste görünümünü özelleştir</small></div>
                <button type="button" class="v65-filter-close" wire:click="closeV65Filters" data-v65-filter-close aria-label="Filtre panelini kapat">×</button>
            </header>
            <div class="v65-filter-tabs" role="tablist" aria-label="Filtre paneli sekmeleri">
                <button type="button" role="tab" aria-selected="{{ $tab === 'filters' ? 'true' : 'false' }}" @class(['is-active' => $tab === 'filters']) wire:click="v65SetFilterTab('filters')">Filtreleme</button>
                <button type="button" role="tab" aria-selected="{{ $tab === 'sorting' ? 'true' : 'false' }}" @class(['is-active' => $tab === 'sorting']) wire:click="v65SetFilterTab('sorting')">Sıralama</button>
            </div>
            <div class="v65-filter-body" role="tabpanel">
                @if($tab === 'filters')
                    {{ $filters }}
                @else
                    {{ $sorting }}
                @endif
            </div>
            <footer class="v65-filter-footer">
                <button type="button" class="v65-filter-clear" wire:click="clearV65Filters" data-v65-filter-close>Temizle</button>
                <button type="button" class="v65-filter-submit" wire:click="applyV65Filters" data-v65-filter-apply>Filtrele</button>
            </footer>
        </div>
    </dialog>
</div>
