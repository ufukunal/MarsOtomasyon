@props(['tabs' => [], 'active' => null])
<nav class="tabs" role="tablist" aria-label="Ekran sekmeleri">
    @foreach($tabs as $key => $label)
        <button type="button" role="tab"
            @class(['tab', 'is-active' => $active === $key])
            aria-selected="{{ $active === $key ? 'true' : 'false' }}"
            wire:click="$set('activeTab','{{ $key }}')">{{ $label }}</button>
    @endforeach
</nav>
