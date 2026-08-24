@extends('layouts.settings')

@section('title', 'Firma / Sistem')
@section('heading', 'Firma / Sistem')

@section('content')
    <div class="page-actions">
        <p>Aktif şirketin temel çalışma ayarları.</p>
        @can('core.settings.manage')
            <a class="button-primary" href="{{ route('settings.company.edit') }}">Düzenle</a>
        @endcan
    </div>

    <section class="detail-card">
        <dl class="detail-grid">
            <div>
                <dt>Firma Kodu</dt>
                <dd>{{ $company->code }}</dd>
            </div>
            <div>
                <dt>Firma Adı</dt>
                <dd>{{ $company->name }}</dd>
            </div>
            <div>
                <dt>Ana Para Birimi</dt>
                <dd>{{ $company->base_currency_code }}</dd>
            </div>
            <div>
                <dt>Saat Dilimi</dt>
                <dd>{{ $company->timezone }}</dd>
            </div>
        </dl>
    </section>

    <div class="notice-info">
        Firma kodu ve firma adı bu ekranda salt okunurdur. Bu alan yalnız sistem çalışma para birimi ve saat dilimini yönetir.
    </div>
@endsection
