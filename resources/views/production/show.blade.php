@extends('layouts.app')

@section('title', 'Üretim Emri '.$order->order_no)

@section('app-content')
<section class="workspace-hero">
    <div><p class="eyebrow">Üretim Emri</p><h1>{{ $order->order_no }}</h1><p>{{ $order->product?->code }} — {{ $order->product?->name }}</p></div>
    <div class="page-actions"><span class="status-badge">{{ $order->status }}</span><a href="{{ route('production.index') }}">Üretime Dön</a></div>
</section>

@if ($errors->any())<section class="notice-error"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></section>@endif

<section class="detail-card">
    <h2>Özet</h2>
    <div class="form-grid">
        <div><strong>Reçete</strong><p>{{ $order->recipe?->code }} — {{ $order->recipe?->name }}</p></div>
        <div><strong>Depo / Lokasyon</strong><p>{{ $order->warehouse?->code }} / {{ $order->location?->code }}</p></div>
        <div><strong>Planlanan</strong><p>{{ $order->planned_quantity }}</p></div>
        <div><strong>Malzeme Maliyeti</strong><p>{{ number_format((float) $order->material_cost, 2, ',', '.') }}</p></div>
        <div><strong>Fire/Eksik Maliyeti</strong><p>{{ number_format((float) $order->loss_cost, 2, ',', '.') }}</p></div>
        <div><strong>Mamul Değeri</strong><p>{{ number_format((float) $order->output_value, 2, ',', '.') }}</p></div>
    </div>
</section>

@can('production.manage')
<section class="detail-card">
    <h2>Lifecycle</h2>
    <div class="page-actions">
        @if($order->status === 'draft')<form method="POST" action="{{ route('production.issue-materials', $order->id) }}">@csrf<button class="button-primary">Malzemeleri Çık</button></form>@endif
        @if($order->status === 'in_progress' && $order->received_at === null)<form method="POST" action="{{ route('production.receive-output', $order->id) }}">@csrf<button class="button-primary">Mamul Girişi</button></form>@endif
        @if($order->status === 'received')<form method="POST" action="{{ route('production.complete', $order->id) }}">@csrf<button class="button-primary">Emri Tamamla</button></form>@endif
    </div>
</section>
@endif

<section class="detail-card">
    <h2>Malzeme Snapshotı</h2>
    <div class="statement-table-card"><table class="data-table"><thead><tr><th>Malzeme</th><th>Gerekli</th><th>Çıkılan</th><th>Taşıma Değeri</th></tr></thead><tbody>
    @foreach($order->materials as $material)<tr><td>{{ $material->product?->code }} — {{ $material->product?->name }}</td><td>{{ $material->required_quantity }}</td><td>{{ $material->issued_quantity }}</td><td>{{ number_format((float) $material->issued_value, 2, ',', '.') }}</td></tr>@endforeach
    </tbody></table></div>
</section>

@if($order->status === 'in_progress' && $order->received_at === null)
@can('production.manage')
<section class="detail-card">
    <h2>Fire / Eksik Kaydı</h2>
    <form method="POST" action="{{ route('production.losses.store', $order->id) }}" class="form-grid">@csrf
        <div><label>İşlem Anahtarı</label><input name="operation_key" value="loss-{{ $order->id }}-{{ now()->format('YmdHis') }}" required maxlength="64"></div>
        <div><label>Malzeme</label><select name="product_id" required>@foreach($order->materials as $material)<option value="{{ $material->product_id }}">{{ $material->product?->code }} — {{ $material->product?->name }}</option>@endforeach</select></div>
        <div><label>Miktar</label><input type="number" name="quantity" min="0.000001" step="0.000001" required></div>
        <div><label>Tür</label><select name="loss_type"><option value="fire">Fire</option><option value="missing">Eksik</option></select></div>
        <div><label>Not</label><input name="note" maxlength="240"></div>
        <div><button class="button-primary">Kaydet</button></div>
    </form>
</section>
@endcan
@endif

<section class="detail-card">
    <h2>Fire / Eksik Geçmişi</h2>
    <div class="statement-table-card"><table class="data-table"><thead><tr><th>Tür</th><th>Malzeme</th><th>Miktar</th><th>Değer</th><th>Tarih</th></tr></thead><tbody>
    @forelse($order->losses as $loss)<tr><td>{{ $loss->loss_type }}</td><td>{{ $loss->product?->code }} — {{ $loss->product?->name }}</td><td>{{ $loss->quantity }}</td><td>{{ number_format((float) $loss->carrying_value, 2, ',', '.') }}</td><td>{{ $loss->occurred_at }}</td></tr>@empty<tr><td colspan="5">Kayıt yok.</td></tr>@endforelse
    </tbody></table></div>
</section>

<section class="detail-card">
    <h2>Teknik Dosyalar</h2>
    @can('production.manage')
    <form method="POST" enctype="multipart/form-data" action="{{ route('production.files.store', $order->id) }}" class="form-grid">@csrf
        <div><label>Etiket</label><input name="label" maxlength="160" placeholder="Teknik resim, kalite planı, talimat..."></div>
        <div><label>Dosya</label><input type="file" name="file" required></div>
        <div><button class="button-primary">Yükle</button></div>
    </form>
    @endcan
    <div class="statement-table-card"><table class="data-table"><thead><tr><th>Etiket</th><th>Dosya</th><th>SHA-256</th><th>Durum</th><th></th></tr></thead><tbody>
    @forelse($attachments as $attachment)
        <tr><td>{{ $attachment->label ?? '—' }}</td><td>{{ $attachment->fileAsset?->original_name }}</td><td><code>{{ $attachment->fileAsset?->sha256 }}</code></td><td>{{ $attachment->detached_at ? 'Arşiv' : 'Aktif' }}</td><td>@if(!$attachment->detached_at)<a href="{{ route('production.files.download', [$order->id, $attachment->id]) }}">İndir</a>@can('production.manage') <form method="POST" action="{{ route('production.files.detach', [$order->id, $attachment->id]) }}" style="display:inline">@csrf<button class="button-link">Arşivle</button></form>@endcan @endif</td></tr>
    @empty<tr><td colspan="5">Teknik dosya yok.</td></tr>@endforelse
    </tbody></table></div>
</section>

<section class="detail-card">
    <h2>Lifecycle Olayları</h2>
    <div class="statement-table-card"><table class="data-table"><thead><tr><th>Tarih</th><th>Olay</th><th>Payload</th></tr></thead><tbody>
    @foreach($order->events->sortBy('id') as $event)<tr><td>{{ $event->occurred_at }}</td><td>{{ $event->event_type }}</td><td><code>{{ json_encode($event->payload, JSON_UNESCAPED_UNICODE) }}</code></td></tr>@endforeach
    </tbody></table></div>
</section>
@endsection
