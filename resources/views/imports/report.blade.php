@extends('layouts.app')
@section('title','İthalat Raporu')
@section('app-content')
<section class="workspace-hero"><div><p class="eyebrow">M16 / Rapor</p><h1>İthalat Mutabakat Raporu</h1><p>Dosya kalem miktarı ile kesinleşmiş mal kabul bağlantılarını karşılaştırır.</p></div></section>
<section class="detail-card"><div class="statement-table-card"><table class="data-table"><thead><tr><th>Dosya</th><th>Durum</th><th>Para</th><th>ETA</th><th>Kalem</th><th>Plan Miktar</th><th>Mal Kabul</th></tr></thead><tbody>@foreach($rows as $row)<tr><td><a href="{{ route('import.show',$row->id) }}">{{ $row->number }}</a></td><td>{{ $row->status }}</td><td>{{ $row->currency_code }}</td><td>{{ $row->expected_arrival_date }}</td><td>{{ $row->item_count }}</td><td>{{ $row->item_quantity }}</td><td>{{ $row->received_quantity }}</td></tr>@endforeach</tbody></table></div></section>
@endsection
