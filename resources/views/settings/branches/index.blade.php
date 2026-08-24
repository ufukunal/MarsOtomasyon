@extends('layouts.settings')

@section('title', 'Şubeler')
@section('heading', 'Şubeler')

@section('content')
    <div class="page-actions">
        <p>Aktif şirketin operasyon şubeleri ve kullanıcı oturumundaki aktif şube seçimi.</p>
        @can('core.branch.manage')
            <a href="{{ route('settings.branches.create') }}">Yeni Şube</a>
        @endcan
    </div>

    <section class="detail-card">
        <table class="data-table">
            <thead>
            <tr>
                <th>Kod</th>
                <th>Ad</th>
                <th>Durum</th>
                <th>Oturum</th>
                <th>İşlem</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($branches as $branch)
                <tr>
                    <td>{{ $branch->code }}</td>
                    <td>{{ $branch->name }}</td>
                    <td>{{ $branch->is_active ? 'Aktif' : 'Pasif' }}</td>
                    <td>{{ $activeBranchId === $branch->getKey() ? 'Aktif şube' : '—' }}</td>
                    <td>
                        <a href="{{ route('settings.branches.show', $branch->getKey()) }}">Detay</a>
                        @if ($branch->is_active && $activeBranchId !== $branch->getKey())
                            <form method="post" action="{{ route('settings.branches.select', $branch->getKey()) }}" style="display:inline">
                                @csrf
                                <button type="submit">Aktif Şube Yap</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">Henüz şube tanımlanmamış.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
