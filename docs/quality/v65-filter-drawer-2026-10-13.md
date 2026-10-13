# V65 ortak filtre paneli — kaynak değişikliği ve kabul raporu

Tarih: 2026-10-13

## Kapsam ve kullanıcı kararı

Üstte yatay sıralanan filtre kutuları liste ekranlarında kaldırıldı. Sağdan açılan ortak bir filtre paneline geçildi. Sekmeler Filtreleme / Sıralama, altında Temizle / Filtrele işlemleri; aktif filtre etiketleri ve ayrık taslak/applied state bulunur. Panel içinde henüz uygulanmamış değişiklikler kapatılınca iptal edilir. Kart ve fiş içi düzenleme/baskı işlemleri korunur. Ürün Listesi'nde kaldırılmış görsel sütunu yeniden eklenmedi.

## Başlangıç

`main` HEAD: `e7cfae17761c54e31ceaaa89172ecc307d4a13ba`.

## Ortak dosyalar

- `app/Livewire/Concerns/WithV65FilterDrawer.php` — state, enum/options/date/length/sort doğrulama, temizleme ve aktif chip bilgisi.
- `app/Livewire/Concerns/WithV65SimpleListFilter.php` — ayrı listSearch kullanan ekranlar için uyarlama.
- `app/Livewire/Components/DataTable/TextFilter.php` — beyaz listeli metin filtresi.
- `app/Livewire/Components/DataTable/DataTableComponent.php` — ortak filtre mantığı ve kullanıcı filtresinin SQL'e güvenli aktarımı.
- `resources/views/components/v65/filter-drawer.blade.php`, `filter-field.blade.php`, `active-filters.blade.php` — ortak görünüm.
- `resources/css/v65-filter-drawer.css` — desktop 400px drawer, mobil tam genişlik, sabit başlık/footer, keyboard focus.
- `resources/js/app.js` — native dialog/ESC/backdrop/focus/navigation.
- `resources/views/layouts/app.blade.php` ve `vite.config.js` — asset tanımları.

## Uygulanan ekranlar

1. `resources/views/livewire/components/data-table.blade.php`: Ürün, Cari, Marka, Birim, Lokasyon, Fiyat Listesi ve Stok modüllerinde bütün ortak DataTable listeleri. Başlıklar, açılan kayıt bağlantıları, CSV/Excel, sayfalama korunur.
2. `app/Livewire/Pages/Products/ProductList.php`: Ürün kodu, adı, barkod (model alanları doğrulandı), marka, kategori, tip, stok tipi, durum.
3. `app/Livewire/Concerns/WithV65DocumentList.php` ve `resources/views/livewire/sales/document-list.blade.php`: satış/alış faturası, sipariş ve diğer ortak belge listeleri.
4. `app/Livewire/Pages/Warehouse/TransportSlipList.php` ve `resources/views/livewire/pages/warehouse/transport-slip-list.blade.php`: Ambar Fişi Listesi; eski URL'deki ayrı tableSearch hâlâ okunur, yeni drawer araması tekli yazılır.
5. `app/Livewire/Production/ProductionOrderCenter.php`, `RecipeCenter.php`, `SubcontractingCenter.php` ile ilgili üç Blade listesi.
6. `app/Livewire/Imports/ImportCenter.php` ve ilgili liste Blade'i.
7. `app/Livewire/Channels/ChannelListingCenter.php` ve ilgili liste Blade'i.

Filtreli olmayan Kanal Hesapları, Kasa/Banka ve İade ekranlarında işlemsel form alanları yanlışlıkla liste filtresi sayılmadı; bu ekranlara filtre icat edilmedi. Kategori 1/2/3 hiyerarşisi ile tedarikçi ürün kodu bağımsız alanı veri modeli ve kabul kapsamı doğrulanmadan eklenmedi. İleride bu alanlar istenirse model/sorgu sözleşmesi ayrıca teyit edilecek.

## Statik kabul denetimleri

- Tüm ortak/özel yeni drawer kullanım alanlarının kaynağında `<x-v65.filter-drawer` yer alır.
- Daha önce `<x-v65.list-filters>` kullanan yedi görünüm yeni panele alındı.
- `wire:model` taslak state'e bağlı; veri sorgusu yalnız `applyV65Filters` ile etkilenir.
- Seçim seçenekleri, arama uzunluğu, tarih biçimi ve sıralama whitelist ile sunucuda doğrulanır.
- Stok Hareketleri ve Rezervasyonlar gibi ekranların eski varsayılan sıralaması Reflection default properties ile korunur.
- Ürün Listesi fotoğraf sütunu kaldırılmış halde kalır.
- Güncel `vite.config.js` yeni CSS dosyasını üretir, `layouts/app.blade.php` yükler.
- `scripts/ci/test-ui-v65-contract.sh` eski yatay filtre işaretçileri yerine yeni drawer sözleşmesini arar.

## Yazılan testler (çalıştırılmadı)

- `tests/Unit/UI/V65FilterDrawerContractTest.php`
- `tests/Unit/UI/V65FilterDraftStateTest.php`
- `tests/Unit/UI/V65ListSortingContractTest.php`
- `tests/Unit/UI/V65FilterResponsiveContractTest.php`

Mevcut ilgili UI/warehouse sözleşme testlerindeki eski yatay filtre beklentileri güncellendi. Test çalıştırma için ayrı kullanıcı onayı gerekmektedir; hiçbir testin geçtiği iddia edilmez.

## Açık kabul ve güvenlik sınırı

- PHP/Blade derleme ve Vite build henüz çalıştırılmadı.
- Livewire form state, tarih/boş/0 seçenekleri, URL'nin korunması, sayfalama ve dışa aktarma entegrasyon testleri yapılmadı.
- Tarayıcıda ESC/overlay/focus, 390/768/1024/1440/1920 piksel düzeni ve gerçek veriyle E2E yapılmadı.
- Fresh UAT web yayını gerçekleştirilmedi; PROD/PostgreSQL/Valkey/volume/demo verilerine müdahale edilmedi.
- Fresh yayını ancak ayrı onay ve yayın öncesi derleme kontrolüyle yapılmalıdır.

Bu belge statik kod değişikliği raporudur; çalışma zamanı kabul belgesi değildir.
