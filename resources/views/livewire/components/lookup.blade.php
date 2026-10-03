<div class="lookup">
    <input
        wire:model.live.debounce.250ms="query"
        wire:keydown.enter.prevent="chooseExact"
        type="search"
        autocomplete="off"
        placeholder="Kod veya ad ile ara"
    >

    @if ($results !== [])
        <div class="lookup-results">
            @foreach ($results as $result)
                <button type="button" wire:click="select('{{ $result['id'] }}')">
                    <strong>{{ $result['code'] }}</strong>
                    <span>{{ $result['label'] }}</span>
                </button>
            @endforeach
        </div>
    @endif
</div>
