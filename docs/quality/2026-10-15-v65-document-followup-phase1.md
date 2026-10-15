# MarsProject V65 — Belge Ekranları Düzeltmeleri, 1. Paket

Tarih: 15.10.2026
Başlangıç main HEAD: `11df11ee7e897963e20c966ac5a16099b6e633ad`

Bu paket önceki belge arayüz denetimindeki **somut kaynak kod sorunlarını** düzeltir. Testler çalıştırılmamış ve PROD/Fresh UAT ortamına dağıtım yapılmamıştır.

## İşlenen işler

### 1. Blade sözdizimi (kritik)

Satış ve alış ortak belge düzenleyicilerindeki `@php($draftPreview = $this->draftTotalsPreview())` kullanımı çok satırlı `@php ... @endphp` biçimine geçirildi. Mevcut `V65BladeCompilationContractTest` kaynak kuralıyla uyum amaçlandı.

### 2. Taslak yazdırma/veri tutarlılığı (kritik)

Taslak belgede ekrana verilen `draftTotalsPreview()` kaydedilmemiş hesaplama önizlemesidir. Yazdırılan `document-readonly` daha önce yalnız kayıtlı satırlara dayanıyordu; ancak toplamlar önizlemeden gelebiliyordu.

Artık taslak belge baskısı kendi içinde **kayıtlı satırlar + kayıtlı finansal toplamlar** kullanır. Ekrandaki taslak önizlemesinin ayrı komponenti print CSS altında gizlenir. Kullanıcının kaydetmediği fiyat, miktar veya iskonto hiçbir zaman kayıtlı yazdırma toplamına karıştırılmaz. Kesinleşmiş belgelerin görüntüleme/print mantığı korunur.

### 3. Cari değişiminde fiyat ve manuel fiyatların korunması (yüksek)

Satış düzenleyicisine geçici `price_source` ayrımı eklendi:

- Yeni ürün seçimi, `PriceResolver` tarafından hesaplanan **otomatik fiyat** olarak işaretlenir.
- Kullanıcı fiyatı elle değiştirince satır **manuel** olarak işaretlenir.
- Cari değişiminde yalnız otomatik fiyat satırları yeni carinin fiyat listesiyle yeniden hesaplanır.
- Daha önce kaydedilmiş satırların fiyat kaynağı bilinmediğinden bunlar koruyucu biçimde manuel sayılır.
- `Cari Fiyatlarını Uygula` butonu, açık bir onayla manuel satırları da yeniden fiyatlandırabilir; ilgili satırdaki eski iskonto tutarı temizlenir.
- Alış fiyatları hiçbir zaman satış fiyat listesiyle değiştirilmez.

Fiyat kaynağı bu aşamada geçici Livewire editör durumudur; veritabanına yeni sütun eklenmez.

### 4. Ürün araması performansı (yüksek)

Her satırın varsayılan ilk 35 ürünü tekrar tekrar sorgulaması önlendi. Livewire bileşen örneğinde ortak varsayılan ürün seçenekleri bir kez yüklenir; aranan satıra özgü sorgular ve seçili ürünün listede kalması devam eder.

### 5. Taslak kaynak belge görünürlüğü (yüksek)

`document-sources.blade.php` komponenti oluşturuldu. Kaynak teklif/sipariş/irsaliye bağlantıları taslak düzenleme ekranında da gösterilir. Yetkili kullanıcılara ilgili kayıt bağlantısı verilir; kaynak numarası gösterme mantığı önceki read-only ekranından taşındı. Print altında ikinci kez görünmesi engellendi.

### 6. Kaynak sözleşmesi testleri

`tests/Unit/UI/V65DocumentEditorDesignTest.php` genişletildi; Blade yönergesi, kaydedilmiş print snapshot, manuel fiyat korunması, ürün sorgusu tekrarlarının azaltılması ve kaynak belge gösterimi için kaynak kontrolleri eklendi. **Testler çalıştırılmadı.**

## Sınırlar ve sıradaki işler

- [ ] PHP ve Blade derleme; Vite build.
- [ ] Testlerin çalıştırılması için ayrı yetki.
- [ ] Finansal hesap/vergiler ve manuel fiyat senaryolarında Livewire regresyon.
- [ ] Cari risk/bakiye paneli; satır iskonto tutarı ve KDV tutarı sütunları.
- [ ] Alan bazlı doğrulama ve hata odağı.
- [ ] Onay/kesinleştirme/ters kayıt akışının kontrollü görsel entegrasyonu.
- [ ] UAT ekran ve yazdırma kabulü; V65 onaylı prototiple çoklu çözünürlük karşılaştırması.
- [ ] Fresh UAT dağıtımı (ayrı onayla).

Uygulama alanı: GitHub `main` kaynak kodu. Veritabanı, Docker, PROD ve yeni belge muhasebe servisi değişikliği yapılmadı.
