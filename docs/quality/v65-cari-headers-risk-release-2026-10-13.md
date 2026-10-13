# MarsProject V65 — Cari Listesi aksiyonları ve risk kontrolü

Tarih: 2026-10-13

## Amaç
Kullanıcıdan gelen iki bekleyen Cari Listesi yerleşim kararını uygulamak; TRY satış faturası kesinleştirmesinde cari risk limiti uyarısı sağlamak ve risk filtresini finansal kurallarla uyumlu tutmak.

## Kullanıcı arayüzü

- Cari Listesi içeriğindeki Filtreler düğmesi özet başlık kartının sağına taşındı.
- Aynı ortak filtre drawer partial'ı kullanıldı; diğer listelerdeki konum korunuyor.
- Üst aksiyonlar Yeni Cari → Yazdır → Dışa Aktar şeklinde düzenlendi.
- Dışa Aktar tek menüde CSV ve Excel işlemlerini içerir.
- Cari Listesi için ayrıca gösterilen genel Yazdır / PDF ve Geri kaldırıldı; diğer ekranlar değişmez.
- Özet kartları, tablo bağlantıları, seçimler, sayfalama ve Cari Detay düzenleme aksiyonlarına dokunulmadı.

## Finansal kural

PostSalesInvoice, satış faturası TRY ise ve cari risk limiti aşılacaksa, işlemden önce kullanıcıdan açık kabul ister. Satış faturası oluşturulması ile kesinleştirme farklı işlemlerdir. Tekrar oynatılan idempotent kesinleştirme işlemleri için zaten `posted` durumundaki faturalarda ikinci risk uyarısı uygulanmaz.

- Kaynak TRY cari neti: `contact_transactions`, debit eksi credit.
- Pozitif net: şirketin müşteriden alacağı; **cari açısından Borçlu/kırmızı**, limit yüzdesine girer.
- Negatif net: şirketin cariye borcu; **cari açısından Alacaklı/yeşil**, sırf mutlak değer alınıp müşteri riski olarak sayılmaz.
- Risk limiti 0: limitsiz; risk yüzde alanı `—`, limit aşımı filtresinde gösterilmez.
- Döviz tutarları TRY ile dönüşüm yapılmadan toplanmaz. Döviz faturasında ayrı risk değerlendirmesi gerekir.
- Risk kontrolü mevcut sipariş onaylama algoritmasının bilinen alacak projeksiyonunu kullanır. Portföy kıymet riskini eksiksiz hesapladığı iddia edilmez.

## Dosyalar

- `resources/views/livewire/components/data-table.blade.php`
- `resources/views/livewire/components/partials/v65-table-filter-controls.blade.php` (yeni)
- `resources/views/layouts/app.blade.php`
- `resources/css/v65-shell.css`
- `app/Livewire/Pages/Contacts/ContactList.php`
- `app/Actions/Sales/PostSalesInvoice.php`
- `app/Livewire/Sales/SalesInvoiceEditor.php`
- `resources/views/livewire/sales/document-editor.blade.php`
- Yeni entegrasyon ve mevcut UI testleri
- `scripts/ci/test-ui-v65-contract.sh` ortak partial uyarlaması

## Veritabanı doğrulamasına açık kalan konu

UAT ekranında `CR0000085` için 13.774,54 ₺ Borçlu / %0 gösterilmiş. Bu yalnız görüntüden hareketle yanlış yüzde kanıtı değildir: net negatifse borç/alacak yönü veya belge kesinleştirme durumu kontrol edilmelidir. Bu kaydı doğrulamak için UAT `mars_uat_demo_2026` dönemi veritabanında yalnız `SELECT` ile kayıtlar incelenmelidir. SQL hata düzeltmesi amacıyla otomatik UPDATE/DELETE uygulanmaz.

## Test ve yayın kanıtı

Entegrasyon testi: `tests/Integration/SalesInvoiceRiskAcceptanceTest.php` — 1.000 TRY limit / 13.000 TRY fatura; risksiz kesinleştirme reddi, açık onayla post, debit hareket, %1.300 gösterimi ve idempotent tekrar.

UI sözleşmeleri yeni partial, başlık butonu, mobil stile ve filtre konumuna uyumlu hale getirildi.

**Durum:** Kaynak kod GitHub `main` dalına işlendi. PHP/Blade/Vite derleme, PostgreSQL entegrasyon testleri, UAT tarayıcı görüntüsü ve yayın bu konuşmada çalıştırılmadı. Fresh PostgreSQL/Valkey/veriler/volumeler değiştirilmedi. Ortam yayını mevcut `ops/uat-fresh-web-update.sh <SHA>` korumalı betiğiyle yapılmalıdır.
