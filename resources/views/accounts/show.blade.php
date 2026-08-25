@extends('layouts.app')

@section('title', 'Cari Detay')

@section('app-content')
    <section class="workspace-hero">
        <div>
            <p class="eyebrow">Cari Detay</p>
            <h1>{{ $account->legal_name }}</h1>
            <p>{{ $account->code }} · {{ $account->typeEnum()->label() }} · {{ $account->statusEnum()->label() }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('customers.index') }}" data-workspace-link>Listeye Dön</a>
            @can('accounts.manage')
                <a class="button-primary" href="{{ route('customers.edit', $account->getKey()) }}" data-workspace-link>Düzenle</a>
            @endcan
        </div>
    </section>

    <section class="detail-card">
        <h2>Firma / Ticari</h2>
        <dl class="detail-list">
            <div><dt>Cari Kodu</dt><dd>{{ $account->code }}</dd></div>
            <div><dt>Resmi Ünvan</dt><dd>{{ $account->legal_name }}</dd></div>
            <div><dt>Ticari Ünvan</dt><dd>{{ $account->trade_name ?: '—' }}</dd></div>
            <div><dt>Cari Türü</dt><dd>{{ $account->typeEnum()->label() }}</dd></div>
            <div><dt>Durum</dt><dd>{{ $account->statusEnum()->label() }}</dd></div>
            <div><dt>Para Birimi</dt><dd>{{ $account->book_currency_code }}</dd></div>
            <div><dt>Vade</dt><dd>{{ $account->due_days }} gün</dd></div>
            <div><dt>Cari İskontosu</dt><dd>%{{ $account->discount_rate }}</dd></div>
            <div><dt>Risk Limiti</dt><dd>{{ $account->risk_limit }} {{ $account->book_currency_code }}</dd></div>
        </dl>
    </section>

    <section class="detail-card">
        <h2>Vergi Bilgileri</h2>
        <dl class="detail-list">
            <div><dt>Kimlik Türü</dt><dd>{{ $account->taxIdentityTypeEnum()->label() }}</dd></div>
            <div><dt>Vergi / Kimlik No</dt><dd>{{ $account->tax_number ?: '—' }}</dd></div>
            <div><dt>Vergi Dairesi</dt><dd>{{ $account->tax_office ?: '—' }}</dd></div>
        </dl>
    </section>
@endsection
