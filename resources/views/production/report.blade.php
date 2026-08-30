@extends('layouts.app')

@section('title', 'Üretim Raporu')

@section('app-content')
<section class="workspace-hero">
    <div><p class="eyebrow">M14 / Rapor</p><h1>Üretim Maliyet ve İş Emri Raporu</h1><p>Son 500 üretim emrinde malzeme, fire/eksik ve mamul taşıma değerlerinin ledger ile uyumlu özeti.</p></div>
    <div class="page-actions"><a href="{{ route('production.index') }}">Üretime Dön</a></div>
</section>
<section class="detail-card">
    <div class="statement-table-card"><table class="data-table"><thead><tr><th>Emir</th><th>Mamul</th><th>Depo</th><th>Durum</th><th>Plan</th><th>Malzeme</th><th>Fire/Eksik</th><th>Mamul Miktarı</th><th>Birim Maliyet</th><th>Mamul Değeri</th><th>Tamamlandı</th></tr></thead><tbody>
    @forelse($rows as $row)
        <tr><td><a href="{{ route('production.show', $row->id) }}">{{ $row->order_no }}</a></td><td>{{ $row->product_code }} — {{ $row->product_name }}</td><td>{{ $row->warehouse_code }} — {{ $row->warehouse_name }}</td><td>{{ $row->status }}</td><td>{{ number_format((float) $row->planned_quantity, 6, ',', '.') }}</td><td>{{ number_format((float) $row->material_cost, 2, ',', '.') }}</td><td>{{ number_format((float) $row->loss_cost, 2, ',', '.') }}</td><td>{{ number_format((float) $row->output_quantity, 6, ',', '.') }}</td><td>{{ number_format((float) $row->output_unit_cost, 6, ',', '.') }}</td><td><strong>{{ number_format((float) $row->output_value, 2, ',', '.') }}</strong></td><td>{{ $row->completed_at ?? '—' }}</td></tr>
    @empty<tr><td colspan="11">Üretim emri bulunamadı.</td></tr>@endforelse
    </tbody></table></div>
</section>
@endsection
