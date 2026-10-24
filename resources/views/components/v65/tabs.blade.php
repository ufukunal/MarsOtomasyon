@props(['tabs' => [], 'active' => null, 'model' => 'activeTab', 'label' => 'Ekran sekmeleri', 'panelPrefix' => null])
<nav {{ $attributes->class(['tabs', 'v65-tabs']) }} role="tablist" aria-label="{{ $label }}" data-v65-tabs>
    @foreach($tabs as $key => $text)
        <button type="button" role="tab"
            @class(['tab', 'v65-tab', 'is-active' => (string) $active === (string) $key])
            aria-selected="{{ (string) $active === (string) $key ? 'true' : 'false' }}"
            @if($panelPrefix) id="{{ $panelPrefix }}-tab-{{ $key }}" aria-controls="{{ $panelPrefix }}-panel-{{ $key }}" @endif
            tabindex="{{ (string) $active === (string) $key ? '0' : '-1' }}"
            wire:click="$set('{{ $model }}', '{{ $key }}')">{{ $text }}</button>
    @endforeach
</nav>
