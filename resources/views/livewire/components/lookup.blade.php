<div class="lookup">
    <div class="lookup-input-row">
        <input
            data-lookup-input
            wire:model.live.debounce.250ms="query"
            wire:keydown.arrow-down.prevent="moveHighlight(1)"
            wire:keydown.arrow-up.prevent="moveHighlight(-1)"
            wire:keydown.enter.prevent="chooseExact"
            wire:keydown.escape.prevent="closeResults"
            type="search"
            autocomplete="off"
            placeholder="Kod veya ad ile ara"
        >
        <button type="button" class="lookup-detail-button" wire:click="openDetailed">Detaylı</button>
    </div>

    @if ($results !== [])
        <div class="lookup-results">
            @foreach ($results as $index => $result)
                <button type="button"
                        @class(['is-highlighted' => $highlighted === $index])
                        wire:click="select('{{ $result['id'] }}')">
                    <strong>{{ $result['code'] }}</strong>
                    <span>{{ $result['label'] }}</span>
                </button>
            @endforeach
        </div>
    @endif

    @if($detailedOpen)
        <div class="lookup-modal-backdrop" wire:keydown.escape.window="closeResults">
            <section class="lookup-modal" role="dialog" aria-modal="true">
                <header>
                    <strong>Detaylı arama</strong>
                    <button type="button" wire:click="closeResults">Kapat</button>
                </header>
                <div class="lookup-modal-list">
                    @forelse($detailedResults as $result)
                        <button type="button" wire:click="select('{{ $result['id'] }}')">
                            <strong>{{ $result['code'] }}</strong>
                            <span>{{ $result['label'] }}</span>
                        </button>
                    @empty
                        <p>Sonuç bulunamadı.</p>
                    @endforelse
                </div>
            </section>
        </div>
    @endif
</div>
