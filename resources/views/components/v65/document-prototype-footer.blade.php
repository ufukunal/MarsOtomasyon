@props(['document' => null, 'screenType', 'itemCount' => 0])

<footer {{ $attributes->class(['v65-reference-document-footer']) }}>
    <p><span class="v65-reference-count-dot" aria-hidden="true"></span>
        <strong>{{ $itemCount }} kalem</strong> · Sunucu hesaplaması
    </p>
    <div class="v65-reference-footer-actions">
        @if(!$document)
            <x-v65.button variant="secondary" size="sm" wire:click="resetNewDraft"
>
                Girişi Sıfırla
            </x-v65.button>
        @elseif($document->status === 'draft' && ! in_array($screenType, ['sales_invoice', 'supplier_invoice'], true))
            <a class="v65-btn v65-btn--secondary v65-btn--sm" href="#v65-reference-workflow">
                Belge İşlemleri
            </a>
        @endif
        @unless(in_array($screenType, ['sales_invoice', 'supplier_invoice'], true))
            <x-v65.button type="submit" size="sm" wire:loading.attr="disabled" wire:target="save">
                {{ config('document_prototype.'.$screenType.'.save', 'Belgeyi Kaydet') }} →
            </x-v65.button>
        @endunless
    </div>
</footer>
