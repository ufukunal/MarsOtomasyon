@extends('layouts.app')

@section('title', 'Cari B2B / Bayi Erişimi')

@section('app-content')
    @php
        $policy = $account->b2bPolicy;
        $value = static fn (string $key, bool $default = false): string => old($key, (bool) ($policy?->{$key} ?? $default)) ? '1' : '0';
    @endphp

    <section class="workspace-hero">
        <div>
            <p class="eyebrow">Cari Düzenle</p>
            <h1>{{ $account->legal_name }}</h1>
            <p>Bayi erişiminin account-level sınırlarını yönetin. Cari iskonto ve risk limiti Firma / Ticari alanından tek kaynak olarak kullanılır.</p>
        </div>
        <a href="{{ route('customers.show', $account->getKey()) }}" data-workspace-link>Vazgeç</a>
    </section>

    <nav class="page-actions" aria-label="Cari düzenleme bölümleri">
        <a href="{{ route('customers.edit', $account->getKey()) }}" data-workspace-link>Firma / Ticari</a>
        <a href="{{ route('customers.profile.edit', $account->getKey()) }}" data-workspace-link>İletişim / Yetkililer · Sevk / Adres</a>
        <a href="{{ route('customers.records.edit', $account->getKey()) }}" data-workspace-link>Banka / Not / Dosya</a>
        <strong>B2B / Bayi Erişimi</strong>
    </nav>

    @if ($errors->any())
        <div class="notice-error" role="alert">
            <strong>Kayıt tamamlanamadı.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="detail-card">
        <h2>Ticari Kaynaklar</h2>
        <p>Bu değerler burada salt okunurdur; B2B fiyat ve risk politikası aynı cari master verisini kullanır.</p>
        <dl class="detail-list">
            <div><dt>Cari İskontosu</dt><dd>%{{ $account->discount_rate }}</dd></div>
            <div><dt>Risk Limiti</dt><dd>{{ $account->risk_limit }} {{ $account->book_currency_code }}</dd></div>
        </dl>
        <div class="page-actions">
            <span></span>
            <a href="{{ route('customers.edit', $account->getKey()) }}" data-workspace-link>Ticari Değerleri Düzenle</a>
        </div>
    </section>

    <form method="post" action="{{ route('customers.b2b.update', $account->getKey()) }}" class="detail-card">
        @csrf
        @method('PUT')

        <h2>B2B Erişim Politikası</h2>
        <p>Erişim kapalıysa aşağıdaki izinler saklanır fakat etkili olmaz. B2B kullanıcıları bağlandığında bu account-level sınırların dışına çıkamaz.</p>

        <div class="form-grid">
            @foreach ([
                'is_enabled' => ['B2B / Bayi Erişimi Aktif', 'Bu carinin bayi portalına erişebilmesine izin verir.'],
                'allow_orders' => ['Sipariş Verebilir', 'B2B üzerinden sipariş oluşturma yetkisini açar.'],
                'show_stock' => ['Stok Görebilir', 'Ürün stok görünürlüğüne izin verir.'],
                'show_invoices' => ['Faturaları Görebilir', 'Cari faturalarının B2B görünümüne izin verir.'],
                'show_statement' => ['Ekstreyi Görebilir', 'Cari hareketleri / ekstre görünürlüğüne izin verir.'],
                'allow_address_management' => ['Adres Yönetebilir', 'B2B tarafında izin verilen adres işlemlerine temel oluşturur.'],
            ] as $key => [$label, $help])
                <label>
                    <input type="hidden" name="{{ $key }}" value="0">
                    <input type="checkbox" name="{{ $key }}" value="1" @checked($value($key) === '1')>
                    {{ $label }}
                    <small>{{ $help }}</small>
                </label>
            @endforeach
        </div>

        <div class="page-actions">
            <span></span>
            <button type="submit">Kaydet</button>
        </div>
    </form>
@endsection
