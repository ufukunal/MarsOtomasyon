@extends('layouts.settings')

@section('title', 'Kullanıcı Detayı')
@section('heading', 'Kullanıcı Detayı')

@section('content')
<div class="page-actions">
    <a href="{{ route('settings.users.index') }}">← Kullanıcılar</a>
    @can('core.user.manage')
        <a class="button-primary" href="{{ route('settings.users.edit', $membership->getKey()) }}">Düzenle</a>
    @endcan
</div>

<div class="detail-card">
    <dl class="detail-grid">
        <div><dt>Ad</dt><dd>{{ $membership->user?->name ?? '—' }}</dd></div>
        <div><dt>E-posta</dt><dd>{{ $membership->user?->email ?? '—' }}</dd></div>
        <div><dt>Üyelik</dt><dd>{{ $membership->is_active ? 'Aktif' : 'Pasif' }}</dd></div>
        <div><dt>Roller</dt><dd>{{ $membership->roles->pluck('name')->join(', ') ?: 'Rol yok' }}</dd></div>
        <div><dt>Son giriş</dt><dd>{{ $membership->user?->last_login_at?->format('d.m.Y H:i') ?? 'Henüz giriş yapmadı' }}</dd></div>
        <div><dt>Kimlik düzenleme</dt><dd>{{ $identityEditable ? 'Bu şirketten düzenlenebilir' : 'Başka şirket üyelikleri nedeniyle kilitli' }}</dd></div>
    </dl>
</div>
@endsection
