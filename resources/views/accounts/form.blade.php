@extends('layouts.app')

@section('title', $account ? 'Cari Düzenle' : 'Yeni Cari')

@section('app-content')
    <section class="workspace-hero">
        <div>
            <p class="eyebrow">Cari Yönetimi</p>
            <h1>{{ $account ? 'Cari Düzenle' : 'Yeni Cari' }}</h1>
            <p>{{ $account ? 'Firma / Ticari bilgileri ve cari durumunu güncelleyin.' : 'Aktif firmaya yeni cari kaydı ekleyin.' }}</p>
        </div>
        <a href="{{ $account ? route('customers.show', $account->getKey()) : route('customers.index') }}" data-workspace-link>Vazgeç</a>
    </section>

    @if ($errors->any())
        <div class="notice-error" role="alert">
            <strong>Kayıt tamamlanamadı.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="post" action="{{ $account ? route('customers.update', $account->getKey()) : route('customers.store') }}" class="detail-card">
        @csrf
        @if ($account)
            @method('PUT')
        @endif

        <h2>Firma / Ticari</h2>
        <div class="form-grid">
            <label>
                Cari Kodu
                <input name="code" maxlength="64" required value="{{ old('code', $account?->code) }}">
            </label>

            <label>
                Cari Türü
                <select name="type" required>
                    @foreach ($accountTypes as $type)
                        <option value="{{ $type->value }}" @selected(old('type', $account?->typeEnum()->value ?? 'customer') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </label>

            @if ($account)
                <label>
                    Durum
                    <select name="status" required>
                        @foreach ($accountStatuses as $status)
                            <option value="{{ $status->value }}" @selected(old('status', $account->statusEnum()->value) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            <label>
                Resmi Ünvan
                <input name="legal_name" maxlength="200" required value="{{ old('legal_name', $account?->legal_name) }}">
            </label>

            <label>
                Ticari Ünvan
                <input name="trade_name" maxlength="200" value="{{ old('trade_name', $account?->trade_name) }}">
            </label>

            <label>
                Cari Para Birimi
                <select name="book_currency_code" required>
                    @foreach ($currencies as $currency)
                        <option value="{{ $currency->code }}" @selected(old('book_currency_code', $account?->book_currency_code ?? 'TRY') === $currency->code)>{{ $currency->code }} · {{ $currency->name }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                Vade (Gün)
                <input type="number" name="due_days" min="0" max="3650" required value="{{ old('due_days', $account?->due_days ?? 0) }}">
            </label>

            <label>
                Cari İskontosu (%)
                <input type="number" name="discount_rate" min="0" max="100" step="0.000001" required value="{{ old('discount_rate', $account?->discount_rate ?? '0') }}">
            </label>

            <label>
                Risk Limiti
                <input type="number" name="risk_limit" min="0" step="0.000001" required value="{{ old('risk_limit', $account?->risk_limit ?? '0') }}">
            </label>
        </div>

        <h2>Vergi Bilgileri</h2>
        <div class="form-grid">
            <label>
                Kimlik Türü
                <select name="tax_identity_type" required>
                    @foreach ($taxIdentityTypes as $identityType)
                        <option value="{{ $identityType->value }}" @selected(old('tax_identity_type', $account?->taxIdentityTypeEnum()->value ?? 'none') === $identityType->value)>{{ $identityType->label() }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                Vergi / Kimlik No
                <input name="tax_number" maxlength="32" value="{{ old('tax_number', $account?->tax_number) }}">
            </label>

            <label>
                Vergi Dairesi
                <input name="tax_office" maxlength="120" value="{{ old('tax_office', $account?->tax_office) }}">
            </label>
        </div>

        <div class="page-actions">
            <span></span>
            <button type="submit">Kaydet</button>
        </div>
    </form>
@endsection
