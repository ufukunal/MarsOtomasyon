@extends('layouts.settings')

@section('title', 'Ayarlar')
@section('heading', 'Ayarlar')

@section('content')
    <div class="page-actions">
        <p>Yalnız erişim yetkiniz bulunan yönetim alanları gösterilir.</p>
    </div>

    <div class="settings-grid">
        @can('core.settings.view')
            <a class="settings-tile" href="{{ route('settings.company.show') }}" data-workspace-link><strong>Firma / Sistem</strong><span>Firma para birimi ve saat dilimi.</span></a>
            <a class="settings-tile" href="{{ route('settings.numbering.index') }}" data-workspace-link><strong>Numaralandırma</strong><span>Belge seri ve sıra yönetimi.</span></a>
            <a class="settings-tile" href="{{ route('settings.taxes.index') }}" data-workspace-link><strong>Vergi / KDV</strong><span>Vergi ve KDV sıfır nedeni tanımları.</span></a>
            <a class="settings-tile" href="{{ route('settings.exchange-rates.index') }}" data-workspace-link><strong>Para Birimi / Kur</strong><span>Kur kayıtları ve düzeltmeleri.</span></a>
            <a class="settings-tile" href="{{ route('settings.posting-periods.index') }}" data-workspace-link><strong>Dönemler</strong><span>Muhasebe dönemi ve kapanış yönetimi.</span></a>
            <a class="settings-tile" href="{{ route('settings.audit.index') }}" data-workspace-link><strong>İşlem Geçmişi</strong><span>Değiştirilemez yönetim audit kayıtları.</span></a>
        @endcan
        @can('integrations.view')
            <a class="settings-tile" href="{{ route('settings.integrations.index') }}" data-workspace-link><strong>Entegrasyonlar</strong><span>SMS, e-posta, WhatsApp, e-belge ve Scanner Agent ayarları.</span></a>
        @endcan
        @can('core.branch.view')
            <a class="settings-tile" href="{{ route('settings.branches.index') }}" data-workspace-link><strong>Şubeler</strong><span>Şube tanımları ve aktif şube yönetimi.</span></a>
        @endcan
        @can('core.file.view')
            <a class="settings-tile" href="{{ route('settings.files.index') }}" data-workspace-link><strong>Firma Dosyaları</strong><span>Private dosya ve attachment yönetimi.</span></a>
        @endcan
        @can('core.user.view')
            <a class="settings-tile" href="{{ route('settings.users.index') }}" data-workspace-link><strong>Kullanıcılar</strong><span>Şirket üyelikleri ve kullanıcı yönetimi.</span></a>
        @endcan
        @can('core.role.view')
            <a class="settings-tile" href="{{ route('settings.roles.index') }}" data-workspace-link><strong>Roller ve Yetkiler</strong><span>Company-scoped RBAC yönetimi.</span></a>
        @endcan
    </div>
@endsection
