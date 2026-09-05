@extends('layouts.app')

@section('title', 'Ürün Aileleri')

@section('app-content')
<div class="page-header">
    <div>
        <h1>Ürün Aileleri ve Varyantlar</h1>
        <p>SKU ürün kimliği korunur; aile yalnız gruplama, ortak içerik, medya ve marketplace parent katmanıdır.</p>
    </div>
    @can('products.manage')
        <a class="button-primary" href="{{ route('inventory.product-families.create') }}">Yeni aile</a>
    @endcan
</div>

<div class="panel">
    <div class="form-grid">
        <label>Tür
            <select data-m25-type>
                <option value="all">Tümü</option>
                <option value="family">Aile</option>
                <option value="variant">Varyant SKU</option>
                <option value="simple">Basit SKU</option>
            </select>
        </label>
        <label>Ara
            <input type="search" maxlength="120" data-m25-search placeholder="Kod veya ad">
        </label>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Tür</th><th>Kod</th><th>Ad</th><th>Aile</th></tr></thead>
            <tbody data-m25-rows><tr><td colspan="4">Yükleniyor…</td></tr></tbody>
        </table>
    </div>
    <div class="muted" data-m25-summary></div>
</div>

<script>
(() => {
    const rows = document.querySelector('[data-m25-rows]');
    const summary = document.querySelector('[data-m25-summary]');
    const type = document.querySelector('[data-m25-type]');
    const search = document.querySelector('[data-m25-search]');
    let draw = 0;
    const load = async () => {
        draw += 1;
        const params = new URLSearchParams({draw: String(draw), start: '0', length: '100', type: type.value, q: search.value});
        const response = await fetch(`{{ route('inventory.product-families.data') }}?${params.toString()}`, {headers: {'Accept': 'application/json'}});
        if (!response.ok) { rows.innerHTML = '<tr><td colspan="4">Liste yüklenemedi.</td></tr>'; return; }
        const payload = await response.json();
        rows.innerHTML = payload.data.length ? payload.data.map((row) => `<tr><td>${row.type}</td><td><a href="${row.url}">${escapeHtml(row.code)}</a></td><td>${escapeHtml(row.name)}</td><td>${row.family_id ?? '—'}</td></tr>`).join('') : '<tr><td colspan="4">Kayıt yok.</td></tr>';
        summary.textContent = `${payload.recordsFiltered} / ${payload.recordsTotal} kayıt`;
    };
    const escapeHtml = (value) => String(value).replace(/[&<>'"]/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
    type.addEventListener('change', load);
    let timer;
    search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(load, 200); });
    load();
})();
</script>
@endsection
