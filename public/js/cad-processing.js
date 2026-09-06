(() => {
    const node = document.querySelector('[data-cad-processing]');
    if (!(node instanceof HTMLElement)) return;

    const url = node.dataset.statusUrl;
    const csrf = node.dataset.csrf;
    if (!url || !csrf) return;

    let attempts = 0;
    const poll = async () => {
        attempts += 1;
        try {
            const response = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
            });
            if (!response.ok) throw new Error(`status ${response.status}`);
            const payload = await response.json();
            if (payload.status === 'ready' || payload.status === 'failed') {
                window.location.reload();
                return;
            }
        } catch {
            node.textContent = 'Provider durumu şu anda yenilenemiyor. Orijinal dosya erişimi etkilenmedi.';
        }

        if (attempts < 150) window.setTimeout(poll, 2000);
    };

    window.setTimeout(poll, 1500);
})();
