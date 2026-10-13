# MarsProject V65 — Ortak Komponent Paketi (14.10.2026)

## Amaç ve kapsam

V65 onaylı görsel dilini koruyarak, ekranlara dağılmış HTML tekrarı yerine ortak Blade bileşenleri kullanılmaya başlandı. Bu paket **tüm yazılımın tamamlandığı anlamına gelmez**; mevcut Livewire aksiyonları, yetkilendirme ve muhasebe kayıt mantığı korunur.

## Yeni Blade komponentleri

| Kullanım | Komponent | Değişkenler | Varsayılan |
| --- | --- | --- | --- |
| Eylem / bağlantı | `<x-v65.button>` | `variant`, `size`, `href`, `type` | `primary`, `md`, `button` |
| Durum etiketi | `<x-v65.status-badge>` | `tone`, `dot` | `neutral`, `false` |
| Dinamik seçenekler | `<x-v65.select>` | `label`, `name`, `hint`, `placeholder` | boş placeholder yok |
| Boş liste mesajı | `<x-v65.empty-state />` | `title`, `description`, slot | `Kayıt bulunamadı` |
| Form eylemleri | `<x-v65.form-actions>` | `align`, slot | `end` |

Buton boyutları: `xs = 28px`, `sm = 32px`, `md = 38px`, `lg = 44px`, `xl = 48px`.

Buton varyantları: `primary`, `secondary`, `ghost`, `danger`, `success`, `warning`.

Durum tonları: `neutral`, `success`, `danger`, `warning`, `info`. Renk her zaman metinle birlikte kullanılmalıdır.

### Kullanım örnekleri

```blade
<x-v65.form-actions class="v65-form-footer">
    <x-v65.button type="submit" wire:loading.attr="disabled" wire:target="save">Kaydet</x-v65.button>
    <x-v65.button variant="secondary" :href="route('units.index')">Listeye Dön</x-v65.button>
</x-v65.form-actions>

<x-v65.status-badge :tone="$account->is_active ? 'success' : 'danger'">
    {{ $account->is_active ? 'Aktif' : 'Pasif' }}
</x-v65.status-badge>

<x-v65.select label="Banka" wire:model="bankAccountId" placeholder="Seçin">
    @foreach($bankAccounts as $account)
        <option value="{{ $account->id }}">{{ $account->account_name }}</option>
    @endforeach
</x-v65.select>

<x-v65.empty-state title="Henüz kayıt bulunmuyor." />
```

Bileşenlere iletilen `wire:model`, `wire:click`, `wire:target`, `wire:loading.attr`, `disabled`, `aria-*` ve benzeri ek HTML nitelikleri asıl HTML elementine aktarılır. `x-v65.select` var olan `x-field.select` bileşeninin statik `options` desteğini kaldırmaz; **dinamik Blade/Livewire seçenekleri için tamamlayıcı** bir alternatiftir.

## İlk entegrasyon dosyaları

- `resources/views/livewire/finance/finance-accounts.blade.php`: aktif/pasif durumları ve sağa hizalanmış parasal sütunlar.
- `resources/views/livewire/finance/bank-statement-center.blade.php`: dinamik select alanları, seçim/işlem butonları, mutabakat durumu.
- `resources/views/livewire/finance/collection-form.blade.php`: cari/hesap seçimi, tahsilat ve ters kayıt butonları, kesinleşme durumu.
- `resources/views/livewire/pages/catalog/category-list.blade.php`: ağaçtaki durum etiketleri ve boş liste.
- `resources/views/livewire/pages/catalog/category-form.blade.php`: form footer ve kaydet butonu.
- `resources/views/livewire/pages/catalog/brand-form.blade.php`: kaydet ve liste bağlantısı.
- `resources/views/livewire/pages/catalog/unit-form.blade.php`: kaydet, liste bağlantısı ve boş dönüşüm tablosu.
- `resources/views/livewire/pages/catalog/location-form.blade.php`: submit butonu ve form footer.
- `resources/views/livewire/pages/catalog/location-detail.blade.php`: lokasyon durumu ve boş stok tablosu.

Görsel kurallar `resources/css/v65-shell.css` sonundaki uygulama kapsamlı bölümde tanımlıdır. Yazdırma fişleri, kullanıcı girişi ve veri kayıt kodları değiştirilmedi.

## Kapanış koşulları

- [x] Beş ortak bileşeni Blade kaynaklarına ekle.
- [x] Belirtilen dokuz ekranda ilk geçişi yap.
- [x] Kaynak sözleşmesi testini yaz (`tests/Unit/UI/V65UnifiedListSystemTest.php`).
- [ ] PHP/Blade derlemesi ve CSS/Vite build doğrulaması.
- [ ] Livewire davranış testleri (özellikle `wire:model.live`, onay ve ters kayıt).
- [ ] Mobil / tablet / masaüstü V65 referans görüntü karşılaştırması.
- [ ] Kalan modüllerin ve prototip kataloglarının birebir entegrasyonu.
- [ ] Fresh UAT yayın onayı ve kullanıcı kabulü.

**Doğrulama notu:** Test kodu hazırlanmıştır ancak otomatik testler ve yayın bu aşamada çalıştırılmamıştır. Bu iş kaydında tamamlandığı belirtilen kapsam sadece yukarıdaki dosyalardaki kaynak değişiklikleridir.
