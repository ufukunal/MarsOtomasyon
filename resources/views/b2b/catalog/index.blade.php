@extends('b2b.layout')
@section('title', 'Katalog — Mars B2B')
@section('heading', 'Ürün Kataloğu')
@section('content')
<section class="detail-card">
<form method="GET" action="{{ route('b2b.catalog.index') }}" class="page-actions"><input name="q" value="{{ $search }}" placeholder="Kod, ad, marka, kategori veya barkod"><button type="submit">Ara</button></form>
<table><thead><tr><th>Kod</th><th>Ürün</th><th>Marka</th><th>Kategori</th>@if($showPrice)<th>Net B2B Fiyat</th>@endif @if($showStock)<th>Kullanılabilir</th>@endif<th></th></tr></thead><tbody>
@foreach($products as $product)
<tr><td>{{ $product->code }}</td><td>{{ $product->name }}</td><td>{{ $product->brand ?? '—' }}</td><td>{{ $product->category?->name ?? '—' }}</td>@if($showPrice)<td>{{ $rows[$product->getKey()]['price'] }} {{ $account->book_currency_code }}</td>@endif @if($showStock)<td>{{ $rows[$product->getKey()]['stock'] }}</td>@endif<td><form method="POST" action="{{ route('b2b.cart.store') }}">@csrf<input type="hidden" name="product_code" value="{{ $product->code }}"><input name="quantity" value="1" size="6"><button type="submit">Sepete Ekle</button></form></td></tr>
@endforeach
</tbody></table>
{{ $products->links() }}
</section>
@endsection
