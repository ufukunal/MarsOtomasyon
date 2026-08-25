@extends('layouts.app')

@section('title', 'Cari Banka / Not / Dosya')

@section('app-content')
    @php
        $bankRows = old('bank_accounts', $account->bankAccounts->map(fn ($bank) => [
            'id' => $bank->getKey(),
            'bank_name' => $bank->bank_name,
            'branch_name' => $bank->branch_name,
            'account_holder' => $bank->account_holder,
            'iban' => $bank->iban,
            'account_number' => $bank->account_number,
            'swift_code' => $bank->swift_code,
            'currency_code' => $bank->currency_code,
            'is_default' => $bank->is_default ? '1' : '0',
            'note' => $bank->note,
        ])->values()->all());
        $noteRows = old('notes', $account->notes->sortByDesc('is_pinned')->map(fn ($note) => [
            'id' => $note->getKey(),
            'body' => $note->body,
            'is_pinned' => $note->is_pinned ? '1' : '0',
        ])->values()->all());
    @endphp

    <section class="workspace-hero">
        <div>
            <p class="eyebrow">Cari Düzenle</p>
            <h1>{{ $account->legal_name }}</h1>
            <p>Banka hesapları, dahili notlar ve cariye bağlı private dosyaları yönetin.</p>
        </div>
        <a href="{{ route('customers.show', $account->getKey()) }}" data-workspace-link>Vazgeç</a>
    </section>

    <nav class="page-actions" aria-label="Cari düzenleme bölümleri">
        <a href="{{ route('customers.edit', $account->getKey()) }}" data-workspace-link>Firma / Ticari</a>
        <a href="{{ route('customers.profile.edit', $account->getKey()) }}" data-workspace-link>İletişim / Yetkililer · Sevk / Adres</a>
        <strong>Banka / Not / Dosya</strong>
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

    <form method="post" action="{{ route('customers.records.update', $account->getKey()) }}">
        @csrf
        @method('PUT')

        <section class="detail-card">
            <div class="page-actions">
                <div>
                    <h2>Banka Hesapları</h2>
                    <p>IBAN doğrulanır ve normalize edilir. En fazla bir varsayılan banka hesabı seçilebilir.</p>
                </div>
                <button type="button" data-repeat-add="bank_accounts">Banka Hesabı Ekle</button>
            </div>

            <div data-repeat-list="bank_accounts" data-next-index="{{ count($bankRows) }}">
                @foreach ($bankRows as $index => $row)
                    <div class="detail-card" data-repeat-row>
                        @if (! empty($row['id']))
                            <input type="hidden" name="bank_accounts[{{ $index }}][id]" value="{{ $row['id'] }}">
                        @endif
                        <div class="form-grid">
                            <label>Banka Adı<input name="bank_accounts[{{ $index }}][bank_name]" maxlength="160" required value="{{ $row['bank_name'] ?? '' }}"></label>
                            <label>Şube<input name="bank_accounts[{{ $index }}][branch_name]" maxlength="120" value="{{ $row['branch_name'] ?? '' }}"></label>
                            <label>Hesap Sahibi<input name="bank_accounts[{{ $index }}][account_holder]" maxlength="200" value="{{ $row['account_holder'] ?? '' }}"></label>
                            <label>IBAN<input name="bank_accounts[{{ $index }}][iban]" maxlength="64" autocomplete="off" value="{{ $row['iban'] ?? '' }}" placeholder="TR00 0000 ..."></label>
                            <label>Hesap No<input name="bank_accounts[{{ $index }}][account_number]" maxlength="64" autocomplete="off" value="{{ $row['account_number'] ?? '' }}"></label>
                            <label>SWIFT / BIC<input name="bank_accounts[{{ $index }}][swift_code]" maxlength="16" value="{{ $row['swift_code'] ?? '' }}"></label>
                            <label>
                                Para Birimi
                                <select name="bank_accounts[{{ $index }}][currency_code]" required>
                                    @foreach ($currencies as $currency)
                                        <option value="{{ $currency->code }}" @selected(($row['currency_code'] ?? $account->book_currency_code) === $currency->code)>{{ $currency->code }} · {{ $currency->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>Not<input name="bank_accounts[{{ $index }}][note]" maxlength="500" value="{{ $row['note'] ?? '' }}"></label>
                            <label>
                                <input type="hidden" name="bank_accounts[{{ $index }}][is_default]" value="0">
                                <input type="checkbox" name="bank_accounts[{{ $index }}][is_default]" value="1" @checked((string) ($row['is_default'] ?? '0') === '1')>
                                Varsayılan Hesap
                            </label>
                        </div>
                        <div class="page-actions"><span></span><button type="button" data-repeat-remove>Hesabı Kaldır</button></div>
                    </div>
                @endforeach
            </div>

            <template data-repeat-template="bank_accounts">
                <div class="detail-card" data-repeat-row>
                    <div class="form-grid">
                        <label>Banka Adı<input name="bank_accounts[__INDEX__][bank_name]" maxlength="160" required></label>
                        <label>Şube<input name="bank_accounts[__INDEX__][branch_name]" maxlength="120"></label>
                        <label>Hesap Sahibi<input name="bank_accounts[__INDEX__][account_holder]" maxlength="200"></label>
                        <label>IBAN<input name="bank_accounts[__INDEX__][iban]" maxlength="64" autocomplete="off" placeholder="TR00 0000 ..."></label>
                        <label>Hesap No<input name="bank_accounts[__INDEX__][account_number]" maxlength="64" autocomplete="off"></label>
                        <label>SWIFT / BIC<input name="bank_accounts[__INDEX__][swift_code]" maxlength="16"></label>
                        <label>
                            Para Birimi
                            <select name="bank_accounts[__INDEX__][currency_code]" required>
                                @foreach ($currencies as $currency)
                                    <option value="{{ $currency->code }}" @selected($account->book_currency_code === $currency->code)>{{ $currency->code }} · {{ $currency->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Not<input name="bank_accounts[__INDEX__][note]" maxlength="500"></label>
                        <label>
                            <input type="hidden" name="bank_accounts[__INDEX__][is_default]" value="0">
                            <input type="checkbox" name="bank_accounts[__INDEX__][is_default]" value="1"> Varsayılan Hesap
                        </label>
                    </div>
                    <div class="page-actions"><span></span><button type="button" data-repeat-remove>Hesabı Kaldır</button></div>
                </div>
            </template>
        </section>

        <section class="detail-card">
            <div class="page-actions">
                <div>
                    <h2>Dahili Notlar</h2>
                    <p>Notlar kullanıcı bilgisiyle saklanır. Önemli notları sabitleyebilirsiniz.</p>
                </div>
                <button type="button" data-repeat-add="notes">Not Ekle</button>
            </div>

            <div data-repeat-list="notes" data-next-index="{{ count($noteRows) }}">
                @foreach ($noteRows as $index => $row)
                    <div class="detail-card" data-repeat-row>
                        @if (! empty($row['id']))
                            <input type="hidden" name="notes[{{ $index }}][id]" value="{{ $row['id'] }}">
                        @endif
                        <div class="form-grid">
                            <label>
                                Not
                                <textarea name="notes[{{ $index }}][body]" maxlength="10000" rows="4" required>{{ $row['body'] ?? '' }}</textarea>
                            </label>
                            <label>
                                <input type="hidden" name="notes[{{ $index }}][is_pinned]" value="0">
                                <input type="checkbox" name="notes[{{ $index }}][is_pinned]" value="1" @checked((string) ($row['is_pinned'] ?? '0') === '1')>
                                Sabitle
                            </label>
                        </div>
                        <div class="page-actions"><span></span><button type="button" data-repeat-remove>Notu Kaldır</button></div>
                    </div>
                @endforeach
            </div>

            <template data-repeat-template="notes">
                <div class="detail-card" data-repeat-row>
                    <div class="form-grid">
                        <label>Not<textarea name="notes[__INDEX__][body]" maxlength="10000" rows="4" required></textarea></label>
                        <label>
                            <input type="hidden" name="notes[__INDEX__][is_pinned]" value="0">
                            <input type="checkbox" name="notes[__INDEX__][is_pinned]" value="1"> Sabitle
                        </label>
                    </div>
                    <div class="page-actions"><span></span><button type="button" data-repeat-remove>Notu Kaldır</button></div>
                </div>
            </template>
        </section>

        <div class="page-actions">
            <span></span>
            <button type="submit">Banka / Not Bilgilerini Kaydet</button>
        </div>
    </form>

    <section class="detail-card">
        <h2>Cari Dosyaları</h2>
        <p>Dosyalar private storage alanında tutulur; doğrudan public URL üretilmez.</p>

        <form method="post" enctype="multipart/form-data" action="{{ route('customers.files.store', $account->getKey()) }}">
            @csrf
            <div class="form-grid">
                <label>Dosya<input type="file" name="file" required data-dirty-ignore></label>
                <label>Etiket<input name="label" maxlength="160" data-dirty-ignore placeholder="Sözleşme, banka yazısı, teknik belge..."></label>
            </div>
            <div class="page-actions"><span></span><button type="submit">Dosya Yükle</button></div>
        </form>

        @if ($attachments->isEmpty())
            <p>Bu cariye bağlı dosya yok.</p>
        @else
            <dl class="detail-list">
                @foreach ($attachments as $attachment)
                    <div>
                        <dt>{{ $attachment->label ?: $attachment->fileAsset?->original_name ?: 'Dosya' }}</dt>
                        <dd>
                            {{ $attachment->fileAsset?->original_name ?: '—' }} · {{ $attachment->fileAsset?->mime_type ?: '—' }} · {{ $attachment->fileAsset?->size_bytes ?: 0 }} byte
                            @if ($attachment->isDetached())
                                · Bağlantı kaldırıldı
                            @else
                                · <a href="{{ route('customers.files.download', [$account->getKey(), $attachment->getKey()]) }}">İndir</a>
                                <form method="post" action="{{ route('customers.files.detach', [$account->getKey(), $attachment->getKey()]) }}" style="display:inline">
                                    @csrf
                                    <button type="submit">Bağlantıyı Kaldır</button>
                                </form>
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </section>
@endsection
