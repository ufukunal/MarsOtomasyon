<?php

it('keeps one-click PNG clipboard writes for HTTPS and provides an HTTP image fallback', function (): void {
    $js = file_get_contents(base_path('resources/js/document-print-preview.js'));
    $markup = file_get_contents(base_path('resources/views/components/v65/document-print-options.blade.php'));
    $styles = file_get_contents(base_path('resources/css/v65-document-preview.css'));

    expect($js)->toContain('window.isSecureContext')
        ->toContain("typeof navigator.clipboard?.write === 'function'")
        ->toContain("new ClipboardItem({ 'image/png': image })")
        ->toContain("imageLink.click()")
        ->toContain("'Resmi Aç ve Kopyala'")
        ->toContain("dialog.querySelector('[data-v65-preview-image-open]').hidden = false")
        ->toContain('data-v65-preview-image-download')
        ->and($markup)->toContain('data-v65-preview-image-open')
        ->toContain('data-v65-preview-image-download')
        ->toContain('download="belge-a4.png"')
        ->and($styles)->toContain('.v65-document-preview-toolbar [hidden]');
});
