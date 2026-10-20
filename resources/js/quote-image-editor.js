/**
 * Private, dependency-free canvas editor for quote-only fabrication photographs.
 * Original files stay immutable. Livewire receives a rasterized new version.
 */
const quoteImageEditors = new WeakMap();

function quoteImageEditor(root) {
    if (quoteImageEditors.has(root)) return quoteImageEditors.get(root);
    const canvas = root.querySelector('[data-v65-image-canvas]');
    const overlay = root.querySelector('[data-v65-image-overlay]');
    const ctx = canvas.getContext('2d');
    const over = overlay.getContext('2d');
    const widthInput = root.querySelector('[data-v65-image-width]');
    const status = root.querySelector('[data-v65-image-status]');
    const color = root.querySelector('[data-v65-image-color]');
    const textInput = root.querySelector('[data-v65-image-text]');
    const state = {
        root, canvas, overlay, ctx, over, widthInput, status, color, textInput,
        id: null, item: null, mode: null, history: [], cursor: -1, steps: [],
        rect: null, start: null, moving: false, dirty: false, busy: false, generation: 0,
    };
    quoteImageEditors.set(root, state);
    return state;
}

function quoteStatus(s, message) {
    s.status.textContent = message;
}

function quoteSyncOverlay(s) {
    s.overlay.width = s.canvas.width;
    s.overlay.height = s.canvas.height;
    s.widthInput.value = s.canvas.width || '';
    s.over.clearRect(0, 0, s.overlay.width, s.overlay.height);
    s.rect = null;
    s.start = null;
}

function quoteSnapshot(s, step) {
    s.history = s.history.slice(0, s.cursor + 1);
    s.history.push(s.canvas.toDataURL('image/png'));
    if (s.history.length > 10) s.history.shift();
    s.cursor = s.history.length - 1;
    if (step) s.steps.push(step);
    s.dirty = s.cursor > 0 || s.steps.length > 0;
    quoteStatus(s, s.dirty ? 'Değişiklikler kaydedilmedi.' : 'Görsel hazır.');
}

async function quoteLoadCanvas(s, url, fresh = true) {
    const token = ++s.generation;
    const img = new Image();
    img.decoding = 'async';
    img.src = url;
    await new Promise((resolve, reject) => {
        img.onload = resolve;
        img.onerror = () => reject(new Error('Görsel yüklenemedi.'));
    });
    if (token !== s.generation || !s.root.isConnected) return;
    const ratio = Math.min(1, 3200 / img.naturalWidth, 3200 / img.naturalHeight,
        Math.sqrt(8000000 / (img.naturalWidth * img.naturalHeight)));
    s.canvas.width = Math.max(1, Math.round(img.naturalWidth * ratio));
    s.canvas.height = Math.max(1, Math.round(img.naturalHeight * ratio));
    s.ctx.drawImage(img, 0, 0, s.canvas.width, s.canvas.height);
    quoteSyncOverlay(s);
    if (fresh) {
        s.history = [];
        s.cursor = -1;
        s.steps = [];
        s.dirty = false;
        s.mode = null;
        quoteSnapshot(s);
    }
    quoteStatus(s, s.dirty ? 'Değişiklikler kaydedilmedi.' : 'Görsel hazır.');
}

async function quoteSelect(s, button, original = false) {
    if (s.busy) return;
    if (s.dirty && !window.confirm('Kaydedilmemiş görsel değişikliklerini iptal etmek istiyor musunuz?')) return;
    const id = Number(button.dataset.imageId);
    if (!Number.isSafeInteger(id) || id <= 0) return;
    s.id = id;
    s.item = button;
    s.root.closest('dialog')?.querySelectorAll('[data-v65-image-select]').forEach(
        (element) => element.classList.toggle('is-selected', element === button)
    );
    quoteStatus(s, 'Görsel açılıyor…');
    try {
        await quoteLoadCanvas(s, original ? button.dataset.imageOriginal : button.dataset.imageUrl);
    } catch {
        quoteStatus(s, 'Görsel yüklenemedi. Dosyayı ve erişim yetkisini kontrol edin.');
    }
}

function quotePosition(s, event) {
    const r = s.overlay.getBoundingClientRect();
    return {
        x: Math.round(Math.max(0, Math.min(s.canvas.width, (event.clientX - r.left) * s.canvas.width / r.width))),
        y: Math.round(Math.max(0, Math.min(s.canvas.height, (event.clientY - r.top) * s.canvas.height / r.height))),
    };
}

function quoteRectangle(a, b) {
    return { x: Math.min(a.x, b.x), y: Math.min(a.y, b.y),
        w: Math.abs(b.x - a.x), h: Math.abs(b.y - a.y) };
}

function quoteDrawShape(ctx, mode, a, b, hex, imageWidth) {
    ctx.strokeStyle = hex;
    ctx.fillStyle = hex;
    ctx.lineWidth = Math.max(2, Math.round(imageWidth / 320));
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    const r = quoteRectangle(a, b);
    if (mode === 'crop') {
        ctx.setLineDash([Math.max(5, ctx.lineWidth * 3), Math.max(4, ctx.lineWidth * 2)]);
        ctx.strokeRect(r.x, r.y, r.w, r.h);
        ctx.setLineDash([]);
    } else if (mode === 'rectangle') {
        ctx.strokeRect(r.x, r.y, r.w, r.h);
    } else if (mode === 'circle') {
        ctx.beginPath();
        ctx.ellipse(r.x + r.w / 2, r.y + r.h / 2, Math.max(1, r.w / 2), Math.max(1, r.h / 2), 0, 0, Math.PI * 2);
        ctx.stroke();
    } else if (mode === 'arrow') {
        const angle = Math.atan2(b.y - a.y, b.x - a.x);
        const head = Math.max(12, imageWidth / 35);
        ctx.beginPath();
        ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y);
        ctx.moveTo(b.x - head * Math.cos(angle - Math.PI / 6), b.y - head * Math.sin(angle - Math.PI / 6));
        ctx.lineTo(b.x, b.y);
        ctx.lineTo(b.x - head * Math.cos(angle + Math.PI / 6), b.y - head * Math.sin(angle + Math.PI / 6));
        ctx.stroke();
    }
}

function quoteRestore(s, index) {
    if (index < 0 || index >= s.history.length) return;
    s.cursor = index;
    const image = new Image();
    const token = ++s.generation;
    image.onload = () => {
        if (token !== s.generation || !s.root.isConnected) return;
        s.canvas.width = image.naturalWidth; s.canvas.height = image.naturalHeight;
        s.ctx.drawImage(image, 0, 0);
        quoteSyncOverlay(s);
        s.dirty = s.cursor !== 0;
        quoteStatus(s, s.dirty ? 'Değişiklikler kaydedilmedi.' : 'Görsel hazır.');
    };
    image.src = s.history[index];
}

function quoteTransform(s, direction) {
    const old = s.canvas;
    const turn = direction === 'rotate-left' || direction === 'rotate-right';
    const buffer = document.createElement('canvas');
    buffer.width = turn ? old.height : old.width;
    buffer.height = turn ? old.width : old.height;
    const ctx = buffer.getContext('2d');
    ctx.translate(buffer.width / 2, buffer.height / 2);
    if (direction === 'rotate-left') ctx.rotate(-Math.PI / 2);
    if (direction === 'rotate-right') ctx.rotate(Math.PI / 2);
    if (direction === 'flip-horizontal') ctx.scale(-1, 1);
    if (direction === 'flip-vertical') ctx.scale(1, -1);
    ctx.drawImage(old, -old.width / 2, -old.height / 2);
    old.width = buffer.width; old.height = buffer.height;
    s.ctx.drawImage(buffer, 0, 0);
    quoteSyncOverlay(s);
    quoteSnapshot(s, direction);
}

function quoteResize(s) {
    const width = Number(s.widthInput.value);
    if (!Number.isInteger(width) || width < 1 || width > 3200) {
        quoteStatus(s, 'Genişlik 1–3200 piksel arasında olmalıdır.');
        return;
    }
    const height = Math.max(1, Math.round(s.canvas.height * width / s.canvas.width));
    if (height > 3200 || height * width > 10000000) {
        quoteStatus(s, 'Görsel çok büyük; daha küçük bir genişlik girin.');
        return;
    }
    if (width === s.canvas.width) return;
    const buffer = document.createElement('canvas');
    buffer.width = width; buffer.height = height;
    const ctx = buffer.getContext('2d');
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(s.canvas, 0, 0, width, height);
    s.canvas.width = width; s.canvas.height = height;
    s.ctx.drawImage(buffer, 0, 0);
    quoteSyncOverlay(s);
    quoteSnapshot(s, 'resize ' + width + 'px');
}

async function quoteSave(s, asNew = false) {
    if (s.busy || !s.id || !s.dirty) {
        quoteStatus(s, 'Kaydedilecek bir değişiklik yok.');
        return;
    }
    const componentElement = s.root.closest('[wire\\:id]');
    const component = componentElement && window.Livewire?.find(componentElement.getAttribute('wire:id'));
    if (!component) {
        quoteStatus(s, 'Teklif bağlantısı bulunamadı; sayfayı yenileyin.');
        return;
    }
    const file = await new Promise(resolve => s.canvas.toBlob(resolve, 'image/png'));
    if (!file || file.size > 5 * 1024 * 1024) {
        quoteStatus(s, 'Görsel 5 MB sınırını aşıyor; boyutlandırıp tekrar deneyin.');
        return;
    }
    s.busy = true;
    quoteStatus(s, 'Düzenlenen görsel kaydediliyor…');
    const uploaded = new File([file], 'quote-image-edited.png', { type: 'image/png' });
    component.upload('customImageEditUpload', uploaded, () => {
        component.call(asNew ? 'saveCustomImageAsNew' : 'saveCustomImageEdit',
            ...(asNew ? [] : [s.id, s.steps.slice(-40)]))
            .then(() => {
                if (s.busy) quoteStatus(s, 'Kaydetme onayı alınamadı; form hatalarını kontrol edin.');
            })
            .catch(() => quoteStatus(s, 'Kaydetme başarısız. Form hatalarını kontrol edin.'))
            .finally(() => { s.busy = false; });
    }, () => {
        s.busy = false;
        quoteStatus(s, 'Görsel yüklemesi başarısız.');
    });
}

function quoteAction(s, action) {
    if (!s.id || s.busy || s.history.length === 0) return;
    if (['rotate-left', 'rotate-right', 'flip-horizontal', 'flip-vertical'].includes(action)) {
        quoteTransform(s, action);
    } else if (action === 'crop-apply') {
        if (!s.rect || s.rect.w < 3 || s.rect.h < 3) {
            quoteStatus(s, 'Önce kırpma aracını seçip görselde bir alan çizin.');
            return;
        }
        const rect = s.rect;
        const buffer = document.createElement('canvas');
        buffer.width = rect.w; buffer.height = rect.h;
        buffer.getContext('2d').drawImage(s.canvas, rect.x, rect.y, rect.w, rect.h, 0, 0, rect.w, rect.h);
        s.canvas.width = rect.w; s.canvas.height = rect.h;
        s.ctx.drawImage(buffer, 0, 0);
        quoteSyncOverlay(s);
        quoteSnapshot(s, 'crop');
    } else if (action === 'resize') quoteResize(s);
    else if (action === 'undo') quoteRestore(s, s.cursor - 1);
    else if (action === 'redo') quoteRestore(s, s.cursor + 1);
    else if (action === 'reset') quoteRestore(s, 0);
    else if (action === 'original') quoteSelect(s, s.item, true);
    else if (action === 'save') void quoteSave(s);
    else if (action === 'save-as') void quoteSave(s, true);
}

function quotePaintPreview(s, point) {
    s.over.clearRect(0, 0, s.overlay.width, s.overlay.height);
    if (!s.start || !point) return;
    if (s.mode !== 'draw' && s.mode !== 'text') {
        quoteDrawShape(s.over, s.mode, s.start, point, s.color.value, s.canvas.width);
    }
}

document.addEventListener('pointerdown', (event) => {
    const overlay = event.target;
    if (!(overlay instanceof HTMLCanvasElement) || !overlay.matches('[data-v65-image-overlay]')) return;
    const s = quoteImageEditors.get(overlay.closest('[data-v65-quote-image-editor]'));
    if (!s || !s.id || s.busy || !s.mode) return;
    const pos = quotePosition(s, event);
    if (s.mode === 'text') {
        const label = s.textInput.value.trim().slice(0, 120);
        if (!label) {
            quoteStatus(s, 'Önce metin alanına bir açıklama yazın.');
            return;
        }
        s.ctx.fillStyle = s.color.value;
        s.ctx.font = 'bold ' + Math.max(14, Math.round(s.canvas.width / 35)) + 'px sans-serif';
        s.ctx.fillText(label, pos.x, pos.y, s.canvas.width - pos.x);
        quoteSnapshot(s, 'text');
        return;
    }
    s.start = pos;
    s.moving = true;
    overlay.setPointerCapture(event.pointerId);
    if (s.mode === 'draw') {
        s.ctx.beginPath(); s.ctx.moveTo(pos.x, pos.y);
        s.ctx.strokeStyle = s.color.value;
        s.ctx.lineWidth = Math.max(2, s.canvas.width / 320);
        s.ctx.lineCap = 'round';
    }
});

document.addEventListener('pointermove', (event) => {
    const overlay = event.target;
    if (!(overlay instanceof HTMLCanvasElement) || !overlay.matches('[data-v65-image-overlay]')) return;
    const s = quoteImageEditors.get(overlay.closest('[data-v65-quote-image-editor]'));
    if (!s?.moving) return;
    const pos = quotePosition(s, event);
    if (s.mode === 'draw') {
        s.ctx.lineTo(pos.x, pos.y); s.ctx.stroke();
    } else quotePaintPreview(s, pos);
});

document.addEventListener('pointerup', (event) => {
    const overlay = event.target;
    if (!(overlay instanceof HTMLCanvasElement) || !overlay.matches('[data-v65-image-overlay]')) return;
    const s = quoteImageEditors.get(overlay.closest('[data-v65-quote-image-editor]'));
    if (!s?.moving) return;
    s.moving = false;
    const p = quotePosition(s, event);
    if (s.mode === 'crop') {
        s.rect = quoteRectangle(s.start, p);
        quotePaintPreview(s, p);
        quoteStatus(s, 'Kırpmayı Uygula ile seçili bölgeyi onaylayın.');
    } else {
        if (s.mode !== 'draw') quoteDrawShape(s.ctx, s.mode, s.start, p, s.color.value, s.canvas.width);
        s.over.clearRect(0, 0, s.overlay.width, s.overlay.height);
        quoteSnapshot(s, s.mode);
    }
    s.start = null;
});

document.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof Element)) return;
    const select = target.closest('[data-v65-image-select]');
    if (select instanceof HTMLButtonElement) {
        const root = select.closest('dialog')?.querySelector('[data-v65-quote-image-editor]');
        if (root) void quoteSelect(quoteImageEditor(root), select);
        return;
    }
    const tool = target.closest('[data-v65-image-tool]');
    if (tool instanceof HTMLButtonElement) {
        const root = tool.closest('[data-v65-quote-image-editor]');
        if (!root) return;
        const s = quoteImageEditor(root);
        s.mode = tool.dataset.v65ImageTool;
        root.querySelectorAll('[data-v65-image-tool]').forEach(b => b.classList.toggle('is-active', b === tool));
        quoteStatus(s, 'Araç seçildi: ' + tool.textContent.trim());
        return;
    }
    const action = target.closest('[data-v65-image-action]');
    if (action instanceof HTMLButtonElement) {
        const root = action.closest('[data-v65-quote-image-editor]');
        if (root) quoteAction(quoteImageEditor(root), action.dataset.v65ImageAction);
    }
});

document.addEventListener('v65-custom-quote-open', () => {
    // Livewire renders the selected row before dispatching this event.
    queueMicrotask(() => {
        const dialog = document.getElementById('v65-custom-quote-dialog');
        const root = dialog?.querySelector('[data-v65-quote-image-editor]');
        const first = dialog?.querySelector('[data-v65-image-select]');
        if (root && first) {
            const s = quoteImageEditor(root);
            s.dirty = false; // A new popup visit explicitly starts a new edit session.
            void quoteSelect(s, first);
        }
    });
});


// Server-side authorization and persistence, not the upload callback, confirms success.
document.addEventListener('v65-quote-image-edited', (event) => {
    const dialog = document.getElementById('v65-custom-quote-dialog');
    const root = dialog?.querySelector('[data-v65-quote-image-editor]');
    const s = root && quoteImageEditors.get(root);
    const id = Number(event.detail?.attachmentId);
    if (!s || !Number.isSafeInteger(id) || id !== s.id) return;

    s.busy = false;
    s.dirty = false;
    s.steps = [];
    const item = dialog.querySelector('[data-v65-image-select][data-image-id="' + id + '"]');
    if (item instanceof HTMLButtonElement) {
        s.item = item;
        const current = item.dataset.imageUrl.split('?')[0] + '?refresh=' + Date.now();
        item.dataset.imageUrl = current;
        const preview = item.closest('.v65-custom-quote-image')?.querySelector('img');
        if (preview) preview.src = current;
    }
    quoteStatus(s, 'Yeni görsel sürümü kaydedildi. Orijinal ve geçmiş revizyonlar korundu.');
});


document.addEventListener('v65-quote-image-added', () => {
    const dialog = document.getElementById('v65-custom-quote-dialog');
    const root = dialog?.querySelector('[data-v65-quote-image-editor]');
    const s = root && quoteImageEditors.get(root);
    if (!s) return;
    s.busy = false;
    s.dirty = false;
    s.steps = [];
    quoteStatus(s, 'Düzenlenmiş resim yeni bir görsel olarak eklendi. Diğer görseller korundu.');
});
