<form wire:submit="save" class="stack">
    <x-tabs :tabs="['general'=>'Firma / Ticari','terms'=>'Ticari Koşullar','addresses'=>'Adresler','people'=>'İletişim','banks'=>'Banka Bilgileri']" :active="$activeTab" />

    @if($activeTab === 'general')
        <section class="panel form-grid">
            @if($contact)
                <x-field.text label="Kod" wire:model="code" disabled />
            @endif
            <x-field.text label="Unvan" wire:model="title" />
            <x-field.select label="Tip" wire:model="type" :options="['legal'=>'Tüzel','real'=>'Gerçek']" />
            <x-field.text label="Vergi Dairesi" wire:model="taxOffice" />
            <x-field.text label="Vergi No" wire:model="taxNumber" />
            <x-field.text label="TC Kimlik" wire:model="nationalId" />
            <x-field.textarea label="Adres" wire:model="address" />
            <x-field.text label="İl" wire:model="city" />
            <x-field.text label="İlçe" wire:model="district" />
            <x-field.text label="Telefon" wire:model="phone" />
            <x-field.text label="E-posta" wire:model="email" />
            <div class="field span-2">
                <span class="field-label">Kategoriler</span>
                <div class="check-grid">
                    @foreach($categories as $category)
                        <label><input type="checkbox" wire:model="categoryIds" value="{{ $category->id }}"> {{ $category->name }}</label>
                    @endforeach
                </div>
            </div>
        </section>
    @elseif($activeTab === 'terms')
        <section class="panel form-grid">
            <x-field.number label="Vade Günü" wire:model="termDays" kind="quantity" />
            <x-field.number label="İskonto %" wire:model="discountRate" />
            <x-field.number label="Risk Limiti" wire:model="riskLimit" />
            <label class="field">
                <span class="field-label">Fiyat Listesi</span>
                <select wire:model="priceListId">
                    <option value="">Varsayılan</option>
                    @foreach($priceLists as $list)<option value="{{ $list->id }}">{{ $list->name }}</option>@endforeach
                </select>
            </label>
            <x-field.toggle label="Aktif" wire:model="isActive" />
        </section>
    @else
        <section class="panel">
            @if(!$contact)
                <p>Yan bilgiler için önce cari kartını kaydedin.</p>
            @else
                <p>{{ ucfirst($activeTab) }} kayıtları cari kartı kaydedildikten sonra ayrı optimistic-lock Action'larıyla yönetilir.</p>
            @endif
        </section>
    @endif

    @foreach($errors->all() as $error)<div class="field-error">{{ $error }}</div>@endforeach
    <button type="submit">Kaydet</button>
</form>
