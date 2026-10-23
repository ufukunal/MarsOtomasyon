// Shared v65 document preview: preview and print the exact persisted document.
const activePreview = (element) => element.closest('[data-v65-document-preview]');

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
        if (!window.isSecureContext || !navigator.clipboard?.write || typeof ClipboardItem === 'undefined') {
            status.textContent = 'Resim kopyalama için güvenli bağlantı ve desteklenen tarayıcı gerekir.';
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
            status.textContent = error instanceof Error ? error.message : 'Resim panoya kopyalanamadı.';
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
    dialog.querySelector('[data-v65-preview-status]').textContent = '';
}, true);

document.addEventListener('click', (event) => {
    if (event.target instanceof HTMLDialogElement && event.target.matches('[data-v65-document-preview]')) {
        event.target.close();
    }
});

window.addEventListener('resize', () => {
    document.querySelectorAll('[data-v65-document-preview][open]').forEach(fitPreview);
});
