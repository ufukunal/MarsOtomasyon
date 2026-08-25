@extends('layouts.app')

@section('title', 'Cariler')

@section('app-content')
    <section class="workspace-hero">
        <div>
            <p class="eyebrow">Cari Yönetimi</p>
            <h1>Cariler</h1>
            <p>Aktif firmaya ait müşteri, tedarikçi ve karma cari kayıtları.</p>
        </div>
        @can('accounts.manage')
            <a class="button-primary" href="{{ route('customers.create') }}" data-workspace-link>Yeni Cari</a>
        @endcan
    </section>

    <form method="get" action="{{ route('customers.index') }}" class="detail-card">
        <div class="form-grid">
            <label>
                Ara
                <input type="search" name="q" value="{{ $search }}" placeholder="Kod veya ünvan" data-dirty-ignore>
            </label>
            <label>
                Durum
                <select name="status" data-dirty-ignore>
                    <option value="all" @selected($statusFilter === 'all')>Tümü</option>
                    <option value="active" @selected($statusFilter === 'active')>Aktif</option>
                    <option value="inactive" @selected($statusFilter === 'inactive')>Pasif</option>
                </select>
            </label>
        </div>
        <div class="page-actions">
            <span></span>
            <button type="submit">Filtrele</button>
        </div>
    </form>

    <section class="detail-card">
        <table class="data-table">
            <thead>
            <tr>
                <th>Kod</th>
                <th>Resmi Ünvan</th>
                <th>Tür</th>
                <th>Para Birimi</th>
                <th>Durum</th>
                <th>İşlem</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($accounts as $account)
                <tr>
                    <td>{{ $account->code }}</td>
                    <td>{{ $account->legal_name }}</td>
                    <td>{{ $account->typeEnum()->label() }}</td>
                    <td>{{ $account->book_currency_code }}</td>
                    <td>{{ $account->statusEnum()->label() }}</td>
                    <td><a href="{{ route('customers.show', $account->getKey()) }}" data-workspace-link>Detay</a></td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">Filtreye uygun cari kaydı bulunamadı.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </section>

    {{ $accounts->links() }}
@endsection
