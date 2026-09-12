@extends('layouts.app')

@section('title', 'Cariler')

@section('app-content')
    <section class="workspace-hero">
        <div>
            <p class="eyebrow">Kişiler / Firmalar</p>
            <h1>Kişi/Firma Listesi</h1>
            <p>Aktif firmaya ait müşteri, tedarikçi ve karma cari kayıtları.</p>
        </div>
        <div class="page-actions">
            @if (app(\App\Foundation\Features\FeatureRegistry::class)->enabled(\App\Foundation\Features\FeatureKey::LightCrm))
                @can('crm.view')
                    <a href="{{ route('crm.index') }}" data-workspace-link>CRM / Fırsatlar</a>
                @endcan
            @endif
        </div>
    </section>

    <div class="list-layout">
        <form method="get" action="{{ route('customers.index') }}" class="filters-panel">
            <div class="filters-panel-title">▼ Filtreler</div>
            <div class="filters-panel-body">
                <label>
                    Genel Arama
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
            <div class="filters-panel-actions">
                <a href="{{ route('customers.index') }}" class="button-secondary">Temizle</a>
                <button type="submit" class="button-primary">Filtrele</button>
            </div>
        </form>

        <section class="table-card">
            <div class="table-toolbar">
                <span class="subtle">{{ $accounts->total() }} kayıt</span>
                <div class="grow"></div>
                @can('accounts.manage')
                    <a class="button-primary" href="{{ route('customers.create') }}" data-workspace-link>＋ Yeni Cari</a>
                @endcan
            </div>
            <div class="statement-table-card">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Cari Kodu</th>
                        <th>Resmi Ünvan</th>
                        <th>Tür</th>
                        <th>Para Birimi</th>
                        <th class="amount-cell">Bakiye</th>
                        <th>Durum</th>
                        <th>İşlem</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($accounts as $account)
                        @php($accountBalance = $balances[$account->getKey()])
                        <tr>
                            <td><a href="{{ route('customers.show', $account->getKey()) }}" data-workspace-link>{{ $account->code }}</a></td>
                            <td><a href="{{ route('customers.show', $account->getKey()) }}" data-workspace-link>{{ $account->legal_name }}</a></td>
                            <td>{{ $account->typeEnum()->label() }}</td>
                            <td>{{ $account->book_currency_code }}</td>
                            <td class="amount-cell">
                                <span class="balance-inline {{ $accountBalance->state()->cssClass() }}">
                                    {{ $accountBalance->formatted() }} · {{ $accountBalance->state()->label() }}
                                </span>
                            </td>
                            <td>{{ $account->statusEnum()->label() }}</td>
                            <td><a href="{{ route('customers.show', $account->getKey()) }}" data-workspace-link>Detay ↗</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">Filtreye uygun cari kaydı bulunamadı.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $accounts->links() }}
        </section>
    </div>
@endsection
