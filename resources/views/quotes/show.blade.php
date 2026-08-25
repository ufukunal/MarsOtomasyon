@extends('layouts.app')

@section('title', 'Teklif '.$quote->number)

@section('app-content')
@php
    $commercialRevision = $quote->selectedRevision;
    $displayLines = $commercialRevision?->lines ?? $quote->lines;
@endphp

<section class="workspace-hero">
    <div>
        <p class="eyebrow">Satış / Teklif</p>
        <h1>{{ $quote->number }}</h1>
        <p>{{ $quote->account->legal_name }} · {{ $quote->statusEnum()->label() }}</p>
    </div>
    <div class="page-actions">
        <a href="{{ route('quotes.index') }}" data-workspace-link>Teklifler</a>
        @can('quotes.manage')
            @if ($quote->isDraft())
                <form method="post" action="{{ route('quotes.revisions.store', $quote->getKey()) }}">
                    @csrf
                    <button type="submit">Revizyon Snapshot</button>
                </form>
                <a class="button-primary" href="{{ route('quotes.edit', $quote->getKey()) }}" data-workspace-link>Düzenle</a>
                <form method="post" action="{{ route('quotes.cancel', $quote->getKey()) }}">
                    @csrf
                    <button type="submit">İptal Et</button>
                </form>
            @endif
        @endcan
    </div>
</section>

@if ($commercialRevision)
    <section class="detail-card">
        <div class="page-actions">
            <div>
                <p class="eyebrow">Ticari Otorite</p>
                <h2>R{{ $commercialRevision->revision_number }} · {{ $quote->statusEnum()->label() }}</h2>
                <p>Bu teklifin ticari içeriği immutable R{{ $commercialRevision->revision_number }} snapshotıdır. Aşağıdaki tutar ve satırlar bu revizyondan gösterilir.</p>
            </div>
            <a href="{{ route('quotes.revisions.show', [$quote->getKey(), $commercialRevision->getKey()]) }}" data-workspace-link>Seçili Snapshot</a>
        </div>
        <dl class="detail-grid">
            <div><dt>Karar Kullanıcısı</dt><dd>{{ $quote->decisionBy?->name ?? '—' }}</dd></div>
            <div><dt>Karar Zamanı</dt><dd>{{ $quote->decision_at?->format('d.m.Y H:i:s') ?? '—' }}</dd></div>
            <div><dt>Karar Notu</dt><dd>{{ $quote->decision_note ?: '—' }}</dd></div>
            <div><dt>Sipariş</dt><dd>{{ $quote->salesOrder?->number ?? '—' }}</dd></div>
        </dl>

        @if ($quote->isApproved())
            @can('quotes.approve')
                <form method="post" action="{{ route('quotes.convert', $quote->getKey()) }}" class="page-actions">
                    @csrf
                    <span>Onaylı R{{ $commercialRevision->revision_number }} exactly-once satış siparişine dönüştürülür.</span>
                    <button class="button-primary" type="submit">Siparişe Dönüştür</button>
                </form>
            @endcan
        @endif
    </section>
@endif

<section class="detail-card">
    <dl class="detail-grid">
        <div><dt>Teklif Tarihi</dt><dd>{{ ($commercialRevision?->quote_date ?? $quote->quote_date)->format('d.m.Y') }}</dd></div>
        <div><dt>Geçerlilik</dt><dd>{{ ($commercialRevision?->valid_until ?? $quote->valid_until)?->format('d.m.Y') ?? '—' }}</dd></div>
        <div><dt>Para Birimi</dt><dd>{{ $commercialRevision?->currency_code ?? $quote->currency_code }}</dd></div>
        <div><dt>Belge İskonto</dt><dd>%{{ $commercialRevision?->document_discount_rate ?? $quote->document_discount_rate }}</dd></div>
    </dl>
</section>

<section class="detail-card statement-table-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Ürün</th>
                <th>Açıklama</th>
                <th class="amount-cell">Miktar</th>
                <th class="amount-cell">Birim Fiyat</th>
                <th>KDV</th>
                <th class="amount-cell">Net</th>
                <th class="amount-cell">Vergi</th>
                <th class="amount-cell">Toplam</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($displayLines as $line)
                <tr>
                    <td>{{ $line->position }}</td>
                    <td>{{ $line->product_code }}</td>
                    <td>{{ $line->description }}</td>
                    <td class="amount-cell">{{ $line->quantity }}</td>
                    <td class="amount-cell">{{ $line->unit_price }}</td>
                    <td>
                        %{{ $line->tax_rate }}
                        @if ($line->tax_zero_reason_code)
                            · {{ $line->tax_zero_reason_code }}
                        @endif
                    </td>
                    <td class="amount-cell">{{ $line->net_total }}</td>
                    <td class="amount-cell">{{ $line->tax_total }}</td>
                    <td class="amount-cell">{{ $line->gross_total }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th colspan="6">Toplam</th>
                <th class="amount-cell">{{ $commercialRevision?->net_total ?? $quote->net_total }}</th>
                <th class="amount-cell">{{ $commercialRevision?->tax_total ?? $quote->tax_total }}</th>
                <th class="amount-cell">{{ $commercialRevision?->gross_total ?? $quote->gross_total }} {{ $commercialRevision?->currency_code ?? $quote->currency_code }}</th>
            </tr>
        </tfoot>
    </table>
</section>

<section class="detail-card statement-table-card">
    <div class="page-actions">
        <h2>Revizyon Geçmişi</h2>
        <span></span>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Revizyon</th>
                <th>Snapshot Zamanı</th>
                <th class="amount-cell">Net</th>
                <th class="amount-cell">Vergi</th>
                <th class="amount-cell">Toplam</th>
                <th>İşlem</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($quote->revisions as $revision)
                <tr>
                    <td>
                        R{{ $revision->revision_number }}
                        @if ($quote->selected_revision_id === $revision->getKey())
                            · Seçili
                        @endif
                    </td>
                    <td>{{ $revision->created_at->format('d.m.Y H:i:s') }}</td>
                    <td class="amount-cell">{{ $revision->net_total }}</td>
                    <td class="amount-cell">{{ $revision->tax_total }}</td>
                    <td class="amount-cell">{{ $revision->gross_total }} {{ $revision->currency_code }}</td>
                    <td>
                        <a href="{{ route('quotes.revisions.show', [$quote->getKey(), $revision->getKey()]) }}" data-workspace-link>Snapshot</a>
                        @if ($quote->isDraft())
                            @can('quotes.approve')
                                <form method="post" action="{{ route('quotes.revisions.approve', [$quote->getKey(), $revision->getKey()]) }}" style="display:inline">
                                    @csrf
                                    <button type="submit">Onayla</button>
                                </form>
                                <form method="post" action="{{ route('quotes.revisions.reject', [$quote->getKey(), $revision->getKey()]) }}" style="display:inline">
                                    @csrf
                                    <button type="submit">Reddet</button>
                                </form>
                            @endcan
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">Henüz immutable revizyon snapshotı yok.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</section>

@if ($commercialRevision?->note ?? $quote->note)
    <section class="detail-card">
        <h2>Not</h2>
        <p>{{ $commercialRevision?->note ?? $quote->note }}</p>
    </section>
@endif
@endsection
