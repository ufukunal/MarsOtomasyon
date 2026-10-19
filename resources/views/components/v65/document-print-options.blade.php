@props(['document'])

@php
    $printVariants = \App\Enums\BusinessDocumentPrintVariant::cases();
@endphp
<div class="v65-document-print-actions">
<details class="v65-document-print-picker">
    <summary class="v65-btn v65-btn--secondary v65-btn--sm" aria-label="Belge yazdırma seçenekleri">
        Yazdır
    </summary>
    <div class="v65-document-print-menu" role="group" aria-label="Yazdırma türü">
        <strong>Yazdırma Tasarımı</strong>
        <small>Çıktı yalnız kaydedilmiş belgeyi kullanır.</small>
        @foreach($printVariants as $printVariant)
            <div class="v65-document-print-option">
                <span>{{ $printVariant->label() }}</span>
                <a href="{{ route('documents.print', ['document' => $document->id, 'format' => 'html', 'variant' => $printVariant->value]) }}"
                    target="_blank" rel="noopener noreferrer" aria-label="{{ $printVariant->label() }} önizle">Önizle</a>
                <a href="{{ route('documents.print', ['document' => $document->id, 'format' => 'pdf', 'variant' => $printVariant->value]) }}"
                    aria-label="{{ $printVariant->label() }} PDF indir">PDF İndir</a>
            </div>
        @endforeach
    </div>
</details>
<a class="v65-btn v65-btn--secondary v65-btn--sm v65-document-direct-pdf"
    href="{{ route('documents.print', ['document' => $document->id, 'format' => 'pdf', 'variant' => \App\Enums\BusinessDocumentPrintVariant::PlainPriced->value]) }}"
    title="Düz Fiyatlı tasarımla tek tıkla indir"
    aria-label="Düz Fiyatlı belgeyi doğrudan PDF indir">PDF İndir</a>
</div>
