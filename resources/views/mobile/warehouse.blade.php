<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#111827">
    <link rel="manifest" href="/mobile-warehouse.webmanifest">
    <title>Mobil Depo</title>
    <style>
        :root { font-family: system-ui, sans-serif; color-scheme: light dark; }
        body { margin: 0; background: Canvas; color: CanvasText; }
        main { max-width: 44rem; margin: auto; padding: 1rem; display: grid; gap: 1rem; }
        section { border: 1px solid color-mix(in srgb, CanvasText 18%, transparent); border-radius: .8rem; padding: 1rem; }
        input, select, textarea, button { box-sizing: border-box; width: 100%; min-height: 3rem; margin-top: .5rem; font: inherit; }
        textarea { min-height: 9rem; font-family: ui-monospace, monospace; }
        button { font-weight: 700; cursor: pointer; }
        pre { overflow-wrap: anywhere; white-space: pre-wrap; }
        .status { font-size: .9rem; font-weight: 700; }
    </style>
</head>
<body>
<main>
    <header>
        <h1>Mobil Depo</h1>
        <div id="network" class="status" aria-live="polite"></div>
    </header>

    <section>
        <h2>Barkod / ürün sorgu</h2>
        <form id="lookup-form">
            <label for="scan">Barkod veya ürün kodu</label>
            <input id="scan" name="scan" inputmode="text" autocomplete="off" autofocus required>
            <button type="submit">Sorgula</button>
        </form>
    </section>

    <section>
        <h2>Depo işlemi</h2>
        <form id="operation-form">
            <label for="operation_type">İşlem</label>
            <select id="operation_type" name="operation_type" required>
                <option value="goods_receipt.verify">Mal kabul doğrula</option>
                <option value="goods_receipt.finalize">Mal kabul kesinleştir</option>
                <option value="picking.consume">Picking / rezervasyon tüket</option>
                <option value="dispatch.verify">İrsaliye ürün doğrula</option>
                <option value="dispatch.finalize">İrsaliye kesinleştir</option>
                <option value="transfer.issue">Transfer çıkış</option>
                <option value="transfer.receive">Transfer kabul</option>
                <option value="stock_count.start">Sayım başlat</option>
                <option value="stock_count.scan">Sayım barkodu tara</option>
                <option value="stock_count.post">Sayımı işle</option>
                <option value="subcontract.send">Fason malzeme gönder</option>
                <option value="subcontract.receive">Fason mamul kabul</option>
            </select>
            <label for="payload">Payload (JSON)</label>
            <textarea id="payload" name="payload" required>{}</textarea>
            <button type="submit">İşlemi gönder</button>
        </form>
    </section>

    <section>
        <h2>Sonuç</h2>
        <pre id="result" aria-live="polite">Hazır.</pre>
    </section>
</main>
<script>
(() => {
    'use strict';
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const result = document.getElementById('result');
    const network = document.getElementById('network');
    const clientKey = 'mars-mobile-warehouse-client-id';
    let clientId = localStorage.getItem(clientKey);
    if (!clientId) {
        clientId = crypto.randomUUID();
        localStorage.setItem(clientKey, clientId);
    }

    const updateNetwork = () => {
        network.textContent = navigator.onLine ? 'Çevrimiçi — yazma işlemleri açık' : 'Çevrimdışı — yazma işlemleri devre dışı';
    };
    addEventListener('online', updateNetwork);
    addEventListener('offline', updateNetwork);
    updateNetwork();

    const post = async (url, body, operation = false) => {
        if (!navigator.onLine) {
            throw new Error('Çevrimdışı yazma desteklenmiyor. Bağlantıyı yeniden kurun.');
        }
        const headers = {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf};
        if (operation) {
            headers['X-Mobile-Client-ID'] = clientId;
            headers['Idempotency-Key'] = crypto.randomUUID();
        }
        const response = await fetch(url, {method: 'POST', headers, body: JSON.stringify(body)});
        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.message || JSON.stringify(data));
        }
        return data;
    };

    document.getElementById('lookup-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        try {
            result.textContent = JSON.stringify(await post('{{ route('mobile.warehouse.lookup') }}', {scan: document.getElementById('scan').value}), null, 2);
        } catch (error) {
            result.textContent = error.message;
        }
    });

    document.getElementById('operation-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        try {
            const payload = JSON.parse(document.getElementById('payload').value);
            result.textContent = JSON.stringify(await post('{{ route('mobile.warehouse.operations.execute') }}', {
                operation_type: document.getElementById('operation_type').value,
                payload
            }, true), null, 2);
        } catch (error) {
            result.textContent = error.message;
        }
    });

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/mobile-warehouse-sw.js');
    }
})();
</script>
</body>
</html>
