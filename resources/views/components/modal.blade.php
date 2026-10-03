@props(['title', 'open' => false])
<div x-data="{ open: @js($open) }" x-show="open" x-on:keydown.escape.window="open=false" class="modal-backdrop" style="display:none">
    <section class="modal-panel" role="dialog" aria-modal="true">
        <header class="modal-header">
            <h2>{{ $title }}</h2>
            <button type="button" x-on:click="open=false">Kapat</button>
        </header>
        <div class="modal-body">{{ $slot }}</div>
        @isset($footer)<footer class="modal-footer">{{ $footer }}</footer>@endisset
    </section>
</div>
