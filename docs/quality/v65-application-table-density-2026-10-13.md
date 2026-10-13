# V65 uygulama genelinde kompakt tablo standardı — 2026-10-13

**Baz sürüm:** `5aa418e16c752f6b0d8902f604fe2f6d54193ead`. **Kapsam:** MarsProject kullanıcı uygulaması içindeki katalog, stok, satış, alış, ambar, ithalat, üretim, fason, finans, iade, e-ticaret, raporlama, ayarlar ve detay/düzenleme sayfalarındaki HTML tablolar.

## Sorunun kaynağı

Önceki `v65 compact list-density contract` yalnız `.v65-list-page:not(.v65-products)` kapsıyordu. Bağımsız `document-workspace`, `v65-product-detail`, `contact-form`, stok detayları ve diğer form/operasyon tablolarına uygulanmıyordu. Ayrıca genel `app.css` ve önceki `v65-shell.css` kuralları dikey boşluk ve eylem butonu yüksekliğini farklı belirliyordu.

## Uygulama

- Önceki listeye özel yoğunluk bloğunun yerine **uygulama genelinde tek kompakt tablo yoğunluğu bloğu** kondu.
- Ortak `.app-shell .content table.data-table:not(.wsl-items)` kapsamı bütün Livewire tablo türlerini yakalar; `table:not(.data-table)` basit HTML tablolarını da kapsar.
- Başlık: 36 px; genel satır: nominal 42 px; hücre: 5 px dikey, 10 px yatay; satır işlem düğmesi: 30 px.
- Veri giriş alanı, fotoğraf veya çok satırlı içerik barındıran satırlar kendi doğal yüksekliğinde kalabilir; kırpma/taşma yapılmaz.
- Ürün Listesi ürün görsellerinin onaylı boyutları korunur.
- `wsl-items` sınıfındaki onaylı ambar fişi editör tablosu özel şablonunda kalır.
- Basılı ambar fişi `resources/views/printing/transport-slip.blade.php` ve PDF `resources/views/reporting/exports/pdf.blade.php`, uygulama `.app-shell .content` sınırının dışındadır; dosyaları değiştirilmedi.
- HTML tablolarda sütunlar, veri kaynakları, mutasyonlar, Livewire aksiyonları ve route'lar aynen korundu. **Veritabanı, demo kayıtları, migration ve servis yapılandırmalarında değişiklik yapılmadı.**

## Doğrulama durumu

- Statik kaynak incelemesi: GitHub'daki mevcut CSS ve tablo Blade yapıları incelendi.
- `tests/Unit/UI/V65CompactListDensityTest.php` güncellendi.
- `tests/Unit/UI/V65ApplicationWideTableDensityTest.php` hazırlandı.
- **Testler ÇALIŞTIRILMADI.** Tarayıcı ölçümü yapılmadı. Fresh ve PROD güncellenmedi.
- Sonraki kabul: 390/768/1024/1440/1920 px genişlikler, kısa/uzun içerikli satırlar, belge satırı input'ları, ürün görselleri, ambar baskısı ve tüm modül tabloları tarayıcıda kontrol edilmeli.
