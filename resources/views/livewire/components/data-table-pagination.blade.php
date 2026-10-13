{{-- Explicitly styled pagination: never relies on Tailwind utility classes or missing locale keys. --}}
@if ($paginator->hasPages())
    <nav class="mars-pagination" aria-label="Tablo sayfaları" role="navigation">
        <span class="mars-pagination-range">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} / {{ $paginator->total() }} kayıt</span>
        <div class="mars-pagination-controls">
            @if ($paginator->onFirstPage())
                <span class="mars-page-button mars-page-disabled" aria-disabled="true">Önceki</span>
            @else
                <button type="button" class="mars-page-button" wire:click="previousPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" aria-label="Önceki sayfa">Önceki</button>
            @endif

            <span class="mars-pagination-numbers">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="mars-page-ellipsis" aria-hidden="true">{{ $element }}</span>
                    @elseif (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page === $paginator->currentPage())
                                <span class="mars-page-button mars-page-current" aria-current="page">{{ $page }}</span>
                            @else
                                <button type="button" class="mars-page-button" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" aria-label="Sayfa {{ $page }}">{{ $page }}</button>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </span>

            <span class="mars-pagination-mobile">Sayfa {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>

            @if ($paginator->hasMorePages())
                <button type="button" class="mars-page-button" wire:click="nextPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" aria-label="Sonraki sayfa">Sonraki</button>
            @else
                <span class="mars-page-button mars-page-disabled" aria-disabled="true">Sonraki</span>
            @endif
        </div>
    </nav>
@endif
