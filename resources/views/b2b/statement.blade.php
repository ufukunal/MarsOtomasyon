@extends('b2b.layout')
@section('title', 'Ekstre — Mars B2B')
@section('heading', 'Cari Ekstresi')
@section('content')
<section class="detail-card"><form method="GET" class="page-actions"><label>Başlangıç<input type="date" name="from" value="{{ $from }}"></label><label>Bitiş<input type="date" name="to" value="{{ $to }}"></label><button type="submit">Filtrele</button></form><p>Açılış: {{ $statement->openingBalance->formatted() }} · Kapanış: {{ $statement->closingBalance->formatted() }}</p><table><thead><tr><th>Tarih</th><th>İşlem</th><th>Not</th><th>Hareket</th><th>Bakiye</th></tr></thead><tbody>@forelse($statement->rows as $row)<tr><td>{{ $row['posting_date'] }}</td><td>{{ $row['description'] }}</td><td>{{ $row['memo'] }}</td><td>{{ $row['movement']->formatted() }}</td><td>{{ $row['running_balance']->formatted() }}</td></tr>@empty<tr><td colspan="5">Hareket yok.</td></tr>@endforelse</tbody></table>{{ $statement->rows->links() }}</section>
@endsection
