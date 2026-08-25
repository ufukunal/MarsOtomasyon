@extends('layouts.app')

@section('title', 'Cari İletişim / Adres')

@section('app-content')
    @php
        $contactRows = old('contacts', $account->contacts->map(fn ($contact) => [
            'id' => $contact->getKey(),
            'kind' => $contact->kind->value,
            'label' => $contact->label,
            'value' => $contact->value,
            'is_primary' => $contact->is_primary ? '1' : '0',
        ])->values()->all());
        $authorizedRows = old('authorized_contacts', $account->authorizedContacts->map(fn ($contact) => [
            'id' => $contact->getKey(),
            'name' => $contact->name,
            'title' => $contact->title,
            'phone' => $contact->phone,
            'email' => $contact->email,
            'is_primary' => $contact->is_primary ? '1' : '0',
            'note' => $contact->note,
        ])->values()->all());
        $addressRows = old('addresses', $account->addresses->map(fn ($address) => [
            'id' => $address->getKey(),
            'type' => $address->type->value,
            'label' => $address->label,
            'recipient_name' => $address->recipient_name,
            'line1' => $address->line1,
            'line2' => $address->line2,
            'district' => $address->district,
            'city' => $address->city,
            'postal_code' => $address->postal_code,
            'country_code' => $address->country_code,
            'is_default' => $address->is_default ? '1' : '0',
        ])->values()->all());
        $shippingRows = old('shipping_preferences', $account->shippingPreferences->map(fn ($preference) => [
            'id' => $preference->getKey(),
            'company_name' => $preference->company_name,
            'city' => $preference->city,
            'branch' => $preference->branch,
            'contact_name' => $preference->contact_name,
            'phone' => $preference->phone,
            'preference' => $preference->preference,
            'address' => $preference->address,
            'note' => $preference->note,
            'is_default' => $preference->is_default ? '1' : '0',
        ])->values()->all());
    @endphp

    <section class="workspace-hero">
        <div>
            <p class="eyebrow">Cari Düzenle</p>
            <h1>{{ $account->legal_name }}</h1>
            <p>İletişim, yetkili, fatura/sevk adresi ve manuel Ambar/Nakliye tercihlerini yönetin.</p>
        </div>
        <a href="{{ route('customers.show', $account->getKey()) }}" data-workspace-link>Vazgeç</a>
    </section>

    <nav class="page-actions" aria-label="Cari düzenleme bölümleri">
        <a href="{{ route('customers.edit', $account->getKey()) }}" data-workspace-link>Firma / Ticari</a>
        <strong>İletişim / Yetkililer · Sevk / Adres</strong>
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

    <form method="post" action="{{ route('customers.profile.update', $account->getKey()) }}">
        @csrf
        @method('PUT')

        <section class="detail-card">
            <div class="page-actions">
                <div>
                    <h2>Firma İletişim Kanalları</h2>
                    <p>Telefon ve e-posta kayıtları. Her türde en fazla bir birincil kanal seçilebilir.</p>
                </div>
                <button type="button" data-repeat-add="contacts">İletişim Ekle</button>
            </div>

            <div data-repeat-list="contacts" data-next-index="{{ count($contactRows) }}">
                @foreach ($contactRows as $index => $row)
                    <div class="detail-card" data-repeat-row>
                        @if (! empty($row['id']))
                            <input type="hidden" name="contacts[{{ $index }}][id]" value="{{ $row['id'] }}">
                        @endif
                        <div class="form-grid">
                            <label>
                                Tür
                                <select name="contacts[{{ $index }}][kind]" required>
                                    @foreach ($contactKinds as $kind)
                                        <option value="{{ $kind->value }}" @selected(($row['kind'] ?? '') === $kind->value)>{{ $kind->label() }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>
                                Etiket
                                <input name="contacts[{{ $index }}][label]" maxlength="80" value="{{ $row['label'] ?? '' }}" placeholder="Ofis, Muhasebe, Satış">
                            </label>
                            <label>
                                Değer
                                <input name="contacts[{{ $index }}][value]" maxlength="200" required value="{{ $row['value'] ?? '' }}">
                            </label>
                            <label>
                                <input type="hidden" name="contacts[{{ $index }}][is_primary]" value="0">
                                <input type="checkbox" name="contacts[{{ $index }}][is_primary]" value="1" @checked((string) ($row['is_primary'] ?? '0') === '1')>
                                Birincil
                            </label>
                        </div>
                        <div class="page-actions"><span></span><button type="button" data-repeat-remove>Satırı Kaldır</button></div>
                    </div>
                @endforeach
            </div>

            <template data-repeat-template="contacts">
                <div class="detail-card" data-repeat-row>
                    <div class="form-grid">
                        <label>
                            Tür
                            <select name="contacts[__INDEX__][kind]" required>
                                @foreach ($contactKinds as $kind)
                                    <option value="{{ $kind->value }}">{{ $kind->label() }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Etiket<input name="contacts[__INDEX__][label]" maxlength="80" placeholder="Ofis, Muhasebe, Satış"></label>
                        <label>Değer<input name="contacts[__INDEX__][value]" maxlength="200" required></label>
                        <label>
                            <input type="hidden" name="contacts[__INDEX__][is_primary]" value="0">
                            <input type="checkbox" name="contacts[__INDEX__][is_primary]" value="1"> Birincil
                        </label>
                    </div>
                    <div class="page-actions"><span></span><button type="button" data-repeat-remove>Satırı Kaldır</button></div>
                </div>
            </template>
        </section>

        <section class="detail-card">
            <div class="page-actions">
                <div>
                    <h2>Yetkililer</h2>
                    <p>Liste boş değilse tam olarak bir birincil yetkili seçilmelidir.</p>
                </div>
                <button type="button" data-repeat-add="authorized_contacts">Yetkili Ekle</button>
            </div>

            <div data-repeat-list="authorized_contacts" data-next-index="{{ count($authorizedRows) }}">
                @foreach ($authorizedRows as $index => $row)
                    <div class="detail-card" data-repeat-row>
                        @if (! empty($row['id']))
                            <input type="hidden" name="authorized_contacts[{{ $index }}][id]" value="{{ $row['id'] }}">
                        @endif
                        <div class="form-grid">
                            <label>Ad Soyad<input name="authorized_contacts[{{ $index }}][name]" maxlength="160" required value="{{ $row['name'] ?? '' }}"></label>
                            <label>Görev / Ünvan<input name="authorized_contacts[{{ $index }}][title]" maxlength="120" value="{{ $row['title'] ?? '' }}"></label>
                            <label>Telefon<input name="authorized_contacts[{{ $index }}][phone]" maxlength="40" value="{{ $row['phone'] ?? '' }}"></label>
                            <label>E-Posta<input type="email" name="authorized_contacts[{{ $index }}][email]" maxlength="200" value="{{ $row['email'] ?? '' }}"></label>
                            <label>Not<input name="authorized_contacts[{{ $index }}][note]" maxlength="500" value="{{ $row['note'] ?? '' }}"></label>
                            <label>
                                <input type="hidden" name="authorized_contacts[{{ $index }}][is_primary]" value="0">
                                <input type="checkbox" name="authorized_contacts[{{ $index }}][is_primary]" value="1" @checked((string) ($row['is_primary'] ?? '0') === '1')>
                                Birincil Yetkili
                            </label>
                        </div>
                        <div class="page-actions"><span></span><button type="button" data-repeat-remove>Yetkiliyi Kaldır</button></div>
                    </div>
                @endforeach
            </div>

            <template data-repeat-template="authorized_contacts">
                <div class="detail-card" data-repeat-row>
                    <div class="form-grid">
                        <label>Ad Soyad<input name="authorized_contacts[__INDEX__][name]" maxlength="160" required></label>
                        <label>Görev / Ünvan<input name="authorized_contacts[__INDEX__][title]" maxlength="120"></label>
                        <label>Telefon<input name="authorized_contacts[__INDEX__][phone]" maxlength="40"></label>
                        <label>E-Posta<input type="email" name="authorized_contacts[__INDEX__][email]" maxlength="200"></label>
                        <label>Not<input name="authorized_contacts[__INDEX__][note]" maxlength="500"></label>
                        <label>
                            <input type="hidden" name="authorized_contacts[__INDEX__][is_primary]" value="0">
                            <input type="checkbox" name="authorized_contacts[__INDEX__][is_primary]" value="1"> Birincil Yetkili
                        </label>
                    </div>
                    <div class="page-actions"><span></span><button type="button" data-repeat-remove>Yetkiliyi Kaldır</button></div>
                </div>
            </template>
        </section>

        <section class="detail-card">
            <div class="page-actions">
                <div>
                    <h2>Fatura / Sevk Adresleri</h2>
                    <p>Fatura ve sevk adresleri ayrı tutulur; her türde en fazla bir varsayılan adres seçilebilir.</p>
                </div>
                <button type="button" data-repeat-add="addresses">Adres Ekle</button>
            </div>

            <div data-repeat-list="addresses" data-next-index="{{ count($addressRows) }}">
                @foreach ($addressRows as $index => $row)
                    <div class="detail-card" data-repeat-row>
                        @if (! empty($row['id']))
                            <input type="hidden" name="addresses[{{ $index }}][id]" value="{{ $row['id'] }}">
                        @endif
                        <div class="form-grid">
                            <label>
                                Tür
                                <select name="addresses[{{ $index }}][type]" required>
                                    @foreach ($addressTypes as $type)
                                        <option value="{{ $type->value }}" @selected(($row['type'] ?? '') === $type->value)>{{ $type->label() }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>Etiket<input name="addresses[{{ $index }}][label]" maxlength="80" required value="{{ $row['label'] ?? '' }}"></label>
                            <label>Alıcı / Firma<input name="addresses[{{ $index }}][recipient_name]" maxlength="200" value="{{ $row['recipient_name'] ?? '' }}"></label>
                            <label>Adres<input name="addresses[{{ $index }}][line1]" maxlength="240" required value="{{ $row['line1'] ?? '' }}"></label>
                            <label>Adres 2<input name="addresses[{{ $index }}][line2]" maxlength="240" value="{{ $row['line2'] ?? '' }}"></label>
                            <label>İlçe<input name="addresses[{{ $index }}][district]" maxlength="120" value="{{ $row['district'] ?? '' }}"></label>
                            <label>Şehir<input name="addresses[{{ $index }}][city]" maxlength="120" required value="{{ $row['city'] ?? '' }}"></label>
                            <label>Posta Kodu<input name="addresses[{{ $index }}][postal_code]" maxlength="20" value="{{ $row['postal_code'] ?? '' }}"></label>
                            <label>Ülke Kodu<input name="addresses[{{ $index }}][country_code]" maxlength="2" required value="{{ $row['country_code'] ?? 'TR' }}"></label>
                            <label>
                                <input type="hidden" name="addresses[{{ $index }}][is_default]" value="0">
                                <input type="checkbox" name="addresses[{{ $index }}][is_default]" value="1" @checked((string) ($row['is_default'] ?? '0') === '1')>
                                Varsayılan
                            </label>
                        </div>
                        <div class="page-actions"><span></span><button type="button" data-repeat-remove>Adresi Kaldır</button></div>
                    </div>
                @endforeach
            </div>

            <template data-repeat-template="addresses">
                <div class="detail-card" data-repeat-row>
                    <div class="form-grid">
                        <label>
                            Tür
                            <select name="addresses[__INDEX__][type]" required>
                                @foreach ($addressTypes as $type)
                                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Etiket<input name="addresses[__INDEX__][label]" maxlength="80" required></label>
                        <label>Alıcı / Firma<input name="addresses[__INDEX__][recipient_name]" maxlength="200"></label>
                        <label>Adres<input name="addresses[__INDEX__][line1]" maxlength="240" required></label>
                        <label>Adres 2<input name="addresses[__INDEX__][line2]" maxlength="240"></label>
                        <label>İlçe<input name="addresses[__INDEX__][district]" maxlength="120"></label>
                        <label>Şehir<input name="addresses[__INDEX__][city]" maxlength="120" required></label>
                        <label>Posta Kodu<input name="addresses[__INDEX__][postal_code]" maxlength="20"></label>
                        <label>Ülke Kodu<input name="addresses[__INDEX__][country_code]" maxlength="2" required value="TR"></label>
                        <label>
                            <input type="hidden" name="addresses[__INDEX__][is_default]" value="0">
                            <input type="checkbox" name="addresses[__INDEX__][is_default]" value="1"> Varsayılan
                        </label>
                    </div>
                    <div class="page-actions"><span></span><button type="button" data-repeat-remove>Adresi Kaldır</button></div>
                </div>
            </template>
        </section>

        <section class="detail-card">
            <div class="page-actions">
                <div>
                    <h2>Manuel Ambar / Nakliye</h2>
                    <p>Hazır firma kataloğu kullanılmaz; cari için tercih edilen ve alternatif firmaları manuel kaydedin.</p>
                </div>
                <button type="button" data-repeat-add="shipping_preferences">Alternatif Firma Ekle</button>
            </div>

            <div data-repeat-list="shipping_preferences" data-next-index="{{ count($shippingRows) }}">
                @foreach ($shippingRows as $index => $row)
                    <div class="detail-card" data-repeat-row>
                        @if (! empty($row['id']))
                            <input type="hidden" name="shipping_preferences[{{ $index }}][id]" value="{{ $row['id'] }}">
                        @endif
                        <div class="form-grid">
                            <label>Firma Adı<input name="shipping_preferences[{{ $index }}][company_name]" maxlength="200" required value="{{ $row['company_name'] ?? '' }}"></label>
                            <label>Şehir<input name="shipping_preferences[{{ $index }}][city]" maxlength="120" required value="{{ $row['city'] ?? '' }}"></label>
                            <label>Şube<input name="shipping_preferences[{{ $index }}][branch]" maxlength="120" value="{{ $row['branch'] ?? '' }}"></label>
                            <label>Ambar Yetkilisi<input name="shipping_preferences[{{ $index }}][contact_name]" maxlength="160" value="{{ $row['contact_name'] ?? '' }}"></label>
                            <label>Ambar Telefonu<input name="shipping_preferences[{{ $index }}][phone]" maxlength="40" value="{{ $row['phone'] ?? '' }}"></label>
                            <label>Tercih<input name="shipping_preferences[{{ $index }}][preference]" maxlength="120" value="{{ $row['preference'] ?? '' }}" placeholder="Öncelikli, ekonomik, hızlı..."></label>
                            <label>Adres<input name="shipping_preferences[{{ $index }}][address]" maxlength="500" value="{{ $row['address'] ?? '' }}"></label>
                            <label>Not<input name="shipping_preferences[{{ $index }}][note]" maxlength="1000" value="{{ $row['note'] ?? '' }}"></label>
                            <label>
                                <input type="hidden" name="shipping_preferences[{{ $index }}][is_default]" value="0">
                                <input type="checkbox" name="shipping_preferences[{{ $index }}][is_default]" value="1" @checked((string) ($row['is_default'] ?? '0') === '1')>
                                Varsayılan Tercih
                            </label>
                        </div>
                        <div class="page-actions"><span></span><button type="button" data-repeat-remove>Firmayı Kaldır</button></div>
                    </div>
                @endforeach
            </div>

            <template data-repeat-template="shipping_preferences">
                <div class="detail-card" data-repeat-row>
                    <div class="form-grid">
                        <label>Firma Adı<input name="shipping_preferences[__INDEX__][company_name]" maxlength="200" required></label>
                        <label>Şehir<input name="shipping_preferences[__INDEX__][city]" maxlength="120" required></label>
                        <label>Şube<input name="shipping_preferences[__INDEX__][branch]" maxlength="120"></label>
                        <label>Ambar Yetkilisi<input name="shipping_preferences[__INDEX__][contact_name]" maxlength="160"></label>
                        <label>Ambar Telefonu<input name="shipping_preferences[__INDEX__][phone]" maxlength="40"></label>
                        <label>Tercih<input name="shipping_preferences[__INDEX__][preference]" maxlength="120" placeholder="Öncelikli, ekonomik, hızlı..."></label>
                        <label>Adres<input name="shipping_preferences[__INDEX__][address]" maxlength="500"></label>
                        <label>Not<input name="shipping_preferences[__INDEX__][note]" maxlength="1000"></label>
                        <label>
                            <input type="hidden" name="shipping_preferences[__INDEX__][is_default]" value="0">
                            <input type="checkbox" name="shipping_preferences[__INDEX__][is_default]" value="1"> Varsayılan Tercih
                        </label>
                    </div>
                    <div class="page-actions"><span></span><button type="button" data-repeat-remove>Firmayı Kaldır</button></div>
                </div>
            </template>
        </section>

        <div class="page-actions">
            <span></span>
            <button type="submit">Kaydet</button>
        </div>
    </form>
@endsection
