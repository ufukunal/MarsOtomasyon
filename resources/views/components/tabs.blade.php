@props(['tabs' => [], 'active' => null])
<nav class="tabs" aria-label="Sekmeler">
    @foreach($tabs as $key => $label)
        <button type="button" @class(['tab', 'is-active' => $active === $key]) wire:click="$set('activeTab','{{ $key }}')">{{ $label }}</button>
    @endforeach
</nav>
