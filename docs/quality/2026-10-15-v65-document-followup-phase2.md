# MarsProject V65 — Belge Arayüzleri 2. Düzeltme Paketi

Tarih: 15.10.2026
Başlangıç `main`: `730f0ae0fcda8ef11a64bb6139f9b7149375fed3`

## Yapılan kaynak kod işleri

### Bölüm 1 — Finansal görünürlük

- Satış faturası için `SalesInvoiceEditor::contactRiskOverview()` eklendi. Sadece `contacts.view` yetkisi olan kullanıcılar görüntüleyebilir.
- Risk bilgisi **yalnız TRY bazlı** olup `Contact::balance()`, cari risk limiti ve fatura bilgisine dayanır. Teminat ve diğer risk unsurlarını kapsadığı iddia edilmez. Kesinleşmiş fatura mevcut bakiyeye tekrar eklenmez.
- `x-v65.contact-risk-overview` Cari borçlu durumunu kırmızı, cari alacaklı durumunu yeşil gösterir. Bilinen risk, limit ve kalan limit bilgisi bulunur.
- `x-v65.document-line-amounts` satış ve alış editörlerinde satır iskonto tutarını ve bilgi amaçlı KDV tutarını gösterir. Satır KDV rakamı **belge genel iskontosu öncesidir**; muhasebe açısından geçerli toplam vergi mevcut `CalculateDocumentTotals` sonucundan alınır. Belgede bu sınırlama açıkça yazılır.
- `document-summary.blade.php` tarafından gösterilen kayıtlı tutarların hesaplama/para birimi davranışı korunur.

### Bölüm 2 — İşlem güvenliği ve erişilebilirlik

- `x-v65.document-confirm` ile satış onayı, teklif onayı, alış onayı, kesinleştirme ve ters kayıt için kullanıcıya kayıtlı belge numarası, cari ve toplam gösterilir.
- Modal onay tuşu ilgili mevcut Livewire metodunu çağırır. `wire:loading.attr` ile tekrar tıklama engellenir. Sunucudaki yetki, dönem, risk ve kayıt kontrolleri korunur.
- Form kaydından önce sunucu tarafında tarih, oran, miktar, fiyat ve döviz kurunun temel geçerlilik denetimleri eklendi; alış belgesinde tedarikçi zorunluluğu uygulanır.
- `x-v65.input-error` ile alan bazlı hata metinleri eklendi; Livewire form hatası sonrasında ilk hatalı alana odaklanmak için JS davranışı tanımlandı.

### Bölüm 3 — Ortak görsel standart

- Cari ve ürün aramasında boş sonuç ve yüklenme durumu metinleri eklendi. Arama doğal `input type=search` ve `select` klavye davranışını kullanır; ilk 35 sonuç gösterilir.
- Satır tablolarında iskonto tutarı ve bilgi amaçlı KDV sütunu; küçük ekranlarda yatay kaydırma açıklaması ve sabit satır numarası eklendi.
- Satış rezervasyon, sevkiyat/fatura, proforma ve alış gönderim butonları ortak V65 butonlarına geçirildi.
- V65 onay modallarında belge bilgileri sağ/sol hizalı okunabilir özet olarak gösterilir.

### Hazırlanan test kodu

`tests/Unit/UI/V65DocumentEditorPhase2Test.php`: kaynak sözleşmesi kontrolleri hazırlanmıştır.

**Çalıştırılmadı**; test yürütmesi için önceki ayrı onay protokolü geçerlidir.

## Bilinen sınırlar ve kalan kabul işleri

- [ ] PHP/Blade/Vite build, testleri çalıştırma ve tarayıcı/Livewire regresyon doğrulaması.
- [ ] KDV belge iskontosu satırlara tam paylaştırılmış tutar isteniyorsa hesaplama servisinin resmi dağıtım kuralı üzerinde ayrıca karar ve doğrulama (şu anda sütun bilgi amaçlıdır).
- [ ] Gerçek şirket/dönem verisiyle risk, borç/alacak ve döviz testleri.
- [ ] Onaylı V65 HTML ekranlarıyla görsel birebir kıyas.
- [ ] Profesyonel yasal fatura, teklif, sipariş ve irsaliye PDF/çıktı şablonlarının onayı (mevcut Yazdır/PDF tarayıcı baskısıdır).
- [ ] Fresh UAT dağıtımı ve kullanıcı kabulü.

PROD, Docker, PostgreSQL, Valkey ve onaylı ambar fişi çıktısı değiştirilmemiştir. Mevcut muhasebe ve belge kayıt servisleri değiştirilmemiştir.
