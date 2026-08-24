@extends('layouts.app')

@section('app-content')
    <section class="settings-shell">
        <header class="settings-header">
            <div>
                <div class="eyebrow">Ayarlar</div>
                <h1 class="settings-title">@yield('heading', 'Ayarlar')</h1>
            </div>
            <nav class="settings-nav" aria-label="Ayarlar">
                <a href="{{ route('settings.index') }}" data-workspace-link>Genel</a>
                @can('core.settings.view')
                    <a href="{{ route('settings.company.show') }}" data-workspace-link>Firma / Sistem</a>
                    <a href="{{ route('settings.numbering.index') }}" data-workspace-link>Numaralandırma</a>
                    <a href="{{ route('settings.taxes.index') }}" data-workspace-link>Vergi / KDV</a>
                    <a href="{{ route('settings.exchange-rates.index') }}" data-workspace-link>Para Birimi / Kur</a>
                    <a href="{{ route('settings.posting-periods.index') }}" data-workspace-link>Dönemler</a>
                    <a href="{{ route('settings.audit.index') }}" data-workspace-link>İşlem Geçmişi</a>
                @endcan
                @can('core.branch.view')
                    <a href="{{ route('settings.branches.index') }}" data-workspace-link>Şubeler</a>
                @endcan
                @can('core.file.view')
                    <a href="{{ route('settings.files.index') }}" data-workspace-link>Firma Dosyaları</a>
                @endcan
                @can('core.user.view')
                    <a href="{{ route('settings.users.index') }}" data-workspace-link>Kullanıcılar</a>
                @endcan
                @can('core.role.view')
                    <a href="{{ route('settings.roles.index') }}" data-workspace-link>Roller ve Yetkiler</a>
                @endcan
            </nav>
        </header>

        @if ($errors->any())
            <div class="notice-error" role="alert">
                <strong>İşlem tamamlanamadı.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <main class="settings-content">
            @yield('content')
        </main>
    </section>
@endsection
