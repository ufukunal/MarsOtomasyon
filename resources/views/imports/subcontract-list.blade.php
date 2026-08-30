@extends('layouts.app')
@section('title','Fason Toplama Listesi')
@section('app-content')
<section class="workspace-hero"><div><p class="eyebrow">M16 / Fason Toplama</p><h1>{{ $file->number }} — Fason Toplama</h1></div></section>
<section class="detail-card"><div class="statement-table-card"><table class="data-table"><thead><tr><th>Konteyner</th><th>Ürün</th><th>Konum</th><th>Miktar</th><th>Paket</th></tr></thead><tbody>@forelse($rows as $item)<tr><td>{{ $item->container?->container_no }}</td><td>{{ $item->product?->code }} — {{ $item->product?->name }}</td><td>{{ $item->material_location }}</td><td>{{ $item->quantity }}</td><td>{{ $item->package_reference }}</td></tr>@empty<tr><td colspan="5">Fason toplama işaretli kalem yok.</td></tr>@endforelse</tbody></table></div></section>
@endsection
