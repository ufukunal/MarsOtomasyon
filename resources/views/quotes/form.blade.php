@extends('layouts.app')

@section('title', $quote === null ? 'Yeni Teklif' : 'Teklif Düzenle')

@section('app-content')
<section class="workspace-hero">
    <div>
        <p class="eyebrow">Satış Yönetimi / Teklifler</p>
        <h1>{{ $quote === null ? 'Yeni Teklif' : $quote->number.' Düzenle' }}</h1>
        <p>Müşteri, tarih ve fiyat koşullarını belirleyin; belge toplamları sunucu tarafında güvenli biçimde yeniden hesaplanır.</p>
    </div>
    <div class="page-actions">
        <a href="{{ route('quotes.index') }}">Teklifler</a>
    </div>
</section>

@if ($errors->any())
<section class="notice-error">
    <strong>Teklif kaydedilemedi.</strong>
    <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
</section>
@endif

<form method="post" action="{{ $quote === null ? route('quotes.store') : route('quotes.update', $quote->getKey()) }}" class="v163-doc v163-doc-form" id="quote-form">
    @csrf
    @if($quote !== null) @method('put') @endif

    <section class="v163-section">
        <div class="v163-section-head">
            Belge Bilgileri
            <small>Teklif başlığı ve ticari koşullar</small>
        </div>
        <div class="v163-section-body">
            <div class="v163-grid">
                @if($quote === null)
                    <div class="v163-field">
                        <label class="v163-required" for="quote-series">Numara Serisi</label>
                        <input id="quote-series" name="series_code" value="{{ old('series_code', 'default') }}" required>
                    </div>
                @endif

                <div class="v163-field full">
                    <label class="v163-required" for="quote-account">Cari / Müşteri</label>
                    <select id="quote-account" name="account_id" required>
                        <option value="">Cari seçin</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->getKey() }}" @selected((string) old('account_id', $quote?->account_id) === (string) $account->getKey())>{{ $account->code }} — {{ $account->legal_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="v163-field">
                    <label class="v163-required" for="quote-date">Teklif Tarihi</label>
                    <input id="quote-date" type="date" name="quote_date" value="{{ old('quote_date', $quote?->quote_date?->format('Y-m-d') ?? now()->toDateString()) }}" required>
                </div>

                <div class="v163-field">
                    <label for="quote-valid-until">Geçerlilik Tarihi</label>
                    <input id="quote-valid-until" type="date" name="valid_until" value="{{ old('valid_until', $quote?->valid_until?->format('Y-m-d')) }}">
                </div>

                <div class="v163-field">
                    <label class="v163-required" for="quote-currency">Para Birimi</label>
                    <select id="quote-currency" name="currency_code" required>
                        @foreach($currencies as $currency)
                            <option value="{{ $currency->code }}" @selected(old('currency_code', $quote?->currency_code ?? 'TRY') === $currency->code)>{{ $currency->code }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="v163-field">
                    <label class="v163-required" for="quote-discount">Belge İskonto %</label>
                    <input id="quote-discount" name="document_discount_rate" inputmode="decimal" value="{{ old('document_discount_rate', $quote?->document_discount_rate ?? '0') }}" required>
                </div>
            </div>
        </div>
    </section>

    @php
        $oldLines = old('lines');
        $formLines = is_array($oldLines) ? $oldLines : ($quote?->lines?->map(fn($line) => [
            'product_id' => $line->product_id, 'description' => $line->description, 'quantity' => $line->quantity,
            'unit_price' => $line->unit_price, 'price_basis' => $line->price_basis->value,
            'line_discount_rate' => $line->line_discount_rate, 'tax_zero_reason_id' => $line->tax_zero_reason_id,
        ])->all() ?? [['product_id'=>'','description'=>'','quantity'=>'1','unit_price'=>'0','price_basis'=>'net','line_discount_rate'=>'0','tax_zero_reason_id'=>'']]);
    @endphp

    <section class="v163-section">
        <div class="v163-section-head">
            Teklif Kalemleri
            <small>Ürün, miktar, fiyat, iskonto ve KDV koşulları</small>
        </div>
        <div class="v163-table-wrap">
            <table class="v163-table" id="quote-lines">
                <thead>
                    <tr>
                        <th>Ürün</th>
                        <th>Açıklama</th>
                        <th>Miktar</th>
                        <th>Birim Fiyat</th>
                        <th>Fiyat Tipi</th>
                        <th>İskonto %</th>
                        <th>KDV 0 Nedeni</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($formLines as $i => $line)
                    <tr>
                        <td>
                            <select class="wide" name="lines[{{ $i }}][product_id]" aria-label="Teklif kalemi ürün" required>
                                <option value="">Ürün seçin</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->getKey() }}" @selected((string)($line['product_id'] ?? '') === (string)$product->getKey())>{{ $product->code }} — {{ $product->name }} (KDV %{{ $product->tax->rate }})</option>
                                @endforeach
                            </select>
                        </td>
                        <td><input class="wide" name="lines[{{ $i }}][description]" aria-label="Kalem açıklaması" value="{{ $line['description'] ?? '' }}" placeholder="Kalem açıklaması"></td>
                        <td><input class="v163-qty" name="lines[{{ $i }}][quantity]" aria-label="Miktar" inputmode="decimal" value="{{ $line['quantity'] ?? '1' }}" required></td>
                        <td><input class="v163-money" name="lines[{{ $i }}][unit_price]" aria-label="Birim fiyat" inputmode="decimal" value="{{ $line['unit_price'] ?? '0' }}" required></td>
                        <td>
                            <select class="v163-compact" name="lines[{{ $i }}][price_basis]" aria-label="Fiyat tipi">
                                @foreach($priceBases as $basis)
                                    <option value="{{ $basis->value }}" @selected(($line['price_basis'] ?? 'net') === $basis->value)>{{ $basis->value === 'net' ? 'KDV Hariç' : 'KDV Dahil' }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td><input class="v163-compact" name="lines[{{ $i }}][line_discount_rate]" aria-label="Kalem iskonto yüzdesi" inputmode="decimal" value="{{ $line['line_discount_rate'] ?? '0' }}" required></td>
                        <td>
                            <select class="wide" name="lines[{{ $i }}][tax_zero_reason_id]" aria-label="KDV sıfır nedeni">
                                <option value="">—</option>
                                @foreach($zeroReasons as $reason)
                                    <option value="{{ $reason->getKey() }}" @selected((string)($line['tax_zero_reason_id'] ?? '') === (string)$reason->getKey())>{{ $reason->code }} — {{ $reason->name }}</option>
                                @endforeach
                            </select>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="v163-section">
        <div class="v163-section-head">Notlar</div>
        <div class="v163-section-body v163-note">
            <textarea id="quote-note" name="note" rows="4" aria-label="Teklif notu" placeholder="Müşteriye veya iç kullanıma yönelik teklif notu">{{ old('note', $quote?->note) }}</textarea>
        </div>
    </section>

    <div class="v163-footer">
        <span class="v163-footer-copy">* Zorunlu alanlar · Toplamlar kayıt sırasında tekrar hesaplanır.</span>
        <span class="v163-grow"></span>
        <a href="{{ $quote === null ? route('quotes.index') : route('quotes.show', $quote->getKey()) }}">Vazgeç</a>
        <button class="button-primary" type="submit">Kaydet</button>
    </div>
</form>
@endsection
