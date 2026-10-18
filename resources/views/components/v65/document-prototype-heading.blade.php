@props(['document' => null, 'screenType', 'indexRoute' => null, 'formId' => null, 'dirty' => false, 'canCopy' => false, 'canReopen' => false, 'editPermission' => null])

@php
    $screen = config('document_prototype.'.$screenType, []);
    $statusNames = [
        'draft' => 'Taslak', 'internal_review' => 'İç İnceleme',
        'customer_review' => 'Müşteri İncelemesi', 'pending_approval' => 'Onay Bekliyor',
        'approved' => 'Onaylı', 'confirmed' => 'Onaylandı', 'sent' => 'Gönderildi',
        'posted' => 'Kaydedildi', 'cancelled' => 'İptal', 'reversed' => 'İptal',
    ];
    $currentState = $document?->status ?? 'draft';
    $editable = $document === null || $currentState === 'draft';
    $saveLabel = $screen['save'] ?? 'Belgeyi Kaydet';
    $isInvoice = in_array($screenType, ['sales_invoice', 'supplier_invoice'], true);
    $invoicePermission = $screenType === 'sales_invoice' ? 'sales_invoices' : 'supplier_invoices';
@endphp

<header {{ $attributes->class(['v65-reference-heading']) }}>
    <div class="v65-reference-heading-main">
        <h1>{{ $screen['title'] ?? 'Belge' }}</h1>
        <p>
            {{ $screen['description'] ?? '' }}
            @if($document?->number)
                <span class="v65-reference-heading-number">{{ $document->number }}</span>
            @endif
        </p>
        @if($dirty)
            <small class="v65-reference-unsaved">Kaydedilmemiş değişiklikler mevcut; çıktı son kaydedilen belgeyi kullanır.</small>
        @endif
    </div>
    <div class="v65-reference-heading-actions">
        <span class="v65-reference-status"><i aria-hidden="true"></i>{{ $statusNames[$currentState] ?? $currentState }}</span>
        @if($document)
            <x-v65.button variant="secondary" size="sm"
                :href="route('documents.history', ['id' => $document->id])">Kayıt Geçmişi</x-v65.button>
            <x-v65.button variant="secondary" size="sm"
                :href="route('documents.print', ['document' => $document->id, 'format' => 'html'])"
                target="_blank" rel="noopener noreferrer">Yazdır</x-v65.button>
        @else
            <span class="v65-reference-print-placeholder" title="Yazdırma, ilk kayıt sonrasında kullanılabilir">Yazdır: kayıt sonrası</span>
        @endif
        @if($editable && $formId)
            @if($document && $editPermission && auth()->user()?->can($editPermission.'.update'))
                <a class="v65-btn v65-btn--secondary v65-btn--sm"
                    href="#{{ $formId }}">Düzenle</a>
            @endif
            <x-v65.button type="submit" size="sm" :form="$formId"
                wire:loading.attr="disabled" wire:target="save">{{ $isInvoice ? 'Faturayı Kaydet' : $saveLabel }}</x-v65.button>
            @if($isInvoice && $document && auth()->user()?->can($invoicePermission.'.cancel'))
                <x-v65.button variant="danger" size="sm" wire:click="deleteInvoice"
                    wire:loading.attr="disabled" wire:target="deleteInvoice">Sil</x-v65.button>
            @endif
        @elseif($document)
            @if($isInvoice)
                @if(auth()->user()?->can($invoicePermission.'.update') && auth()->user()?->can($invoicePermission.'.create')
                    && (auth()->user()?->can($invoicePermission.'.cancel') || \App\Support\Documents\DocumentEditCatalog::hasReversal($document)))
                    <x-v65.button variant="secondary" size="sm" wire:click="editInvoice"
                        wire:loading.attr="disabled" wire:target="editInvoice">Düzenle</x-v65.button>
                @endif
                @if(auth()->user()?->can($invoicePermission.'.cancel')
                    && ! \App\Support\Documents\DocumentEditCatalog::hasReversal($document))
                    <x-v65.button variant="danger" size="sm" wire:click="deleteInvoice"
                        wire:loading.attr="disabled" wire:target="deleteInvoice">Sil</x-v65.button>
                @endif
            @elseif($screenType === 'quote' && $document->number !== null && auth()->user()?->can('quotes.update'))
                <x-v65.button variant="secondary" size="sm" wire:click="revision"
                    wire:loading.attr="disabled" wire:target="revision">Düzenle</x-v65.button>
            @elseif($canReopen && $screenType === 'purchase_dispatch')
                <x-v65.button variant="secondary" size="sm" wire:click="reopenForEditing"
                    wire:loading.attr="disabled" wire:target="reopenForEditing">Düzenle</x-v65.button>
            @elseif($canCopy)
                <x-v65.button variant="secondary" size="sm" wire:click="startEditableCopy"
                    wire:loading.attr="disabled" wire:target="startEditableCopy">Düzenle</x-v65.button>
            @endif
        @endif
        @if($indexRoute)
            <x-v65.button variant="ghost" size="sm" :href="route($indexRoute)">Listeye Dön</x-v65.button>
        @endif
    </div>
</header>
