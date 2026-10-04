<form wire:submit="save" class="stack">
    <x-tabs :tabs="['general'=>'Firma / Ticari','terms'=>'Ticari Koşullar','addresses'=>'Adresler','people'=>'İletişim','banks'=>'Banka Bilgileri']" :active="$activeTab" />

    @if($activeTab === 'general')
        <section class="panel form-grid">
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
            <div class="field span-2"><span class="field-label">Kategoriler</span><div class="check-grid">@foreach($categories as $category)<label><input type="checkbox" wire:model="categoryIds" value="{{ $category->id }}"> {{ $category->name }}</label>@endforeach</div></div>
        </section>
    @elseif($activeTab === 'terms')
        <section class="panel form-grid">
            <x-field.number label="Vade Günü" wire:model="termDays" kind="quantity" />
            <x-field.number label="İskonto %" wire:model="discountRate" />
            <x-field.number label="Risk Limiti" wire:model="riskLimit" />
            <label class="field"><span class="field-label">Fiyat Listesi</span><select wire:model="priceListId"><option value="">Varsayılan</option>@foreach($priceLists as $list)<option value="{{ $list->id }}">{{ $list->name }}</option>@endforeach</select></label>
            <x-field.toggle label="Aktif" wire:model="isActive" />
        </section>
    @elseif($activeTab === 'addresses')
        <section class="panel stack">
            @if(!$contact)<p>Önce cari kartını kaydedin.</p>@else
                <div class="form-grid">
                    <x-field.select label="Tür" wire:model="addressType" :options="['invoice'=>'Fatura','shipping'=>'Sevk']" />
                    <x-field.text label="Başlık" wire:model="addressTitle" />
                    <x-field.textarea label="Adres" wire:model="addressLine" />
                    <x-field.text label="İl" wire:model="addressCity" />
                    <x-field.text label="İlçe" wire:model="addressDistrict" />
                    <x-field.toggle label="Varsayılan" wire:model="addressDefault" />
                </div>
                <button type="button" wire:click="saveAddress">{{ $addressId ? 'Adresi Güncelle' : 'Adres Ekle' }}</button>
                <table class="data-table"><thead><tr><th>Tür</th><th>Başlık</th><th>Adres</th><th>Varsayılan</th><th></th></tr></thead><tbody>@foreach($addresses as $row)<tr><td>{{ $row->type }}</td><td>{{ $row->title }}</td><td>{{ $row->address }}</td><td>{{ $row->is_default?'Evet':'Hayır' }}</td><td><button type="button" wire:click="editAddress({{ $row->id }})">Düzenle</button> <button type="button" wire:click="deleteAddress({{ $row->id }})">Sil</button></td></tr>@endforeach</tbody></table>
            @endif
        </section>
    @elseif($activeTab === 'people')
        <section class="panel stack">
            @if(!$contact)<p>Önce cari kartını kaydedin.</p>@else
                <div class="form-grid">
                    <x-field.text label="Ad Soyad" wire:model="personName" />
                    <x-field.text label="Unvan" wire:model="personTitle" />
                    <x-field.text label="Telefon" wire:model="personPhone" />
                    <x-field.text label="E-posta" wire:model="personEmail" />
                    <x-field.toggle label="Varsayılan" wire:model="personDefault" />
                </div>
                <button type="button" wire:click="savePerson">{{ $personId ? 'Yetkiliyi Güncelle' : 'Yetkili Ekle' }}</button>
                <table class="data-table"><thead><tr><th>Ad</th><th>Unvan</th><th>Telefon</th><th>Varsayılan</th><th></th></tr></thead><tbody>@foreach($people as $row)<tr><td>{{ $row->name }}</td><td>{{ $row->title }}</td><td>{{ $row->phone }}</td><td>{{ $row->is_default?'Evet':'Hayır' }}</td><td><button type="button" wire:click="editPerson({{ $row->id }})">Düzenle</button> <button type="button" wire:click="deletePerson({{ $row->id }})">Sil</button></td></tr>@endforeach</tbody></table>
            @endif
        </section>
    @elseif($activeTab === 'banks')
        <section class="panel stack">
            @if(!$contact)<p>Önce cari kartını kaydedin.</p>@else
                <div class="form-grid">
                    <x-field.text label="Banka" wire:model="bankName" />
                    <x-field.text label="IBAN" wire:model="iban" />
                    <x-field.toggle label="Varsayılan" wire:model="bankDefault" />
                </div>
                <button type="button" wire:click="saveBank">{{ $bankId ? 'Bankayı Güncelle' : 'Banka Ekle' }}</button>
                <table class="data-table"><thead><tr><th>Banka</th><th>IBAN</th><th>Varsayılan</th><th></th></tr></thead><tbody>@foreach($banks as $row)<tr><td>{{ $row->bank_name }}</td><td>{{ $row->iban }}</td><td>{{ $row->is_default?'Evet':'Hayır' }}</td><td><button type="button" wire:click="editBank({{ $row->id }})">Düzenle</button> <button type="button" wire:click="deleteBank({{ $row->id }})">Sil</button></td></tr>@endforeach</tbody></table>
            @endif
        </section>
    @endif

    @foreach($errors->all() as $error)<div class="field-error">{{ $error }}</div>@endforeach
    @if(in_array($activeTab,['general','terms'],true))<button type="submit">Kaydet</button>@endif
</form>
