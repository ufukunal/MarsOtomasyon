@props(['document'])

@php
    $printVariants = \App\Enums\BusinessDocumentPrintVariant::cases();
@endphp
<div class="v65-document-print-actions">
    <details class="v65-document-print-picker">
        <summary class="v65-btn v65-btn--secondary v65-btn--sm" aria-label="Belge yazdırma seçenekleri">Yazdır</summary>
        <div class="v65-document-print-menu" role="group" aria-label="Yazdırma türü">
            <strong>Çıktı Tasarımını Seçin</strong>
            <small>Çıktı yalnız kaydedilmiş belgeyi kullanır.</small>
            @foreach($printVariants as $printVariant)
                <div class="v65-document-print-option {{ $printVariant->isSlip() ? 'v65-slip-print-option' : '' }}">
                    <span>{{ $printVariant->label() }}</span>
                    <button type="button" class="v65-document-print-preview-link"
                        aria-label="{{ $printVariant->label() }} önizle"
                        data-v65-preview-open
                        data-preview-label="{{ $printVariant->label() }}"
                        data-preview-html="{{ route('documents.print', ['document' => $document->id, 'format' => 'html', 'variant' => $printVariant->value, 'embedded' => 1]) }}"
                        data-preview-pdf="{{ route('documents.print', ['document' => $document->id, 'format' => 'pdf', 'variant' => $printVariant->value]) }}"
                        data-preview-png="{{ route('documents.print', ['document' => $document->id, 'format' => 'png', 'variant' => $printVariant->value]) }}">Önizle</button>
                    <a href="{{ route('documents.print', ['document' => $document->id, 'format' => 'pdf', 'variant' => $printVariant->value]) }}"
                        target="_blank" rel="noopener noreferrer" aria-label="{{ $printVariant->label() }} PDF indir">PDF İndir</a>
                </div>
            @endforeach
        </div>
    </details>
    <dialog class="v65-document-preview-dialog" data-v65-document-preview
        aria-labelledby="v65-document-preview-heading-{{ $document->id }}">
        <header class="v65-document-preview-header">
            <div>
                <h2 id="v65-document-preview-heading-{{ $document->id }}">A4 Önizleme</h2>
                <small data-v65-preview-label></small>
            </div>
            <button type="button" class="v65-document-preview-close" data-v65-preview-close aria-label="Önizlemeyi kapat">×</button>
        </header>
        <div class="v65-document-preview-toolbar">
            <a href="#" data-v65-preview-pdf target="_blank" rel="noopener noreferrer">PDF İndir</a>
            <button type="button" data-v65-preview-print>Yazdır</button>
            <button type="button" data-v65-preview-copy>Resim Olarak Kopyala</button>
        </div>
        <div class="v65-document-preview-viewport">
            <div class="v65-document-preview-sheet">
                <iframe title="A4 belge önizlemesi" data-v65-preview-frame></iframe>
            </div>
        </div>
        <footer class="v65-document-preview-footer">
            <span>Resim olarak kopyalama çok sayfalı belgelerde ilk A4 sayfasını kopyalar.</span>
            <span role="status" aria-live="polite" data-v65-preview-status></span>
        </footer>
    </dialog>
    @if($document->document_type !== \App\Enums\DocumentType::Quote)
        <a class="v65-btn v65-btn--secondary v65-btn--sm v65-document-direct-pdf"
            href="{{ route('documents.print', ['document' => $document->id, 'format' => 'pdf', 'variant' => \App\Enums\BusinessDocumentPrintVariant::PlainPriced->value]) }}"
            title="Düz Fiyatlı tasarımla tek tıkla indir"
            aria-label="Düz Fiyatlı belgeyi doğrudan PDF indir">PDF İndir</a>
    @endif
</div>
