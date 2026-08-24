@extends('layouts.settings')

@section('title', 'Şube Detayı')
@section('heading', 'Şube Detayı')

@section('content')
    <div class="page-actions">
        <p>Şube kaydının salt okunur görünümü.</p>
        <div>
            <a href="{{ route('settings.branches.index') }}">Listeye Dön</a>
            @can('core.branch.manage')
                · <a href="{{ route('settings.branches.edit', $branch->getKey()) }}">Düzenle</a>
            @endcan
        </div>
    </div>

    <section class="detail-card">
        <dl class="detail-grid">
            <div>
                <dt>Kod</dt>
                <dd>{{ $branch->code }}</dd>
            </div>
            <div>
                <dt>Ad</dt>
                <dd>{{ $branch->name }}</dd>
            </div>
            <div>
                <dt>Durum</dt>
                <dd>{{ $branch->is_active ? 'Aktif' : 'Pasif' }}</dd>
            </div>
            <div>
                <dt>Oturum Seçimi</dt>
                <dd>{{ $activeBranchId === $branch->getKey() ? 'Aktif şube' : 'Seçili değil' }}</dd>
            </div>
        </dl>
    </section>

    @if ($branch->is_active && $activeBranchId !== $branch->getKey())
        <form method="post" action="{{ route('settings.branches.select', $branch->getKey()) }}">
            @csrf
            <button type="submit">Aktif Şube Yap</button>
        </form>
    @endif
@endsection
