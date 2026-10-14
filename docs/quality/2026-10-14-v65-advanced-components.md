# MarsProject V65 — Gelişmiş ortak komponentler (14.10.2026)

## İşlenen güncelleme

Bu güncelleme, V65 ortak bileşen kataloğunu genişletir. Muhasebe/operasyon servisleri, veri modelleri, veritabanı şemaları, yetkiler, stok hesapları ve fiş yazdırma şablonları değiştirilmemiştir.

### Yeni bileşenler

| Blade bileşeni | İşlev | Başlıca parametreler |
| --- | --- | --- |
| `<x-v65.modal>` | Tarayıcı `dialog` öğesi ile başlık, içerik, footer ve onay penceresi | `id`, `title`, `size` = sm/md/lg |
| `<x-v65.search-field>` | Tek tip erişilebilir arama inputu | `label`, `placeholder`, `wire:model` |
| `<x-v65.multi-select>` | Slot içindeki Livewire checkbox seçenekleri için fieldset | `label`, `hint` |
| `<x-v65.bulk-actions>` | Seçili kayıt sayısı pozitifse toplu işlem bildirimi | `count`, `label` |
| `<x-v65.tabs>` | Aktif Livewire sekmeleri; ok, Home ve End tuşları | `tabs`, `active`, `model`, `label` |
| `<x-v65.notice>` | Başarı, bilgi, uyarı ve hata bildirimleri | `tone`, `title` |

Not: `x-v65.multi-select` seçeneklerin davranışını üstlenmez; `wire:model` içteki gerçek checkbox alanlarında kalır. `x-v65.tabs` anahtarı `activeTab` varsayılanlıdır. `x-v65.bulk-actions` hiçbir finansal toplu işlem metodu oluşturmaz; yalnızca seçim durumunu gösterir. `x-v65.modal` içinde onay eylemini çağıran açık bir Livewire butonu kullanılmalıdır; JS muhasebe komutu çalıştırmaz.

### Örnekler

```blade
<x-v65.search-field label="Genel Arama" wire:model="v65DraftFilters.search" />

<x-v65.multi-select label="Kategoriler">
    @foreach($categories as $category)
        <label><input type="checkbox" wire:model="categoryIds" value="{{ $category->id }}">{{ $category->name }}</label>
    @endforeach
</x-v65.multi-select>

<x-v65.bulk-actions :count="count($selectedSecurityIds)" label="evrak seçildi">
    <span>Bordro işlemini aşağıdaki alanda tamamlayın.</span>
</x-v65.bulk-actions>

<x-v65.button variant="danger" data-v65-modal-open="v65-confirm">Ters Kaydet</x-v65.button>
<x-v65.modal id="v65-confirm" title="Ters Kaydı Onayla" size="sm">
    <p>İşlemi kontrol edin.</p>
    <x-slot:footer>
        <x-v65.button variant="secondary" data-v65-modal-close>Vazgeç</x-v65.button>
        <x-v65.button variant="danger" wire:click="reversePayroll" data-v65-modal-close>Onayla</x-v65.button>
    </x-slot:footer>
</x-v65.modal>
```

## İlk entegrasyon

- **Cari Düzenleme** — `resources/views/livewire/pages/contacts/contact-form.blade.php`: aktif sekme, kategori checkbox seçimleri ve form kaydet alanı. Eski `wire:model="categoryIds"` ve `wire:submit="save"` korunur.
- **Çek/Senet** — `resources/views/livewire/finance/securities-center.blade.php`: portföy seçim göstergesi, kayıt durumu, sayısal sütunların sağa hizalanması, ters kayıttan önce açık onay diyaloğu. `reversePayroll` metodu yalnızca onay butonundadır.
- **Kanal Hesapları** — `resources/views/livewire/channels/channel-account-center.blade.php`: durum ve uyarı kutuları ile hesap aktif/pasif etiketi.
- **Tüm DataTable filtre panelleri** — `resources/views/livewire/components/partials/v65-table-filter-controls.blade.php`: dinamik arama ortak bileşenle çizilir; `wire:model="v65DraftFilters.search"` değişmedi.

Stiller `resources/css/v65-shell.css` sonuna eklenmiştir. Modal JS'inde doğal `showModal()`, dış alan/iptal ile kapama ve açan butona odağı geri taşıma vardır. Sekme JS'i ok, Home ve End tuşlarını destekler.

## Kapsam ve doğrulama

- [x] Altı ortak Blade bileşenini ekle.
- [x] İlgili ekranlara kaynak kod entegrasyonu uygula.
- [x] Bildirimlerin `status`/`alert` rollerini ve modal etiketlerini tanımla.
- [x] Kaynak sözleşmesi testlerini yaz: `tests/Unit/UI/V65AdvancedComponentsTest.php`.
- [ ] PHP/Blade derleme ve Vite build.
- [ ] Livewire etkileşim/regresyon testleri (onay, form kaydı, sekmeler, yeniden çizim).
- [ ] Klavye ve ekran okuyucu kabul testi.
- [ ] Masaüstü, tablet, telefon çözünürlüklerinde prototiple görsel karşılaştırma.
- [ ] Tüm modüllerin bileşen standardına geçirilmesi.
- [ ] Fresh UAT yükleme ve kullanıcı kabulü.

**Test ve yayın yetkisi:** Otomatik testler çalıştırılmamış, PROD veritabanına/Docker'a müdahale edilmemiş, Fresh UAT yayını yapılmamıştır. Bu kayıt yalnızca GitHub kaynak kodu güncellemesini belgelendirir.
