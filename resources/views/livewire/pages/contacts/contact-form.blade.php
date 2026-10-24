<form wire:submit="save" class="stack">
    @if($contact)
        <nav class="v65-product-edit-navigation"><a href="{{ route('contacts.index') }}">← Cari Listesi</a> <a href="{{ route('contacts.show', ['contact' => $contact->id]) }}">Cari Detayına Dön →</a></nav>
    @endif
    <x-v65.tabs :tabs="['general'=>'Firma / Ticari','terms'=>'Ticari Koşullar','addresses'=>'Adresler','people'=>'İletişim','banks'=>'Banka Bilgileri']" :active="$activeTab" panel-prefix="v65-contact-edit" />

    @if($activeTab === 'general')
        <section id="v65-contact-edit-panel-general" role="tabpanel" tabindex="0" aria-labelledby="v65-contact-edit-tab-general" class="panel form-grid">
            @if($contact)<x-field.text label="Kod" wire:model="code" disabled />@endif
            <x-field.text label="Unvan" wire:model="title" />
            <x-field.select label="Tip" wire:model="type" :options="['legal'=>'Tüzel','real'=>'Gerçek']" />
            <x-field.text label="Vergi Dairesi" wire:model="taxOffice" />
            <x-field.text label="Vergi No" wire:model="taxNumber" />
            @can('contacts.sensitive.view')
                <x-field.text label="TC Kimlik" wire:model="nationalId" />
            @else
                <div class="field"><span class="field-label">TC Kimlik</span><div>{{ $this->maskedNationalId() ?: '—' }}</div></div>
            @endcan
            <x-field.textarea label="Adres" wire:model="address" />
            <x-field.text label="İl" wire:model="city" />
            <x-field.text label="İlçe" wire:model="district" />
            <x-field.text label="Telefon" wire:model="phone" />
            <x-field.text label="E-posta" wire:model="email" />
            <x-v65.multi-select label="Kategoriler" class="span-2"><div class="check-grid">@foreach($categories as $category)<label><input type="checkbox" wire:model="categoryIds" value="{{ $category->id }}"> {{ $category->name }}</label>@endforeach</div></x-v65.multi-select>
        </section>
    @elseif($activeTab === 'terms')
        <section id="v65-contact-edit-panel-terms" role="tabpanel" tabindex="0" aria-labelledby="v65-contact-edit-tab-terms" class="panel form-grid">
            <x-field.number label="Vade Günü" wire:model="termDays" kind="quantity" />
            <x-field.number label="İskonto %" wire:model="discountRate" />
            <x-field.number label="Risk Limiti" wire:model="riskLimit" />
            <x-field.toggle label="Aktif" wire:model="isActive" />
        </section>
    @elseif($activeTab === 'addresses')
        <section id="v65-contact-edit-panel-addresses" role="tabpanel" tabindex="0" aria-labelledby="v65-contact-edit-tab-addresses" class="panel stack">
            @if(!$contact)<p>Önce cari kartını kaydedin.</p>@else
                <div class="form-grid">
                    <x-field.select label="Tür" wire:model="addressType" :options="['invoice'=>'Fatura','shipping'=>'Sevk']" />
                    <x-field.text label="Başlık" wire:model="addressTitle" />
                    <x-field.textarea label="Adres" wire:model="addressLine" />
                    <x-field.text label="İl" wire:model="addressCity" />
                    <x-field.text label="İlçe" wire:model="addressDistrict" />
                    <x-field.toggle label="Varsayılan" wire:model="addressDefault" />
                </div>
                <x-v65.button type="button" size="sm" wire:click="saveAddress">{{ $addressId ? 'Adresi Güncelle' : 'Adres Ekle' }}</x-v65.button>
                <div class="table-scroll v65-list-table"><table class="data-table"><thead><tr><th>Tür</th><th>Başlık</th><th>Adres</th><th>Varsayılan</th><th scope="col">İşlemler</th></tr></thead><tbody>@foreach($addresses as $row)<tr><td>{{ \App\Support\Formatting\BusinessLabelFormatter::value('address_type', $row->type) }}</td><td>{{ $row->title }}</td><td>{{ $row->address }}</td><td>{{ $row->is_default?'Evet':'Hayır' }}</td><td class="v65-row-actions"><x-v65.button variant="secondary" size="sm" type="button" wire:click="editAddress({{ $row->id }})">Düzenle</x-v65.button> <x-v65.button variant="danger" size="sm" type="button" wire:click="deleteAddress({{ $row->id }})" wire:confirm="Cari adresi silinsin mi?">Sil</x-v65.button></td></tr>@endforeach</tbody></table></div>
            @endif
        </section>
    @elseif($activeTab === 'people')
        <section id="v65-contact-edit-panel-people" role="tabpanel" tabindex="0" aria-labelledby="v65-contact-edit-tab-people" class="panel stack">
            @if(!$contact)<p>Önce cari kartını kaydedin.</p>@else
                <div class="form-grid">
                    <x-field.text label="Ad Soyad" wire:model="personName" />
                    <x-field.text label="Unvan" wire:model="personTitle" />
                    <x-field.text label="Telefon" wire:model="personPhone" />
                    <x-field.text label="E-posta" wire:model="personEmail" />
                    <x-field.toggle label="Varsayılan" wire:model="personDefault" />
                </div>
                <x-v65.button type="button" size="sm" wire:click="savePerson">{{ $personId ? 'Yetkiliyi Güncelle' : 'Yetkili Ekle' }}</x-v65.button>
                <div class="table-scroll v65-list-table"><table class="data-table"><thead><tr><th>Ad</th><th>Unvan</th><th>Telefon</th><th>Varsayılan</th><th scope="col">İşlemler</th></tr></thead><tbody>@foreach($people as $row)<tr><td>{{ $row->name }}</td><td>{{ $row->title }}</td><td>{{ $row->phone }}</td><td>{{ $row->is_default?'Evet':'Hayır' }}</td><td class="v65-row-actions"><x-v65.button variant="secondary" size="sm" type="button" wire:click="editPerson({{ $row->id }})">Düzenle</x-v65.button> <x-v65.button variant="danger" size="sm" type="button" wire:click="deletePerson({{ $row->id }})" wire:confirm="Cari yetkili kaydı silinsin mi?">Sil</x-v65.button></td></tr>@endforeach</tbody></table></div>
            @endif
        </section>
    @elseif($activeTab === 'banks')
        <section id="v65-contact-edit-panel-banks" role="tabpanel" tabindex="0" aria-labelledby="v65-contact-edit-tab-banks" class="panel stack">
            @if(!$contact)<p>Önce cari kartını kaydedin.</p>@else
                <div class="form-grid">
                    <x-field.text label="Banka" wire:model="bankName" />
                    <x-field.text label="IBAN" wire:model="iban" />
                    <x-field.toggle label="Varsayılan" wire:model="bankDefault" />
                </div>
                <x-v65.button type="button" size="sm" wire:click="saveBank">{{ $bankId ? 'Bankayı Güncelle' : 'Banka Ekle' }}</x-v65.button>
                <div class="table-scroll v65-list-table"><table class="data-table"><thead><tr><th>Banka</th><th>IBAN</th><th>Varsayılan</th><th scope="col">İşlemler</th></tr></thead><tbody>@foreach($banks as $row)<tr><td>{{ $row->bank_name }}</td><td>{{ $row->iban }}</td><td>{{ $row->is_default?'Evet':'Hayır' }}</td><td class="v65-row-actions"><x-v65.button variant="secondary" size="sm" type="button" wire:click="editBank({{ $row->id }})">Düzenle</x-v65.button> <x-v65.button variant="danger" size="sm" type="button" wire:click="deleteBank({{ $row->id }})" wire:confirm="Cari banka bilgisi silinsin mi?">Sil</x-v65.button></td></tr>@endforeach</tbody></table></div>
            @endif
        </section>
    @endif

    @foreach($errors->all() as $error)<div class="field-error">{{ $error }}</div>@endforeach
    @if(in_array($activeTab,['general','terms'],true))<x-v65.form-actions class="v65-form-footer"><x-v65.button type="submit" wire:loading.attr="disabled" wire:target="save">Kaydet</x-v65.button></x-v65.form-actions>@endif
</form>
