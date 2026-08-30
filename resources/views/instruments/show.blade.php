@extends('layouts.app')
@section('title', 'Çek / Senet '.$instrument->document_no)
@section('app-content')
<section class="workspace-hero"><div><p class="eyebrow">Çek / Senet #{{ $instrument->id }}</p><h1>{{ $instrument->document_no }}</h1><p>{{ $instrument->account?->legal_name }} · {{ $instrument->amount }} {{ $instrument->currency_code }} · vade {{ $instrument->due_date?->format('d.m.Y') }}</p></div><div class="page-actions"><span class="status-badge">{{ $instrument->status }}</span><a class="button-secondary" href="{{ route('instruments.index') }}">Portföye Dön</a></div></section>

<section class="detail-card"><h2>Belge / Custody</h2><dl class="detail-grid"><div><dt>Yön</dt><dd>{{ $instrument->direction }}</dd></div><div><dt>Tür</dt><dd>{{ $instrument->kind }}</dd></div><div><dt>Teslim</dt><dd>{{ $instrument->delivery_date?->format('d.m.Y') }}</dd></div><div><dt>Vade</dt><dd>{{ $instrument->due_date?->format('d.m.Y') }}</dd></div><div><dt>Holder</dt><dd>{{ $instrument->current_holder_type }} @if($instrument->currentHolderAccount) / {{ $instrument->currentHolderAccount->legal_name }} @endif @if($instrument->currentTreasuryAccount) / {{ $instrument->currentTreasuryAccount->name }} @endif</dd></div><div><dt>Cari posting</dt><dd>#{{ $instrument->delivery_account_transaction_id }}</dd></div></dl></section>

@can('instruments.manage')
<section class="detail-card"><h2>Lifecycle İşlemleri</h2><div class="form-grid">
@if($instrument->direction === 'received' && $instrument->status === 'portfolio')
<form method="POST" action="{{ route('instruments.send-to-bank', $instrument->id) }}">@csrf<label>Banka</label><select name="treasury_account_id" required>@foreach($banks as $bank)<option value="{{ $bank->id }}">{{ $bank->name }} / {{ $bank->currency_code }}</option>@endforeach</select><input type="date" name="event_date" value="{{ now()->toDateString() }}" required><button type="submit">Bankaya Gönder</button></form>
<form method="POST" action="{{ route('instruments.endorse', $instrument->id) }}">@csrf<label>Tedarikçi</label><select name="supplier_account_id" required>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->legal_name }} / {{ $supplier->book_currency_code }}</option>@endforeach</select><input type="date" name="event_date" value="{{ now()->toDateString() }}" required><button type="submit">Ciro Et</button></form>
<form method="POST" action="{{ route('instruments.return', $instrument->id) }}">@csrf<input type="date" name="event_date" value="{{ now()->toDateString() }}" required><button type="submit">Müşteriye İade</button></form>
@endif
@if($instrument->direction === 'received' && $instrument->status === 'bank_collection')
<form method="POST" action="{{ route('instruments.settle', $instrument->id) }}">@csrf<input type="hidden" name="treasury_account_id" value="{{ $instrument->current_treasury_account_id }}"><input type="date" name="event_date" value="{{ now()->toDateString() }}" required><button type="submit" class="button-primary">Tahsil Edildi</button></form>
<form method="POST" action="{{ route('instruments.recall-from-bank', $instrument->id) }}">@csrf<input type="date" name="event_date" value="{{ now()->toDateString() }}" required><button type="submit">Portföye Geri Al</button></form>
@endif
@if($instrument->direction === 'issued' && $instrument->status === 'issued')
<form method="POST" action="{{ route('instruments.settle', $instrument->id) }}">@csrf<select name="treasury_account_id" required>@foreach($banks as $bank)<option value="{{ $bank->id }}">{{ $bank->name }} / {{ $bank->currency_code }}</option>@endforeach</select><input type="date" name="event_date" value="{{ now()->toDateString() }}" required><button type="submit" class="button-primary">Ödendi</button></form>
@endif
@if(in_array($instrument->status, ['portfolio','bank_collection','endorsed','issued'], true))
<form method="POST" action="{{ route('instruments.dishonor', $instrument->id) }}">@csrf<input type="date" name="event_date" value="{{ now()->toDateString() }}" required><button type="submit">{{ $instrument->direction === 'received' ? 'Karşılıksız' : 'Ödenmedi' }}</button></form>
<form method="POST" action="{{ route('instruments.cancel', $instrument->id) }}">@csrf<input type="date" name="event_date" value="{{ now()->toDateString() }}" required><button type="submit">Ters Kayıt / İptal</button></form>
@endif
</div></section>

<section class="detail-card"><h2>Ön / Arka Görsel</h2><form method="POST" enctype="multipart/form-data" action="{{ route('instruments.files.upload', $instrument->id) }}" class="form-grid">@csrf<select name="side" required><option value="front">Ön</option><option value="back">Arka</option></select><input type="file" name="file" required><button type="submit">Yükle / Değiştir</button></form>
<div class="statement-table-card"><table class="data-table"><thead><tr><th>Yön</th><th>Dosya</th><th>Tarih</th><th>Durum</th><th></th></tr></thead><tbody>@forelse($attachments as $attachment)<tr><td>{{ $attachment->label }}</td><td>@if($attachment->detached_at === null)<a href="{{ route('instruments.files.download', [$instrument->id, $attachment->id]) }}">{{ $attachment->fileAsset?->original_name }}</a>@else{{ $attachment->fileAsset?->original_name }}@endif</td><td>{{ $attachment->attached_at }}</td><td>{{ $attachment->detached_at ? 'arşiv' : 'aktif' }}</td><td>@if($attachment->detached_at === null)<form method="POST" action="{{ route('instruments.files.detach', [$instrument->id, $attachment->id]) }}">@csrf @method('DELETE')<button type="submit">Arşivle</button></form>@endif</td></tr>@empty<tr><td colspan="5">Görsel yok.</td></tr>@endforelse</tbody></table></div></section>
@endcan

<section class="detail-card"><h2>Lifecycle Geçmişi</h2><div class="statement-table-card"><table class="data-table"><thead><tr><th>Tarih</th><th>Olay</th><th>Geçiş</th><th>Cari Tx</th><th>Treasury Tx</th></tr></thead><tbody>@foreach($instrument->events as $event)<tr><td>{{ $event->event_date?->format('d.m.Y') }}</td><td>{{ $event->event_type }}</td><td>{{ $event->from_status }} → {{ $event->to_status }}</td><td>{{ $event->account_transaction_id ? '#'.$event->account_transaction_id : '—' }}</td><td>{{ $event->treasury_movement_id ? '#'.$event->treasury_movement_id : '—' }}</td></tr>@endforeach</tbody></table></div></section>
@endsection
