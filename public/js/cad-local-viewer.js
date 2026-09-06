(() => {
    const root = document.querySelector('[data-cad-local-viewer]');
    if (!(root instanceof HTMLElement)) return;

    const sourceUrl = root.dataset.sourceUrl;
    const extension = (root.dataset.extension || '').toLowerCase();
    const host = root.querySelector('[data-cad-surface-host]');
    const status = root.querySelector('[data-cad-render-status]');
    if (!sourceUrl || !(host instanceof HTMLElement)) return;

    const state = { scale: 1, offsetX: 0, offsetY: 0, dragging: false, lastX: 0, lastY: 0 };
    let render = () => {};

    const fitBounds = (bounds, width, height) => {
        const dx = Math.max(1, bounds.maxX - bounds.minX);
        const dy = Math.max(1, bounds.maxY - bounds.minY);
        const scale = Math.min((width - 40) / dx, (height - 40) / dy);
        return {
            scale,
            offsetX: 20 - bounds.minX * scale + (width - 40 - dx * scale) / 2,
            offsetY: 20 + bounds.maxY * scale + (height - 40 - dy * scale) / 2,
        };
    };

    const wireControls = (surface) => {
        const zoom = (factor) => { state.scale *= factor; render(); };
        root.querySelector('[data-cad-fit]')?.addEventListener('click', () => {
            state.scale = 1; state.offsetX = 0; state.offsetY = 0; render(true);
        });
        root.querySelector('[data-cad-zoom-in]')?.addEventListener('click', () => zoom(1.2));
        root.querySelector('[data-cad-zoom-out]')?.addEventListener('click', () => zoom(1 / 1.2));
        surface.addEventListener('wheel', (event) => {
            event.preventDefault();
            zoom(event.deltaY < 0 ? 1.1 : 1 / 1.1);
        }, { passive: false });
        surface.addEventListener('pointerdown', (event) => {
            state.dragging = true; state.lastX = event.clientX; state.lastY = event.clientY;
            surface.setPointerCapture(event.pointerId);
        });
        surface.addEventListener('pointermove', (event) => {
            if (!state.dragging) return;
            state.offsetX += event.clientX - state.lastX;
            state.offsetY += event.clientY - state.lastY;
            state.lastX = event.clientX; state.lastY = event.clientY;
            render();
        });
        surface.addEventListener('pointerup', () => { state.dragging = false; });
    };

    const parseDxf = (text) => {
        const lines = text.replace(/\r/g, '').split('\n');
        const pairs = [];
        for (let i = 0; i + 1 < lines.length; i += 2) pairs.push([Number(lines[i].trim()), lines[i + 1].trim()]);
        const entities = [];
        let current = null;
        for (const [code, value] of pairs) {
            if (code === 0 && ['LINE', 'CIRCLE', 'LWPOLYLINE'].includes(value)) {
                if (current) entities.push(current);
                current = { type: value, layer: '0', points: [] };
                continue;
            }
            if (!current) continue;
            if (code === 8) current.layer = value;
            if (current.type === 'LINE') {
                if (code === 10) current.x1 = Number(value);
                if (code === 20) current.y1 = Number(value);
                if (code === 11) current.x2 = Number(value);
                if (code === 21) current.y2 = Number(value);
            } else if (current.type === 'CIRCLE') {
                if (code === 10) current.cx = Number(value);
                if (code === 20) current.cy = Number(value);
                if (code === 40) current.r = Number(value);
            } else if (current.type === 'LWPOLYLINE') {
                if (code === 10) current.points.push([Number(value), 0]);
                if (code === 20 && current.points.length) current.points[current.points.length - 1][1] = Number(value);
            }
        }
        if (current) entities.push(current);
        return entities;
    };

    const dxfBounds = (entities) => {
        const xs = [], ys = [];
        for (const e of entities) {
            if (e.type === 'LINE') { xs.push(e.x1, e.x2); ys.push(e.y1, e.y2); }
            if (e.type === 'CIRCLE') { xs.push(e.cx - e.r, e.cx + e.r); ys.push(e.cy - e.r, e.cy + e.r); }
            if (e.type === 'LWPOLYLINE') for (const [x, y] of e.points) { xs.push(x); ys.push(y); }
        }
        return { minX: Math.min(...xs, 0), maxX: Math.max(...xs, 1), minY: Math.min(...ys, 0), maxY: Math.max(...ys, 1) };
    };

    const showDxf = (text) => {
        const entities = parseDxf(text);
        const layers = [...new Set(entities.map((e) => e.layer || '0'))];
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(800, host.clientWidth || 800);
        canvas.height = 480;
        canvas.dataset.cadSurface = 'dxf';
        host.replaceChildren(canvas);
        const ctx = canvas.getContext('2d');
        if (!ctx) throw new Error('Canvas is unavailable');
        const bounds = dxfBounds(entities);
        let base = fitBounds(bounds, canvas.width, canvas.height);

        render = (fit = false) => {
            if (fit) base = fitBounds(bounds, canvas.width, canvas.height);
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.save();
            ctx.translate(base.offsetX + state.offsetX, base.offsetY + state.offsetY);
            ctx.scale(base.scale * state.scale, -base.scale * state.scale);
            ctx.lineWidth = 1 / (base.scale * state.scale);
            for (const e of entities) {
                ctx.beginPath();
                if (e.type === 'LINE') { ctx.moveTo(e.x1, e.y1); ctx.lineTo(e.x2, e.y2); }
                if (e.type === 'CIRCLE') ctx.arc(e.cx, e.cy, e.r, 0, Math.PI * 2);
                if (e.type === 'LWPOLYLINE' && e.points.length) {
                    ctx.moveTo(e.points[0][0], e.points[0][1]);
                    e.points.slice(1).forEach(([x, y]) => ctx.lineTo(x, y));
                }
                ctx.stroke();
            }
            ctx.restore();
        };
        render(true);
        wireControls(canvas);
        root.dataset.cadLayers = String(layers.length);
    };

    const parseObj = (text) => {
        const vertices = [], faces = [];
        for (const raw of text.split(/\r?\n/)) {
            const line = raw.trim();
            if (line.startsWith('v ')) {
                const parts = line.split(/\s+/).slice(1).map(Number);
                vertices.push([parts[0] || 0, parts[1] || 0, parts[2] || 0]);
            }
            if (line.startsWith('f ')) {
                faces.push(line.split(/\s+/).slice(1).map((part) => Number(part.split('/')[0]) - 1));
            }
        }
        return { vertices, faces };
    };

    const showObj = (text) => {
        const model = parseObj(text);
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(800, host.clientWidth || 800);
        canvas.height = 480;
        canvas.dataset.cadSurface = 'obj';
        host.replaceChildren(canvas);
        const ctx = canvas.getContext('2d');
        if (!ctx) throw new Error('Canvas is unavailable');
        let angle = 0.6;

        render = () => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            const projected = model.vertices.map(([x, y, z]) => {
                const rx = x * Math.cos(angle) - z * Math.sin(angle);
                const rz = x * Math.sin(angle) + z * Math.cos(angle);
                const scale = 120 * state.scale / Math.max(1, 4 + rz * 0.2);
                return [canvas.width / 2 + state.offsetX + rx * scale, canvas.height / 2 + state.offsetY - y * scale];
            });
            for (const face of model.faces) {
                if (face.length < 2) continue;
                ctx.beginPath();
                face.forEach((index, i) => {
                    const point = projected[index];
                    if (!point) return;
                    if (i === 0) ctx.moveTo(point[0], point[1]);
                    else ctx.lineTo(point[0], point[1]);
                });
                ctx.closePath();
                ctx.stroke();
            }
        };
        render();
        wireControls(canvas);
        canvas.addEventListener('pointermove', (event) => {
            if (event.buttons === 1 && event.shiftKey) { angle += event.movementX * 0.01; render(); }
        });
    };

    fetch(sourceUrl, { credentials: 'same-origin', headers: { 'Accept': 'text/plain' } })
        .then((response) => {
            if (!response.ok) throw new Error(`source ${response.status}`);
            return response.text();
        })
        .then((text) => {
            if (extension === 'dxf') showDxf(text);
            else if (extension === 'obj') showObj(text);
            else throw new Error('unsupported');
            root.dataset.cadRendered = 'true';
            if (status) status.textContent = 'Hazır';
        })
        .catch(() => {
            root.dataset.cadRendered = 'failed';
            if (status) status.textContent = 'Önizleme oluşturulamadı';
        });
})();
