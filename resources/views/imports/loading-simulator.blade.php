@extends('layouts.app')
@section('title','Yükleme Simülatörü')
@section('app-content')
<section class="workspace-hero"><div><p class="eyebrow">M16 / Yükleme</p><h1>{{ $file->number }} — Yükleme Simülatörü</h1><p>Konteyner tanımlı kapasite ile planlanan brüt ağırlık/hacmi karşılaştırır.</p></div></section>
<section class="detail-card"><div class="statement-table-card"><table class="data-table"><thead><tr><th>Konteyner</th><th>Plan kg</th><th>Azami kg</th><th>Plan m³</th><th>Azami m³</th><th>Durum</th></tr></thead><tbody>@foreach($rows as $row)@php($overWeight=$row->max_weight_kg!==null && (float)$row->gross_weight_kg>(float)$row->max_weight_kg) @php($overVolume=$row->max_volume_m3!==null && (float)$row->volume_m3>(float)$row->max_volume_m3)<tr><td>{{ $row->container_no }}</td><td>{{ $row->gross_weight_kg }}</td><td>{{ $row->max_weight_kg }}</td><td>{{ $row->volume_m3 }}</td><td>{{ $row->max_volume_m3 }}</td><td>{{ $overWeight || $overVolume ? 'Kapasite Aşıldı' : 'Uygun' }}</td></tr>@endforeach</tbody></table></div></section>
@endsection
