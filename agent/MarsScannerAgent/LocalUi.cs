namespace MarsScannerAgent;

internal static class LocalUi
{
    public const string Html = """
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="Content-Security-Policy" content="default-src 'none'; connect-src 'self'; img-src blob:; style-src 'unsafe-inline'; script-src 'unsafe-inline'; base-uri 'none'; form-action 'none'">
<title>Mars Scanner Agent</title>
<style>
:root { color-scheme: light; font: 14px/1.45 system-ui,Segoe UI,Arial,sans-serif; color:#1c344c; background:#eef3f7 }
* { box-sizing:border-box }
body { margin:0; padding:32px 15px }
main { max-width:760px; margin:0 auto; background:white; padding:26px; border:1px solid #d7e0e9; border-radius:12px; box-shadow:0 5px 35px #21344a12 }
h1 { font-size:22px; margin:0 0 4px }
h2 { font-size:17px; margin:20px 0 8px }
p { color:#5c7082; margin:5px 0 18px }
label { display:block; font-weight:600; margin:13px 0 5px }
input,select,button,a.action { padding:10px 12px; font:inherit; border-radius:6px }
input,select { width:100%; border:1px solid #b9cbd9; background:#fff }
button,a.action { display:inline-block; border:1px solid #296390; background:#296390; color:white; text-decoration:none; cursor:pointer }
button.secondary { background:white; color:#296390 }
button:disabled { cursor:wait; opacity:.5 }
.toolbar { display:flex; gap:9px; flex-wrap:wrap; margin:16px 0 }
#message { padding:12px; background:#edf5fb; border-radius:6px; white-space:pre-wrap; overflow-wrap:anywhere }
#preview { display:none; max-width:100%; max-height:65vh; margin:12px auto; border:1px solid #d7e0e9; object-fit:contain }
#save { display:none }
small { display:block; color:#667989 }
@media(max-width:480px) { main { padding:17px } .toolbar button,.toolbar a { width:100%; text-align:center } }
</style>
</head>
<body>
<main>
<h1>Mars Scanner Agent</h1>
<p>Windows WIA tarayıcı bağlantısı · Yalnızca bu bilgisayarda çalışır.</p>
<label for="token">Erişim anahtarı</label>
<input id="token" type="password" autocomplete="off" placeholder="Kurulumda gösterilen erişim anahtarını girin">
<small>Anahtar tarayıcıda saklanmaz ve MarsProject sunucusuna gönderilmez.</small>
<div class="toolbar">
<button id="refresh" type="button">Tarayıcıları Bul</button>
</div>
<label for="devices">Bağlı tarayıcı</label>
<select id="devices"><option value="">Önce tarayıcıları bulun</option></select>
<label for="dpi">Tarama çözünürlüğü</label>
<select id="dpi">
<option value="150">150 DPI</option>
<option value="300" selected>300 DPI</option>
<option value="600">600 DPI</option>
</select>
<div class="toolbar">
<button id="scan" type="button">Belge Tara</button>
<a class="action" id="save" href="#" download="mars-tarama.jpg">JPG İndir</a>
</div>
<div id="message" role="status" aria-live="polite">Agent hazır. Epson EcoTank cihazını açın ve belgeyi tarayıcı camına yerleştirin.</div>
<img id="preview" alt="Taranan belge">
<p>Tarama tamamlandığında JPG dosyasını indirebilir ve MarsProject'teki belge eki alanına yükleyebilirsiniz. Bu ilk sürüm belgeleri sunucuya otomatik göndermez.</p>
</main>
<script>
(() => {
'use strict';
const byId = (id) => document.getElementById(id);
const message = byId('message');
const devices = byId('devices');
const refresh = byId('refresh');
const scan = byId('scan');
const save = byId('save');
const image = byId('preview');
let lastImageUrl = null;
const info = (text) => { message.textContent = text; };
const errorMessage = async (response) => {
    const body = await response.json().catch(() => ({}));
    return body.detail || body.error || ('HTTP ' + response.status);
};
const headers = () => ({
    'Authorization': 'Bearer ' + byId('token').value.trim(),
});
refresh.addEventListener('click', async () => {
    if (!byId('token').value.trim()) { info('Erişim anahtarını girin.'); return; }
    refresh.disabled = true;
    info('Windows WIA tarayıcıları aranıyor…');
    try {
        const response = await fetch('/api/scanners', { headers: headers(), cache: 'no-store' });
        if (!response.ok) throw new Error(await errorMessage(response));
        const data = await response.json();
        devices.replaceChildren();
        for (const device of data.devices) {
            const option = document.createElement('option');
            option.value = device.id;
            option.textContent = device.name;
            devices.appendChild(option);
        }
        if (!data.devices.length) {
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'WIA tarayıcı bulunamadı';
            devices.appendChild(option);
        }
        info(data.devices.length + ' tarayıcı bulundu.');
    } catch (error) {
        info('Cihaz arama hatası: ' + error.message);
    } finally { refresh.disabled = false; }
});
scan.addEventListener('click', async () => {
    if (!devices.value || !byId('token').value.trim()) {
        info('Önce erişim anahtarını girin ve bir tarayıcı seçin.');
        return;
    }
    scan.disabled = true;
    info('Tarama çalışıyor. Cihazın taramayı bitirmesini bekleyin…');
    try {
        const response = await fetch('/api/scan', {
            method: 'POST',
            headers: { ...headers(), 'Content-Type': 'application/json' },
            body: JSON.stringify({ deviceId: devices.value, dpi: Number(byId('dpi').value) }),
        });
        if (!response.ok) throw new Error(await errorMessage(response));
        const blob = await response.blob();
        if (blob.type !== 'image/jpeg') throw new Error('Tarayıcı JPG yerine farklı içerik döndürdü.');
        if (lastImageUrl) URL.revokeObjectURL(lastImageUrl);
        lastImageUrl = URL.createObjectURL(blob);
        image.src = lastImageUrl;
        image.style.display = 'block';
        save.href = lastImageUrl;
        save.download = 'mars-tarama-' + new Date().toISOString().replace(/[:.]/g,'-') + '.jpg';
        save.style.display = 'inline-block';
        info('Tarama tamamlandı. JPG görüntüsü hazır.');
    } catch (error) {
        info('Tarama hatası: ' + error.message);
    } finally { scan.disabled = false; }
});
window.addEventListener('pagehide', () => {
    if (lastImageUrl) URL.revokeObjectURL(lastImageUrl);
});
})();
</script>
</body>
</html>
""";
}
