<div class="wizard">
    <header class="wizard-heading">
        <h1>Firma Kurulum Sihirbazı</h1>
        <p>Şirket, dönem ve başlangıç yapılandırması</p>
    </header>
    @php($stepNames = ['Firma Bilgileri', 'İlk Dönem', 'Varsayılanlar', 'Lokasyonlar', 'Belge Serileri', 'Yönetici', 'Açılış Verisi', 'Özet ve Tamamla'])
    <div class="wizard-progress" aria-label="Kurulum ilerlemesi">
        <span>Adım {{ $step }} / 8</span>
        <strong>{{ $stepNames[$step - 1] }}</strong>
    </div>
    <ol class="wizard-progress-track" aria-label="Kurulum adımları">
        @foreach($stepNames as $index => $stepName)
            <li @class(['is-active' => $step === $index + 1, 'is-complete' => $step > $index + 1])
                title="{{ $stepName }}" @if($step === $index + 1) aria-current="step" @endif>{{ $index + 1 }}</li>
        @endforeach
    </ol>

    @if ($step === 1)
        <h2>Firma Bilgileri</h2>
        <div class="wizard-form-grid">
            <label class="field"><span class="field-label">Firma Kodu *</span><input wire:model="code" autocomplete="off" maxlength="20"></label>
            <label class="field"><span class="field-label">Kısa Ad *</span><input wire:model="name"></label>
            <label class="field span-2"><span class="field-label">Resmî Unvan</span><input wire:model="legalName"></label>
            <label class="field"><span class="field-label">DB Öneki *</span><input wire:model="dbPrefix" autocomplete="off"><small class="field-hint">Yalnız harf, rakam ve alt çizgi. Kaydedilince değiştirilemez.</small></label>
            <label class="field"><span class="field-label">Para Birimi *</span><input wire:model="baseCurrency" maxlength="3"></label>
            <label class="field"><span class="field-label">Vergi Dairesi</span><input wire:model="taxOffice"></label>
            <label class="field"><span class="field-label">Vergi Numarası</span><input wire:model="taxNumber"></label>
            <label class="field span-2"><span class="field-label">Adres</span><textarea wire:model="address"></textarea></label>
        </div>
    @elseif ($step === 2)
        <h2>İlk Dönem</h2>
        <label class="field"><span class="field-label">Dönem Yılı *</span><input wire:model="year" type="number" min="2000" max="2200"></label>
        <div class="context-preview">Dönem veritabanı: <strong>{{ $dbPrefix }}_{{ $year }}</strong></div>
        @if (config('app.env') === 'uat')
            <p class="field-hint">Hazır UAT dönem veritabanı: <strong>{{ config('operations.database.uat_preprovisioned_period') }}</strong>. Yıl ve önek bununla eşleşmelidir.</p>
        @endif
    @elseif ($step === 3)
        <h2>Varsayılanlar</h2>
        <label class="field"><span class="field-label">Varsayılan Vade Günü *</span><input wire:model="defaultTermDays" type="number" min="0" max="3650"><small class="field-hint">Ödeme için varsayılan süre (gün).</small></label>
        <label class="field"><span class="field-label">Maliyet Sapma Eşiği (%) *</span><input wire:model="costDeviationThreshold" inputmode="decimal"><small class="field-hint">Alış maliyeti değişim uyarı eşiği; örnek: 25.0000.</small></label>
        <label class="field"><span class="field-label">Varsayılan KDV Oranı (%) *</span><input wire:model="defaultVatRate" inputmode="decimal"><small class="field-hint">Önerilen KDV oranı; işlem sırasında gerektiğinde değiştirilir.</small></label>
    @elseif ($step === 4)
        <h2>Lokasyonlar</h2>
        <p>En az bir depo zorunludur. İlave lokasyonlar kurulumdan sonra eklenebilir.</p>
        <label class="field"><span class="field-label">Depo Kodu *</span><input wire:model="warehouseCode"></label>
        <label class="field"><span class="field-label">Depo Adı *</span><input wire:model="warehouseName"></label>
    @elseif ($step === 5)
        <h2>Belge Serileri</h2>
        <div class="context-preview">Varsayılan belge serileri oluşturulacaktır. Seri ayarlarını kurulumdan sonra kontrol edin.</div>
    @elseif ($step === 6)
        <h2>Yönetici Kullanıcı</h2>
        <label class="field"><span class="field-label">Ad Soyad *</span><input wire:model="adminName" autocomplete="name"></label>
        <label class="field"><span class="field-label">E-posta *</span><input wire:model="adminEmail" type="email" autocomplete="email"></label>
        @guest
            <label class="field"><span class="field-label">Parola *</span><input wire:model="adminPassword" type="password" autocomplete="new-password"><small class="field-hint">En az 10 karakter; harf ve rakam içermelidir.</small></label>
        @endguest
    @elseif ($step === 7)
        <h2>Açılış Verisi</h2>
        <div class="context-preview">Bu adım atlanabilir. Cari, ürün ve stok verilerini ilgili modüllerden içe aktarabilirsiniz.</div>
    @else
        <h2>Özet ve Tamamla</h2>
        <dl>
            <dt>Firma</dt><dd>{{ $code }} · {{ $name }}</dd>
            <dt>Dönem</dt><dd>{{ $year }}</dd>
            <dt>Veritabanı</dt><dd>{{ $dbPrefix }}_{{ $year }}</dd>
            <dt>Varsayılanlar</dt><dd>{{ $defaultTermDays }} gün · maliyet %{{ $costDeviationThreshold }} · KDV %{{ $defaultVatRate }}</dd>
            <dt>Depo</dt><dd>{{ $warehouseCode }} · {{ $warehouseName }}</dd>
            <dt>Yönetici</dt><dd>{{ $adminEmail }}</dd>
        </dl>
        <div class="alert alert-warning">DB öneki kayıt sonrası değiştirilemez.
            @if (config('app.env') === 'uat')
                Bu ortamda yeni fiziksel veritabanı oluşturulmaz; hazır dönem veritabanı kullanılır.
            @else
                Tamamla işlemi fiziksel dönem veritabanı oluşturabilir.
            @endif
        </div>
    @endif

    @if($errors->any())
        <div class="wizard-errors" role="alert">
            <strong>Lütfen bilgileri kontrol edin:</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <div class="wizard-actions">
        @if($step > 1)
            <button class="button-secondary" type="button" wire:click="previous" wire:loading.attr="disabled" wire:target="next,finish">Geri</button>
        @else
            <span></span>
        @endif
        @if($step < 8)
            <button type="button" wire:click="next" wire:loading.attr="disabled" wire:target="next,finish">Devam →</button>
        @else
            <button type="button" wire:click="finish" wire:loading.attr="disabled" wire:target="finish">Tamamla</button>
        @endif
    </div>
</div>
