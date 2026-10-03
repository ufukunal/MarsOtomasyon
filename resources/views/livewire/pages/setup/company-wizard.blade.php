<div class="wizard">
    <h1>Firma Kurulum Sihirbazı</h1>
    <div class="wizard-progress">Adım {{ $step }} / 8</div>

    @if ($step === 1)
        <h2>Firma Bilgileri</h2>
        <input wire:model="code" placeholder="Kod">
        <input wire:model="name" placeholder="Kısa ad">
        <input wire:model="legalName" placeholder="Resmi unvan">
        <input wire:model="dbPrefix" placeholder="db_prefix">
        <input wire:model="taxOffice" placeholder="Vergi dairesi">
        <input wire:model="taxNumber" placeholder="Vergi no">
        <textarea wire:model="address" placeholder="Adres"></textarea>
        <input wire:model="baseCurrency" placeholder="TRY">
    @elseif ($step === 2)
        <h2>İlk Dönem</h2>
        <input wire:model="year" type="number">
        <p>Oluşacak veritabanı: <strong>{{ $dbPrefix }}_{{ $year }}</strong></p>
    @elseif ($step === 3)
        <h2>Varsayılanlar</h2>
        <input wire:model="defaultTermDays" type="number" placeholder="Vade günü">
        <input wire:model="costDeviationThreshold" placeholder="Maliyet sapma eşiği">
        <input wire:model="defaultVatRate" placeholder="Varsayılan KDV">
    @elseif ($step === 4)
        <h2>Lokasyonlar</h2>
        <p>En az bir depo zorunludur.</p>
        <input wire:model="warehouseCode" placeholder="Depo kodu">
        <input wire:model="warehouseName" placeholder="Depo adı">
    @elseif ($step === 5)
        <h2>Belge Serileri</h2>
        <p>Varsayılan Faz 0 belge serileri kullanılacaktır; seri ekranından sonra değiştirilebilir.</p>
    @elseif ($step === 6)
        <h2>Yönetici Kullanıcı</h2>
        <input wire:model="adminName" placeholder="Ad soyad">
        <input wire:model="adminEmail" type="email" placeholder="E-posta">
        @guest
            <input wire:model="adminPassword" type="password" placeholder="Parola">
        @endguest
    @elseif ($step === 7)
        <h2>Açılış Verisi</h2>
        <p>Açılış veri importu bu adımda atlanabilir; ilgili modüller hazır olduğunda kuyruk üzerinden alınır.</p>
    @else
        <h2>Özet ve Tamamla</h2>
        <dl>
            <dt>Firma</dt><dd>{{ $code }} · {{ $name }}</dd>
            <dt>DB</dt><dd>{{ $dbPrefix }}_{{ $year }}</dd>
            <dt>Depo</dt><dd>{{ $warehouseCode }} · {{ $warehouseName }}</dd>
            <dt>Yönetici</dt><dd>{{ $adminEmail }}</dd>
        </dl>
        <div class="alert alert-warning">
            db_prefix kayıt sonrası değiştirilemez. Tamamla işlemi fiziksel PostgreSQL veritabanı oluşturur.
        </div>
    @endif

    @foreach ($errors->all() as $error)
        <div class="field-error">{{ $error }}</div>
    @endforeach

    <div class="wizard-actions">
        @if ($step > 1)
            <button type="button" wire:click="previous">Geri</button>
        @endif

        @if ($step < 8)
            <button type="button" wire:click="next">Devam</button>
        @else
            <button type="button" wire:click="finish">Tamamla</button>
        @endif
    </div>
</div>
