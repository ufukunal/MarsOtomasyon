@extends('layouts.app')

@section('title', 'Cari Ekstresi')

@section('app-content')
    <section class="workspace-hero">
        <div>
            <p class="eyebrow">Cari Ekstresi</p>
            <h1>{{ $account->legal_name }}</h1>
            <p>{{ $account->code }} · {{ $account->book_currency_code }} · Salt okunur cari hareket dökümü</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('customers.index') }}" data-workspace-link>Cariler</a>
            <a class="button-primary" href="{{ route('customers.show', $account->getKey()) }}" data-workspace-link>Cari Detaya Dön</a>
        </div>
    </section>

    <section class="balance-grid">
        <article class="balance-card {{ $statement->openingBalance->state()->cssClass() }}">
            <span>Açılış Bakiyesi</span>
            <strong>{{ $statement->openingBalance->formatted() }}</strong>
            <small>{{ $statement->openingBalance->state()->label() }}</small>
        </article>
        <article class="balance-card {{ $statement->closingBalance->state()->cssClass() }}">
            <span>{{ $to ? 'Dönem Sonu Bakiyesi' : 'Güncel Bakiye' }}</span>
            <strong>{{ $statement->closingBalance->formatted() }}</strong>
            <small>{{ $statement->closingBalance->state()->label() }}</small>
        </article>
    </section>

    <form method="get" action="{{ route('customers.statement.index', $account->getKey()) }}" class="detail-card">
        <div class="form-grid">
            <label>
                Başlangıç Tarihi
                <input type="date" name="from" value="{{ $from }}" data-dirty-ignore>
            </label>
            <label>
                Bitiş Tarihi
                <input type="date" name="to" value="{{ $to }}" data-dirty-ignore>
            </label>
        </div>
        <div class="page-actions">
            <a href="{{ route('customers.statement.index', $account->getKey()) }}" data-workspace-link>Filtreyi Temizle</a>
            <button type="submit">Uygula</button>
        </div>
    </form>

    <section class="detail-card statement-table-card">
        <table class="data-table">
            <thead>
            <tr>
                <th>Tarih</th>
                <th>İşlem</th>
                <th>Açıklama</th>
                <th class="amount-cell">Borç</th>
                <th class="amount-cell">Alacak</th>
                <th class="amount-cell">Bakiye</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($statement->rows as $row)
                <tr>
                    <td>{{ \Carbon\CarbonImmutable::parse($row['posting_date'])->format('d.m.Y') }}</td>
                    <td>{{ $row['description'] }}</td>
                    <td>{{ $row['memo'] ?: '—' }}</td>
                    <td class="amount-cell">{{ $row['movement']->debitDisplay() }}</td>
                    <td class="amount-cell">{{ $row['movement']->creditDisplay() }}</td>
                    <td class="amount-cell">
                        <span class="balance-inline {{ $row['running_balance']->state()->cssClass() }}">
                            {{ $row['running_balance']->formatted() }} · {{ $row['running_balance']->state()->label() }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">Seçilen tarih aralığında cari hareketi yok.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </section>

    {{ $statement->rows->links() }}
@endsection
