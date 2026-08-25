@extends('layouts.app')

@section('title', 'Cari Detay')

@section('app-content')
    @php($b2bPolicy = $account->b2bPolicy)

    <section class="workspace-hero">
        <div>
            <p class="eyebrow">Cari Detay</p>
            <h1>{{ $account->legal_name }}</h1>
            <p>{{ $account->code }} · {{ $account->typeEnum()->label() }} · {{ $account->statusEnum()->label() }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('customers.index') }}" data-workspace-link>Listeye Dön</a>
            @can('accounts.manage')
                <a href="{{ route('customers.profile.edit', $account->getKey()) }}" data-workspace-link>İletişim / Adres</a>
                <a href="{{ route('customers.records.edit', $account->getKey()) }}" data-workspace-link>Banka / Not / Dosya</a>
                <a href="{{ route('customers.b2b.edit', $account->getKey()) }}" data-workspace-link>B2B / Bayi Erişimi</a>
                <a class="button-primary" href="{{ route('customers.edit', $account->getKey()) }}" data-workspace-link>Düzenle</a>
            @endcan
        </div>
    </section>

    <section class="detail-card">
        <h2>Firma / Ticari</h2>
        <dl class="detail-list">
            <div><dt>Cari Kodu</dt><dd>{{ $account->code }}</dd></div>
            <div><dt>Resmi Ünvan</dt><dd>{{ $account->legal_name }}</dd></div>
            <div><dt>Ticari Ünvan</dt><dd>{{ $account->trade_name ?: '—' }}</dd></div>
            <div><dt>Cari Türü</dt><dd>{{ $account->typeEnum()->label() }}</dd></div>
            <div><dt>Durum</dt><dd>{{ $account->statusEnum()->label() }}</dd></div>
            <div><dt>Para Birimi</dt><dd>{{ $account->book_currency_code }}</dd></div>
            <div><dt>Vade</dt><dd>{{ $account->due_days }} gün</dd></div>
            <div><dt>Cari İskontosu</dt><dd>%{{ $account->discount_rate }}</dd></div>
            <div><dt>Risk Limiti</dt><dd>{{ $account->risk_limit }} {{ $account->book_currency_code }}</dd></div>
        </dl>
    </section>

    <section class="detail-card">
        <h2>B2B / Bayi Erişimi</h2>
        <dl class="detail-list">
            <div><dt>Erişim</dt><dd>{{ $b2bPolicy?->is_enabled ? 'Aktif' : 'Kapalı' }}</dd></div>
            <div><dt>Sipariş</dt><dd>{{ $b2bPolicy?->is_enabled && $b2bPolicy?->allow_orders ? 'İzinli' : 'Kapalı' }}</dd></div>
            <div><dt>Stok Görünürlüğü</dt><dd>{{ $b2bPolicy?->is_enabled && $b2bPolicy?->show_stock ? 'Açık' : 'Kapalı' }}</dd></div>
            <div><dt>Fatura Görünürlüğü</dt><dd>{{ $b2bPolicy?->is_enabled && $b2bPolicy?->show_invoices ? 'Açık' : 'Kapalı' }}</dd></div>
            <div><dt>Ekstre Görünürlüğü</dt><dd>{{ $b2bPolicy?->is_enabled && $b2bPolicy?->show_statement ? 'Açık' : 'Kapalı' }}</dd></div>
            <div><dt>Adres Yönetimi</dt><dd>{{ $b2bPolicy?->is_enabled && $b2bPolicy?->allow_address_management ? 'İzinli' : 'Kapalı' }}</dd></div>
        </dl>
    </section>

    <section class="detail-card">
        <h2>Vergi Bilgileri</h2>
        <dl class="detail-list">
            <div><dt>Kimlik Türü</dt><dd>{{ $account->taxIdentityTypeEnum()->label() }}</dd></div>
            <div><dt>Vergi / Kimlik No</dt><dd>{{ $account->tax_number ?: '—' }}</dd></div>
            <div><dt>Vergi Dairesi</dt><dd>{{ $account->tax_office ?: '—' }}</dd></div>
        </dl>
    </section>

    <section class="detail-card">
        <h2>İletişim / Yetkililer</h2>
        @if ($account->contacts->isEmpty() && $account->authorizedContacts->isEmpty())
            <p>İletişim veya yetkili kaydı yok.</p>
        @else
            <dl class="detail-list">
                @foreach ($account->contacts->sortByDesc('is_primary') as $contact)
                    <div>
                        <dt>{{ $contact->kind->label() }}{{ $contact->label ? ' · '.$contact->label : '' }}</dt>
                        <dd>{{ $contact->value }}{{ $contact->is_primary ? ' · Birincil' : '' }}</dd>
                    </div>
                @endforeach
                @foreach ($account->authorizedContacts->sortByDesc('is_primary') as $contact)
                    <div>
                        <dt>{{ $contact->is_primary ? 'Birincil Yetkili' : 'Yetkili' }}</dt>
                        <dd>{{ $contact->name }}{{ $contact->title ? ' · '.$contact->title : '' }}{{ $contact->phone ? ' · '.$contact->phone : '' }}{{ $contact->email ? ' · '.$contact->email : '' }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </section>

    <section class="detail-card">
        <h2>Fatura / Sevk Adresleri</h2>
        @if ($account->addresses->isEmpty())
            <p>Adres kaydı yok.</p>
        @else
            <dl class="detail-list">
                @foreach ($account->addresses->sortBy([['type', 'asc'], ['is_default', 'desc']]) as $address)
                    <div>
                        <dt>{{ $address->type->label() }} · {{ $address->label }}{{ $address->is_default ? ' · Varsayılan' : '' }}</dt>
                        <dd>
                            {{ $address->recipient_name ? $address->recipient_name.' · ' : '' }}
                            {{ $address->line1 }}{{ $address->line2 ? ' '.$address->line2 : '' }}{{ $address->district ? ' · '.$address->district : '' }} · {{ $address->city }}{{ $address->postal_code ? ' '.$address->postal_code : '' }} · {{ $address->country_code }}
                        </dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </section>

    <section class="detail-card">
        <h2>Manuel Ambar / Nakliye</h2>
        @if ($account->shippingPreferences->isEmpty())
            <p>Ambar / Nakliye tercihi yok.</p>
        @else
            <dl class="detail-list">
                @foreach ($account->shippingPreferences->sortByDesc('is_default') as $preference)
                    <div>
                        <dt>{{ $preference->company_name }}{{ $preference->is_default ? ' · Varsayılan' : '' }}</dt>
                        <dd>
                            {{ $preference->city }}{{ $preference->branch ? ' · '.$preference->branch : '' }}
                            {{ $preference->contact_name ? ' · '.$preference->contact_name : '' }}
                            {{ $preference->phone ? ' · '.$preference->phone : '' }}
                            {{ $preference->preference ? ' · '.$preference->preference : '' }}
                            {{ $preference->address ? ' · '.$preference->address : '' }}
                            {{ $preference->note ? ' · '.$preference->note : '' }}
                        </dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </section>

    <section class="detail-card">
        <h2>Banka Hesapları</h2>
        @if ($account->bankAccounts->isEmpty())
            <p>Banka hesabı kaydı yok.</p>
        @else
            <dl class="detail-list">
                @foreach ($account->bankAccounts->sortByDesc('is_default') as $bank)
                    <div>
                        <dt>{{ $bank->bank_name }}{{ $bank->branch_name ? ' · '.$bank->branch_name : '' }}{{ $bank->is_default ? ' · Varsayılan' : '' }}</dt>
                        <dd>
                            {{ $bank->currency_code }}
                            {{ $bank->account_holder ? ' · '.$bank->account_holder : '' }}
                            {{ $bank->iban ? ' · IBAN '.$bank->iban : '' }}
                            {{ $bank->account_number ? ' · Hesap '.$bank->account_number : '' }}
                            {{ $bank->swift_code ? ' · SWIFT '.$bank->swift_code : '' }}
                            {{ $bank->note ? ' · '.$bank->note : '' }}
                        </dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </section>

    <section class="detail-card">
        <h2>Dahili Notlar</h2>
        @if ($account->notes->isEmpty())
            <p>Not kaydı yok.</p>
        @else
            <dl class="detail-list">
                @foreach ($account->notes->sortByDesc('is_pinned') as $note)
                    <div>
                        <dt>{{ $note->is_pinned ? 'Sabit Not' : 'Not' }} · {{ $note->updated_at?->format('d.m.Y H:i') }}</dt>
                        <dd>{{ $note->body }}{{ $note->updatedBy?->name ? ' · '.$note->updatedBy->name : '' }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </section>

    <section class="detail-card">
        <h2>Cari Dosyaları</h2>
        @if ($attachments->whereNull('detached_at')->isEmpty())
            <p>Aktif dosya bağlantısı yok.</p>
        @else
            <dl class="detail-list">
                @foreach ($attachments->whereNull('detached_at') as $attachment)
                    <div>
                        <dt>{{ $attachment->label ?: $attachment->fileAsset?->original_name ?: 'Dosya' }}</dt>
                        <dd>
                            {{ $attachment->fileAsset?->original_name ?: '—' }}
                            · <a href="{{ route('customers.files.download', [$account->getKey(), $attachment->getKey()]) }}">İndir</a>
                        </dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </section>
@endsection
