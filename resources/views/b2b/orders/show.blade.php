@extends('b2b.layout')
@section('title', $order->number.' — Mars B2B')
@section('heading', 'Sipariş '.$order->number)
@section('content')
<section class="detail-card"><dl class="detail-list"><div><dt>Tarih</dt><dd>{{ $order->order_date?->format('d.m.Y') }}</dd></div><div><dt>Durum</dt><dd>{{ $order->statusEnum()->label() }}</dd></div><div><dt>Toplam</dt><dd>{{ $order->gross_total }} {{ $order->currency_code }}</dd></div></dl><table><thead><tr><th>Kod</th><th>Ürün</th><th>Miktar</th><th>Net</th><th>Vergi</th><th>Brüt</th></tr></thead><tbody>@foreach($order->lines as $line)<tr><td>{{ $line->product_code }}</td><td>{{ $line->product_name }}</td><td>{{ $line->quantity }}</td><td>{{ $line->net_total }}</td><td>{{ $line->tax_total }}</td><td>{{ $line->gross_total }}</td></tr>@endforeach</tbody></table></section>
@endsection
