# MarsProject V65 — Teklif, Sipariş, İrsaliye ve Fatura Ekranları Düzeltme Kaydı

Tarih: 2026-10-14
Kapsam: Satış Teklifi, Satış Siparişi, Satış İrsaliyesi, Satış Faturası, Satınalma Siparişi, Mal Kabul, Alış Faturası

## Hedef

Onaylanan V65 ortak görsel yaklaşımı, satış/alış belge düzenleyicilerine taşımak. Kullanıcının gönderdiği satış faturası ekranındaki çift başlık, dağınık KDV düğmeleri, satır net tutar yokluğu, toplam paneli eksikliği, ham durum etiketi ve ayrı duran kesinleştirme butonları temel girdidir.

## Tamamlanan kaynak kod değişiklikleri

- \`resources/views/livewire/sales/document-editor.blade.php\`
  - Belge numarası + Türkçe durum etiketi + yetkili listeye dönüş + kaydedilmiş belgeyi yazdırma.
  - Teklife özgü geçerlilik tarihi yalnız teklif editöründe; sevk belgesinde ilgisiz vade kontrolü gizli.
  - Cari kod/unvan araması, satır ürün kod/ad/barkod araması.
  - Toplu KDV kontrolleri tek satırda ve satır ekle / Kaydet ortak footer içinde.
  - Hizmet açıklaması, ürün satırı açıklaması, sıra numarası ve satır net toplamı.
  - Taslak olmayan belgede tüm kaydedilmiş satırlar salt-okunur gösterilir.
  - Tüm belge durumlarında kaydedilmiş finansal toplamlar veya kaydedilmemiş taslak hesap önizlemesi.
  - Risk/rezervasyon/sevkiyat/orijinal işlem metotları korunur.
  - Kesinleştirme, sipariş onayı ve ters kayıt kullanıcıya açık doğrulama metni ve Livewire loading kullanır.

- \`resources/views/livewire/purchases/document-editor.blade.php\`
  - Aynı ortak editör, satır tablosu, durum, hata ve hesap özeti.
  - Mal kabulün fiyat ve KDV alanları gizlenerek kaydedilmiş miktar, birim ve net tutarlar gösterilir.
  - Üçlü eşleştirme tablosu \`data-table\` ailesine taşınır.
  - Alış bedelini satış fiyat listesinden otomatik atamaz; kullanıcı tarafından girilir.

- \`app/Livewire/Sales/BaseSalesDocumentEditor.php\`, \`app/Livewire/Purchases/BasePurchaseDocumentEditor.php\`
  - Satır indeksinden bağımsız \`row_key\` ile Livewire satır kimliği.
  - Cari arama ve satır-başına ürün/barkod arama; varsayılan sorgu 35 kayıt, mevcut seçim listede korunur.
  - Seçili ürünün birimi, KDV'si ve açıklaması güncellenir; satış tarafında fiyat mevcut \`PriceResolver\` üzerinden alınır.
  - Miktar, fiyat veya iskonto oranı değiştiğinde eski ve artık tutarsız iskonto tutarı temizlenir.
  - \`draftTotalsPreview()\` mevcut \`CalculateDocumentTotals\` sınıfını kullanır; veri tabanına yazmaz. Geçersiz/eksik alanlarda tahmin üretmez.
  - \`documentDirty\` ve \`requireSavedDraft()\`: kaydedilmemiş taslak değişikliğiyle durum geçişini engeller.
  - Belge ilişkileri ve birimler topluca yüklenir; kayıt servislerinin muhasebe/period işlemleri aynen kalır.

- Yeni ortak bileşenler:
  - \`resources/views/components/v65/document-heading.blade.php\`
  - \`resources/views/components/v65/document-summary.blade.php\`
  - \`resources/views/components/v65/document-readonly.blade.php\`
- \`resources/css/v65-shell.css\`: editör grid, 38 px girişler, sayısal hizalar, arama, buton çubuğu, mobil ve yazdırma düzeni.
- \`resources/views/layouts/app.blade.php\`: belge editörlerinde mükerrer jenerik Yazdır / PDF ve Geri kaldırıldı; belgelerin kendi gerçek eylemleri kullanılıyor.
- \`resources/views/livewire/sales/document-list.blade.php\`: semantik durum renkleri, standart boş liste.
- \`app/Livewire/Concerns/WithV65DocumentList.php\`: onay bekliyor / gönderildi durumlarının Türkçesi.

## Özellikle korunan davranışlar

- Veri yazma ve muhasebeleştirme servisleri (\`SaveSalesDocumentDraft\`, \`SavePurchaseDocumentDraft\`, \`Post*\`, \`ReverseDocument\`) değiştirilmedi.
- Sipariş rezervasyon, miktar seçimi, sevk, fatura ve proforma dönüşüm metotları korundu.
- İrsaliye/sipariş kaynak belge bağlantıları rol/izin kontrolüyle gösterilir.
- Kullanıcının onaylı ambar fişi şablonlarına, PROD sunucusuna, Docker'a veya DB şemasına dokunulmadı.

## Sınırlamalar

- Taslak hesap özeti; rakamsal alanlar geçerliyse aynı backend hesaplayıcısından **önizleme** sağlar. Kesin tutarlar kayıtta yeniden hesaplanır.
- Alış ürün fiyatı manuel girilir; satışta \`PriceResolver\` kullanılır. Ekran kaydı başarıyla çalışmadan toplamın kalıcı olduğu iddia edilmez.
- Ekranın doğrudan tarayıcı ve canlı veriyle test edilmesi henüz yapılmadı.
- Yazdırma, kaydedilmiş belge verisini kullanır; çıktı düzeninin tarayıcıda ayrıca doğrulanması gerekir.

## Tamamlama kontrol listesi

- [x] Ortak editör kaynaklarını yapılandır.
- [x] Taslak/okuma modu ayrımını oluştur.
- [x] Tarihlerin belge türüne göre gösterimini düzelt.
- [x] Satır fiyatı, açıklaması, birimi, KDV'si ve hesaplanan neti göster.
- [x] Anlık taslak hesap önizlemesi / kayıtlı kesin toplam ayırımı.
- [x] Kaydedilmemiş değişiklikte kritik durum geçişlerini engelle.
- [x] Cari/ürün araması ve belge kaynak bağlantıları.
- [x] Kaynak kod kalite sözleşmesi testini yaz: \`tests/Unit/UI/V65DocumentEditorDesignTest.php\`.
- [ ] Blade/PHP derleme.
- [ ] Otomatik Livewire / regresyon testleri.
- [ ] UAT gerçek şirket/dönem verisiyle iş akışı testi.
- [ ] Onaylı HTML V65 ile 390/768/1024/1440/1920 px görsel karşılaştırma.
- [ ] Yazdırma önizlemesinin kullanıcı kabulü.

**Doğrulama:** Bu kayıt GitHub'daki kod değişikliklerini açıklar. Testler ve UAT çalıştırılmadı; canlı ortama deploy edilmedi.
