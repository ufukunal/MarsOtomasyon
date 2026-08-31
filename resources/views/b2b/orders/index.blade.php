@extends('b2b.layout')
@section('title', 'Siparişler — Mars B2B')
@section('heading', 'Sipariş Geçmişi')
@section('content')
<section class="detail-card"><table><thead><tr><th>No</th><th>Tarih</th><th>Durum</th><th>Tutar</th></tr></thead><tbody>@forelse($orders as $order)<tr><td><a href="{{ route('b2b.orders.show', $order->number) }}">{{ $order->number }}</a></td><td>{{ $order->order_date?->format('d.m.Y') }}</td><td>{{ $order->statusEnum()->label() }}</td><td>{{ $order->gross_total }} {{ $order->currency_code }}</td></tr>@empty<tr><td colspan="4">Sipariş yok.</td></tr>@endforelse</tbody></table>{{ $orders->links() }}</section>
@endsection
