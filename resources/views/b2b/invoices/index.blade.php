@extends('b2b.layout')
@section('title', 'Faturalar — Mars B2B')
@section('heading', 'Faturalar')
@section('content')
<section class="detail-card"><table><thead><tr><th>No</th><th>Tarih</th><th>Durum</th><th>Toplam</th><th></th></tr></thead><tbody>@forelse($invoices as $invoice)<tr><td>{{ $invoice->number }}</td><td>{{ $invoice->invoice_date?->format('d.m.Y') }}</td><td>{{ $invoice->statusEnum()->value }}</td><td>{{ $invoice->gross_total }} {{ $invoice->currency_code }}</td><td><a href="{{ route('b2b.invoices.download', $invoice->number) }}">PDF</a></td></tr>@empty<tr><td colspan="5">Fatura yok.</td></tr>@endforelse</tbody></table>{{ $invoices->links() }}</section>
@endsection
