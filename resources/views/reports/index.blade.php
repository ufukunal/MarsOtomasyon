@extends('layouts.app')

@section('title', 'Raporlar')

@section('app-content')
<section class="workspace-hero">
    <div>
        <p class="eyebrow">Finans / Analiz</p>
        <h1>Raporlar</h1>
        <p>Cari, treasury ve stok ledger kayıtlarından tarihsel olarak yeniden üretilebilir yönetim raporları.</p>
    </div>
    <div class="page-actions">
        <span class="status-badge">As-of {{ $filters['as_of'] }}</span>
    </div>
</section>

<section class="detail-card">
    <h2>Rapor Filtreleri</h2>
    <form method="GET" action="{{ route('reports.index') }}" class="form-grid">
        <div>
            <label for="as_of">Rapor Tarihi</label>
            <input id="as_of" type="date" name="as_of" value="{{ $filters['as_of'] }}" required>
        </div>
        <div>
            <label for="currency">Para Birimi</label>
            <input id="currency" name="currency" value="{{ $filters['currency'] }}" maxlength="3" placeholder="TRY">
        </div>
        <div>
            <label for="account_type">Cari Tipi</label>
            <select id="account_type" name="account_type">
                <option value="">Tümü</option>
                <option value="customer" @selected($filters['account_type'] === 'customer')>Müşteri</option>
                <option value="supplier" @selected($filters['account_type'] === 'supplier')>Tedarikçi</option>
                <option value="mixed" @selected($filters['account_type'] === 'mixed')>Müşteri / Tedarikçi</option>
                <option value="clearing" @selected($filters['account_type'] === 'clearing')>Takas / Clearing</option>
            </select>
        </div>
        <div>
            <label for="warehouse_id">Depo</label>
            <select id="warehouse_id" name="warehouse_id">
                <option value="">Tüm depolar</option>
                @foreach($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}" @selected($filters['warehouse_id'] === (int) $warehouse->id)>
                        {{ $warehouse->code }} — {{ $warehouse->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="page-actions">
            <button type="submit" class="button-primary">Raporu Yenile</button>
            <a href="{{ route('reports.index') }}">Filtreleri Temizle</a>
        </div>
    </form>
</section>

<section class="detail-card">
    <div class="workspace-hero">
        <div>
            <p class="eyebrow">Finans</p>
            <h2>Finansal Pozisyon</h2>
        </div>
        <div class="page-actions">
            <a class="button-primary" href="{{ route('reports.export', array_merge(request()->query(), ['section' => 'finance'])) }}">CSV İndir</a>
        </div>
    </div>
    <div class="statement-table-card">
        <table class="data-table">
            <thead>
            <tr><th>Para Birimi</th><th>Treasury</th><th>Cari Alacak</th><th>Cari Borç</th><th>Net Pozisyon</th></tr>
            </thead>
            <tbody>
            @forelse($finance as $row)
                <tr>
                    <td>{{ $row['currency'] }}</td>
                    <td>{{ number_format((float) $row['treasury'], 2, ',', '.') }}</td>
                    <td>{{ number_format((float) $row['receivable'], 2, ',', '.') }}</td>
                    <td>{{ number_format((float) $row['payable'], 2, ',', '.') }}</td>
                    <td><strong>{{ number_format((float) $row['net'], 2, ',', '.') }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="5">Seçilen filtrelerde finansal hareket bulunamadı.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="detail-card">
    <div class="workspace-hero">
        <div>
            <p class="eyebrow">Cari</p>
            <h2>Yaşlandırma</h2>
        </div>
        <div class="page-actions">
            <a class="button-primary" href="{{ route('reports.export', array_merge(request()->query(), ['section' => 'aging'])) }}">CSV İndir</a>
        </div>
    </div>
    <div class="statement-table-card">
        <table class="data-table">
            <thead>
            <tr>
                <th>Cari</th><th>Tip</th><th>PB</th><th>Vadesi Gelmemiş</th><th>1–30</th><th>31–60</th><th>61–90</th><th>90+</th><th>Toplam</th>
            </tr>
            </thead>
            <tbody>
            @forelse($aging as $row)
                <tr>
                    <td>{{ $row['code'] }} — {{ $row['name'] }}</td>
                    <td>{{ $row['type'] }}</td>
                    <td>{{ $row['currency'] }}</td>
                    <td>{{ number_format((float) $row['current'], 2, ',', '.') }}</td>
                    <td>{{ number_format((float) $row['days_1_30'], 2, ',', '.') }}</td>
                    <td>{{ number_format((float) $row['days_31_60'], 2, ',', '.') }}</td>
                    <td>{{ number_format((float) $row['days_61_90'], 2, ',', '.') }}</td>
                    <td>{{ number_format((float) $row['days_90_plus'], 2, ',', '.') }}</td>
                    <td><strong>{{ number_format((float) $row['total'], 2, ',', '.') }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="9">Seçilen filtrelerde açık cari bakiye bulunamadı.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="detail-card">
    <div class="workspace-hero">
        <div>
            <p class="eyebrow">Stok</p>
            <h2>Stok Değerleme</h2>
        </div>
        <div class="page-actions">
            <a class="button-primary" href="{{ route('reports.export', array_merge(request()->query(), ['section' => 'stock'])) }}">CSV İndir</a>
        </div>
    </div>
    <div class="statement-table-card">
        <table class="data-table">
            <thead>
            <tr><th>Ürün</th><th>Depo</th><th>Miktar</th><th>Ort. Maliyet</th><th>Stok Değeri</th></tr>
            </thead>
            <tbody>
            @forelse($stock as $row)
                <tr>
                    <td>{{ $row['product_code'] }} — {{ $row['product_name'] }}</td>
                    <td>{{ $row['warehouse_code'] }} — {{ $row['warehouse_name'] }}</td>
                    <td>{{ number_format((float) $row['quantity'], 6, ',', '.') }}</td>
                    <td>{{ number_format((float) $row['unit_cost'], 6, ',', '.') }}</td>
                    <td><strong>{{ number_format((float) $row['value'], 2, ',', '.') }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="5">Seçilen tarihte stok bakiyesi bulunamadı.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="detail-card">
    <div class="workspace-hero">
        <div>
            <p class="eyebrow">Ledger</p>
            <h2>Son Stok Hareketleri</h2>
        </div>
        <div class="page-actions">
            <a class="button-primary" href="{{ route('reports.export', array_merge(request()->query(), ['section' => 'movements'])) }}">CSV İndir</a>
        </div>
    </div>
    <div class="statement-table-card">
        <table class="data-table">
            <thead>
            <tr><th>Tarih</th><th>Ürün</th><th>Depo</th><th>Hareket</th><th>Miktar</th><th>Birim Maliyet</th><th>Değer</th><th>Kaynak</th></tr>
            </thead>
            <tbody>
            @forelse($movements as $movement)
                <tr>
                    <td>{{ $movement->occurred_at }}</td>
                    <td>{{ $movement->product_code }} — {{ $movement->product_name }}</td>
                    <td>{{ $movement->warehouse_code }} — {{ $movement->warehouse_name }}</td>
                    <td>{{ $movement->movement_type }}</td>
                    <td>{{ number_format((float) $movement->quantity_delta, 6, ',', '.') }}</td>
                    <td>{{ number_format((float) $movement->unit_cost, 6, ',', '.') }}</td>
                    <td>{{ number_format((float) $movement->value_delta, 2, ',', '.') }}</td>
                    <td><code>{{ $movement->source_type }}:{{ $movement->source_id }}</code></td>
                </tr>
            @empty
                <tr><td colspan="8">Seçilen filtrelerde stok hareketi bulunamadı.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
