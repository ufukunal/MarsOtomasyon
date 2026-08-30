@extends('layouts.app')
@section('title','Toplama / Üretim Listesi')
@section('app-content')
<section class="workspace-hero"><div><p class="eyebrow">M16 / Operasyon Listesi</p><h1>{{ $file->number }} — Toplama / Üretim</h1></div></section>
<section class="detail-card"><div class="statement-table-card"><table class="data-table"><thead><tr><th>Konteyner</th><th>Ürün</th><th>Paket</th><th>Komponent</th><th>Konum</th><th>Miktar</th><th>Paket Adedi</th><th>Brüt kg</th></tr></thead><tbody>@foreach($file->items as $item)<tr><td>{{ $item->container?->container_no }}</td><td>{{ $item->product?->code }} — {{ $item->product?->name }}</td><td>{{ $item->package_reference }}</td><td>{{ $item->component_reference }}</td><td>{{ $item->material_location }}</td><td>{{ $item->quantity }}</td><td>{{ $item->package_count }}</td><td>{{ $item->gross_weight_kg }}</td></tr>@endforeach</tbody></table></div></section>
@endsection
