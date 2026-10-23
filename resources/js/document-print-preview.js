// Shared v65 document preview: preview and print the exact persisted document.
const activePreview = (element) => element.closest('[data-v65-document-preview]');
const canCopyPngToClipboard = () => window.isSecureContext
    && typeof ClipboardItem !== 'undefined'
    && typeof navigator.clipboard?.write === 'function';

const fitPreview = (dialog) => {
    const sheet = dialog?.querySelector('.v65-document-preview-sheet');
    if (!sheet) return;
    sheet.style.setProperty('--v65-preview-scale', String(Math.min(1, sheet.clientWidth / 794)));
};

document.addEventListener('click', async (event) => {
    if (!(event.target instanceof Element)) return;

    const open = event.target.closest('[data-v65-preview-open]');
    if (open) {
        const picker = open.closest('.v65-document-print-actions');
        const dialog = picker?.querySelector('[data-v65-document-preview]');
        const frame = dialog?.querySelector('[data-v65-preview-frame]');
        if (!(dialog instanceof HTMLDialogElement) || !(frame instanceof HTMLIFrameElement)) return;

        dialog.querySelector('[data-v65-preview-label]').textContent = open.dataset.previewLabel || '';
        dialog.querySelector('[data-v65-preview-pdf]').href = open.dataset.previewPdf;
        dialog.dataset.imageUrl = open.dataset.previewPng;
        dialog.dataset.htmlUrl = open.dataset.previewHtml;
        dialog.dataset.copyFallback = 'false';
        const imageOpenLink = dialog.querySelector('[data-v65-preview-image-open]');
        imageOpenLink.href = open.dataset.previewPng;
        imageOpenLink.hidden = canCopyPngToClipboard();
        dialog.querySelector('[data-v65-preview-image-download]').href = open.dataset.previewPng;
        const copyButton = dialog.querySelector('[data-v65-preview-copy]');
        copyButton.textContent = canCopyPngToClipboard() ? 'Resim Olarak Kopyala' : 'Resmi Aç ve Kopyala';
        dialog.querySelector('[data-v65-preview-status]').textContent = 'A4 önizleme yükleniyor…';
        frame.dataset.ready = 'false';
        frame.src = open.dataset.previewHtml;
        open.closest('details')?.removeAttribute('open');
        dialog.showModal();
        fitPreview(dialog);
        return;
    }

    const close = event.target.closest('[data-v65-preview-close]');
    if (close) {
        activePreview(close)?.close();
        return;
    }

    const print = event.target.closest('[data-v65-preview-print]');
    if (print) {
        const dialog = activePreview(print);
        const frame = dialog?.querySelector('[data-v65-preview-frame]');
        if (frame?.dataset.ready !== 'true') {
            dialog.querySelector('[data-v65-preview-status]').textContent = 'Belge yüklenmeden yazdırılamaz.';
            return;
        }
        try {
            frame.contentWindow.focus();
            frame.contentWindow.print();
        } catch {
            window.open(dialog.dataset.htmlUrl, '_blank', 'noopener,noreferrer');
            dialog.querySelector('[data-v65-preview-status]').textContent = 'Yazdırma için belge yeni sekmede açıldı.';
        }
        return;
    }

    const copy = event.target.closest('[data-v65-preview-copy]');
    if (copy) {
        const dialog = activePreview(copy);
        const status = dialog?.querySelector('[data-v65-preview-status]');
        if (!dialog || !status) return;
        if (!canCopyPngToClipboard() || dialog.dataset.copyFallback === 'true') {
            // Browsers prohibit binary clipboard writes on an insecure HTTP origin.
            // Open the real PNG so the browser's native "Resmi kopyala" command
            // remains available; do not claim that the clipboard was changed.
            const imageLink = dialog.querySelector('[data-v65-preview-image-open]');
            if (imageLink instanceof HTMLAnchorElement) {
                imageLink.click();
            }
            status.textContent = 'HTTP veya tarayıcı kısıtlaması: Görseli açılan sekmede sağ tıklayıp “Resmi kopyala” seçin. Açılmazsa PNG Aç bağlantısını kullanın.';
            return;
        }
        copy.disabled = true;
        status.textContent = 'A4 görseli hazırlanıyor…';
        try {
            // Promise-valued ClipboardItem retains the user's click permission while
            // the server renders the first A4 page into a PNG.
            const image = fetch(dialog.dataset.imageUrl, { credentials: 'same-origin' })
                .then(async (response) => {
                    if (!response.ok) throw new Error('Görsel oluşturulamadı (' + response.status + ').');
                    const blob = await response.blob();
                    if (blob.type !== 'image/png') throw new Error('Geçersiz görsel yanıtı.');
                    return blob;
                });
            await navigator.clipboard.write([new ClipboardItem({ 'image/png': image })]);
            status.textContent = 'İlk A4 sayfası resim olarak panoya kopyalandı.';
        } catch (error) {
            dialog.dataset.copyFallback = 'true';
            copy.textContent = 'Resmi Aç ve Kopyala';
            dialog.querySelector('[data-v65-preview-image-open]').hidden = false;
            const reason = error instanceof Error ? error.message : 'Tarayıcı panoya yazmayı reddetti.';
            status.textContent = reason + ' PNG Aç bağlantısını kullanıp görsele sağ tıklayarak “Resmi kopyala” seçebilirsiniz.';
        } finally {
            copy.disabled = false;
        }
    }
});

document.addEventListener('load', (event) => {
    const frame = event.target;
    if (!(frame instanceof HTMLIFrameElement) || !frame.matches('[data-v65-preview-frame]')) return;
    const dialog = activePreview(frame);
    if (!dialog?.open || frame.src === 'about:blank') return;
    frame.dataset.ready = 'true';
    dialog.querySelector('[data-v65-preview-status]').textContent = canCopyPngToClipboard()
        ? ''
        : 'HTTP bağlantısında doğrudan resim kopyalama tarayıcı tarafından engellenir. Resmi Aç ve Kopyala veya PNG İndir kullanın.';
}, true);

document.addEventListener('click', (event) => {
    if (event.target instanceof HTMLDialogElement && event.target.matches('[data-v65-document-preview]')) {
        event.target.close();
    }
});

window.addEventListener('resize', () => {
    document.querySelectorAll('[data-v65-document-preview][open]').forEach(fitPreview);
});
