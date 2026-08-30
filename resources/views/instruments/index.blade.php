@extends('layouts.app')
@section('title', 'Çek / Senet')
@section('app-content')
<section class="workspace-hero"><div><p class="eyebrow">Finans / Portföy</p><h1>Çek / Senet</h1><p>Teslim anında cari etkisi, custody geçmişi, ciro, banka kapanışı ve ters kayıt zinciri tek ekranda.</p></div></section>

<section class="detail-card">
    <h2>Portföy</h2>
    <form method="GET" class="form-grid">
        <div><label>Yön</label><select name="direction"><option value="">Tümü</option><option value="received" @selected($directionFilter === 'received')>Alınan</option><option value="issued" @selected($directionFilter === 'issued')>Verilen</option></select></div>
        <div><label>Tür</label><select name="kind"><option value="">Tümü</option><option value="cheque" @selected($kindFilter === 'cheque')>Çek</option><option value="promissory_note" @selected($kindFilter === 'promissory_note')>Senet</option></select></div>
        <div><label>Durum</label><input name="status" value="{{ $statusFilter }}" placeholder="portfolio / issued / ..."></div>
        <div class="page-actions"><button type="submit">Filtrele</button></div>
    </form>
    <div class="statement-table-card"><table class="data-table"><thead><tr><th>#</th><th>Belge</th><th>Yön/Tür</th><th>Cari</th><th>Teslim</th><th>Vade</th><th>Tutar</th><th>Durum</th></tr></thead><tbody>
    @forelse($instruments as $instrument)
        <tr><td><a href="{{ route('instruments.show', $instrument->id) }}">{{ $instrument->id }}</a></td><td>{{ $instrument->document_no }}</td><td>{{ $instrument->direction === 'received' ? 'Alınan' : 'Verilen' }} / {{ $instrument->kind === 'cheque' ? 'Çek' : 'Senet' }}</td><td>{{ $instrument->account?->legal_name }}</td><td>{{ $instrument->delivery_date?->format('d.m.Y') }}</td><td>{{ $instrument->due_date?->format('d.m.Y') }}</td><td>{{ $instrument->amount }} {{ $instrument->currency_code }}</td><td>{{ $instrument->status }}</td></tr>
    @empty <tr><td colspan="8">Çek/senet kaydı bulunamadı.</td></tr> @endforelse
    </tbody></table></div>
    {{ $instruments->links() }}
</section>

@can('instruments.manage')
<section class="detail-card"><h2>Yeni Çek / Senet</h2>
<form method="POST" action="{{ route('instruments.store') }}" class="form-grid">@csrf
    <div><label for="direction">Yön</label><select id="direction" name="direction" required><option value="received">Alınan</option><option value="issued">Verilen</option></select></div>
    <div><label for="kind">Tür</label><select id="kind" name="kind" required><option value="cheque">Çek</option><option value="promissory_note">Senet</option></select></div>
    <div><label for="account_id">Cari</label><select id="account_id" name="account_id" required>@foreach($commercialAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} — {{ $account->legal_name }} / {{ $account->type }} / {{ $account->book_currency_code }}</option>@endforeach</select></div>
    <div><label for="document_no">Belge No</label><input id="document_no" name="document_no" maxlength="120" required></div>
    <div><label for="amount">Tutar</label><input id="amount" name="amount" inputmode="decimal" required></div>
    <div><label for="currency_code">Para Birimi</label><input id="currency_code" name="currency_code" maxlength="3" value="TRY" required></div>
    <div><label for="issue_date">Düzenleme</label><input id="issue_date" type="date" name="issue_date"></div>
    <div><label for="delivery_date">Teslim</label><input id="delivery_date" type="date" name="delivery_date" value="{{ now()->toDateString() }}" required></div>
    <div><label for="due_date">Vade</label><input id="due_date" type="date" name="due_date" required></div>
    <div><label for="bank_name">Banka</label><input id="bank_name" name="bank_name" maxlength="160"></div>
    <div><label for="branch_name">Şube</label><input id="branch_name" name="branch_name" maxlength="120"></div>
    <div><label for="drawer_or_maker">Keşideci / Düzenleyen</label><input id="drawer_or_maker" name="drawer_or_maker" maxlength="200"></div>
    <div><label for="note">Not</label><input id="note" name="note" maxlength="5000"></div>
    <div class="page-actions"><button type="submit" class="button-primary">Kaydet ve Cari Etkisini İşle</button></div>
</form></section>
@endcan
@endsection
