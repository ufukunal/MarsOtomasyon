@extends('layouts.app')

@section('title', 'Fason')

@section('app-content')
<section class="workspace-hero"><div><p class="eyebrow">M15 / Fason</p><h1>Fason</h1><p>Gönderilen malzemenin miktar ve taşıma değerini subcontract custody içinde koruyan, mamul kabulü ve fire/eksik ile reconcile edilen fason akışı.</p></div><div class="page-actions"><a class="button-primary" href="{{ route('subcontract.report') }}">Fason Raporu</a></div></section>
@if ($errors->any())<section class="notice-error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></section>@endif
@can('subcontract.manage')
<section class="detail-card"><h2>Yeni Fason Sipariş</h2><form method="POST" action="{{ route('subcontract.store') }}" class="form-grid">@csrf
<div><label>Fasoncu / Tedarikçi</label><select name="supplier_account_id" required><option value="">Seçin</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->code }} — {{ $supplier->legal_name }}</option>@endforeach</select></div>
<div><label>Mamul</label><select name="output_product_id" required><option value="">Seçin</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->code }} — {{ $product->name }}</option>@endforeach</select></div>
<div><label>Sipariş No</label><input name="order_no" required maxlength="64"></div><div><label>Planlanan Mamul</label><input name="planned_output_quantity" type="number" min="0.000001" step="0.000001" required></div>
<div><label>Depo</label><select name="warehouse_id" required><option value="">Seçin</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->code }} — {{ $warehouse->name }}</option>@endforeach</select></div>
<div><label>Lokasyon</label><select name="location_id" required><option value="">Seçin</option>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->code }} — {{ $location->name }}</option>@endforeach</select></div>
@for($i=0;$i<4;$i++)<div><label>Malzeme {{ $i+1 }}</label><select name="materials[{{ $i }}][product_id]"><option value="">Seçin</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->code }} — {{ $product->name }}</option>@endforeach</select></div><div><label>Miktar {{ $i+1 }}</label><input name="materials[{{ $i }}][quantity]" type="number" min="0.000001" step="0.000001"></div>@endfor
<div style="grid-column:1/-1"><label>Not</label><textarea name="note" maxlength="500"></textarea></div><div><button class="button-primary">Sipariş Oluştur</button></div></form></section>
@endcan
<section class="detail-card"><h2>Fason Siparişler</h2><div class="statement-table-card"><table class="data-table"><thead><tr><th>Sipariş</th><th>Fasoncu</th><th>Mamul</th><th>Plan</th><th>Durum</th><th>Gönderilen Değer</th><th>Alınan Değer</th><th></th></tr></thead><tbody>@forelse($orders as $order)<tr><td>{{ $order->order_no }}</td><td>{{ $order->supplier?->code }} — {{ $order->supplier?->legal_name }}</td><td>{{ $order->outputProduct?->code }} — {{ $order->outputProduct?->name }}</td><td>{{ $order->planned_output_quantity }}</td><td>{{ $order->status }}</td><td>{{ number_format((float)$order->sent_value,2,',','.') }}</td><td>{{ number_format((float)$order->received_output_value,2,',','.') }}</td><td><a href="{{ route('subcontract.show',$order->id) }}">Aç</a></td></tr>@empty<tr><td colspan="8">Fason sipariş yok.</td></tr>@endforelse</tbody></table></div>{{ $orders->links() }}</section>
@endsection
