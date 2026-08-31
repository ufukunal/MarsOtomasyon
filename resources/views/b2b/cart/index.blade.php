@extends('b2b.layout')
@section('title', 'Sepet — Mars B2B')
@section('heading', 'Sepet')
@section('content')
<section class="detail-card">
@if($products->isEmpty())<p>Sepetiniz boş.</p>@else
<table><thead><tr><th>Kod</th><th>Ürün</th><th>Miktar</th>@if($showPrice)<th>Net B2B Fiyat</th>@endif<th></th></tr></thead><tbody>
@foreach($products as $product)<tr><td>{{ $product->code }}</td><td>{{ $product->name }}</td><td><form method="POST" action="{{ route('b2b.cart.update', $product->code) }}">@csrf @method('PUT')<input name="quantity" value="{{ $cart[$product->code] }}"><button type="submit">Güncelle</button></form></td>@if($showPrice)<td>{{ $prices[$product->code] }}</td>@endif<td><form method="POST" action="{{ route('b2b.cart.destroy', $product->code) }}">@csrf @method('DELETE')<button type="submit">Çıkar</button></form></td></tr>@endforeach
</tbody></table>
<form method="POST" action="{{ route('b2b.orders.submit') }}">@csrf<input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}"><button type="submit">Siparişi Oluştur</button></form>
@endif
</section>
@endsection
