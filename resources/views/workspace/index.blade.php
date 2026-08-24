@extends('layouts.app')

@section('title', 'Ana Sayfa')

@section('app-content')
    <section class="workspace-hero">
        <div>
            <p class="eyebrow">MarsOtomasyon</p>
            <h1>{{ $company->name }}</h1>
            <p>Core yönetim altyapısı hazır. İş modülleri tamamlandıkça ana menüde otomatik açılacak.</p>
        </div>
        <a class="button-primary" href="{{ route('settings.index') }}" data-workspace-link>Ayarlar</a>
    </section>

    @if ($activeBranches->isEmpty())
        <div class="notice-info">Bu firmada aktif şube yok. Operasyonel modüllerden önce Ayarlar → Şubeler alanından aktif bir şube oluşturun.</div>
    @elseif ($activeBranches->count() > 1 && $selectedBranch === null)
        <div class="notice-info">Birden fazla aktif şube var. Operasyonel işlemler için üst bardan aktif şubeyi seçin.</div>
    @endif

    <section class="dashboard-grid">
        <article class="dashboard-card">
            <span>Aktif Firma</span>
            <strong>{{ $company->name }}</strong>
            <small>{{ $company->code }} · {{ $company->base_currency_code }} · {{ $company->timezone }}</small>
        </article>
        <article class="dashboard-card">
            <span>Aktif Şube</span>
            <strong>{{ $selectedBranch?->name ?? 'Seçilmedi' }}</strong>
            <small>{{ $selectedBranch?->code ?? $activeBranches->count().' aktif şube' }}</small>
        </article>
        <article class="dashboard-card">
            <span>Çekirdek Yönetimi</span>
            <strong>Ayarlar</strong>
            <small>Firma, şube, kullanıcı, rol, numaralandırma, vergi, kur, dönem, dosya ve audit.</small>
        </article>
    </section>
@endsection
