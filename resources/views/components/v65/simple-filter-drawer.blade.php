@props(['title' => 'Filtreler'])
<details {{ $attributes->class(['v65-simple-filter-drawer']) }} data-v65-action-menu wire:ignore.self>
    <summary class="v65-filter-trigger" aria-label="Filtre panelini aç veya kapat">{{ $title }}</summary>
    <div class="v65-simple-filter-panel">
        <header class="v65-simple-filter-head">
            <strong>Filtreleme</strong>
            <button type="button" class="button-secondary" data-v65-simple-filter-close aria-label="Filtre panelini kapat">Kapat</button>
        </header>
        <div class="v65-simple-filter-fields">
            {{ $slot }}
        </div>
        <footer class="v65-simple-filter-foot"><small>Seçimler otomatik uygulanır.</small></footer>
    </div>
</details>
