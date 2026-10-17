# Alım / satım belgeleri: sonradan düzenleme güvenlik politikası (2026-10-17)

## Kapsam

Satış Teklifi, Satış Siparişi, Satış İrsaliyesi, Satış Faturası,
Alış Teklifi, Satınalma Siparişi, Alış İrsaliyesi ve Alış Faturası.

## İş kuralları

| Belge durumu | Düzenleme eylemi | Eski belge üzerindeki etki |
| --- | --- | --- |
| Yeni / kaydedilmiş taslak | **Düzenle / Kaydet** | Aynı taslak güncellenir |
| Numaralı satış teklifi | **Revizyonla Düzenle** | Yeni revizyon, eski teklif ve numara geçmişi korunur |
| Onayda, onaylı, gönderilmiş, dönüştürülmüş, kapanmış vb. (alış irsaliyesi hariç) | **Düzenleme Taslağı Aç** | Yeni bağımsız taslak; eski belge, rezervasyon ve bağlı üretim işlemleri değiştirilmez |
| Onaylı alış irsaliyesi; henüz Mal Kabul veya alt belge yok | **Düzenlemek için Aç** | Aynı belge numarası korunarak taslak duruma döner; yeniden onay gerekir |
| Mal Kabul veya alt belgeye bağlı alış irsaliyesi | **İlişkili belgeleri incele** | Hatalı ikinci miktar ayrılmasını önlemek için doğrudan düzenleme reddedilir |
| Kesinleşmiş satış irsaliyesi / satış faturası / alış faturası | **Önce Ters Kayıt, sonra Düzenleme Taslağı Aç** | Ters kayıt defter/stoğu ayrı fişle düzeltir; geçmiş belge salt okunur kalır |
| Ters kayıt fişi | Düzenleme kopyası kapalı | Yeni belge oluşturmak için ters kaydın kendisi kullanılamaz |

## Yetkiler ve tutarlılık

- Yeni düzenleme kopyası için kaynak belgenin **view, update ve create**
  izinleri gerekir. Bu izinler UI'da ve action içinde ayrı ayrı kontrol edilir.
- Dönem yazılabilir ve yeni belgenin tarihi için dönem açık olmalıdır.
- Tamamlanmış belgenin `status`, `number`, `posted_at`,
  defter/hareket kayıtları değiştirilmez.
- Alış irsaliyesi kopyalanmaz: taslak irsaliye bile siparişin kalan
  miktarını ayırdığı için, bağlı Mal Kabul / alt belge yoksa kilit altında
  eski kaydın durumu taslağa alınır. Yeniden onayda eski numara korunur.
- `edit_copy_of` kaydı kaynak belgeden yeni kopyaya tutulur;
  gerçek kaynak-sipariş ve irsaliye ilişkileri kopyalanır.
- Aynı kaynakta açık düzenleme taslağı varsa ikinci kopya yerine
  mevcut açık taslak açılır. İdempotency key ve kaynak satır kilidi
  tekrar isteğe karşı ek korumadır.
- Kopyanın stok rezervasyonu ve `cancelled_quantity` alanları
  temizlenir. Eski rezervasyonlar yeni taslağa aktarılmaz.
- Yeni taslağa ilk başta belge numarası verilmez; eski numara yeniden
  kullanılmaz. Belge türünün gerçek onay/kesinleştirme adımları geçerlidir.
- Finansal ve stok hesapları orijinal `Save*DocumentDraft`,
  onay/rezervasyon/post aksiyonlarında tekrar hesaplanır.
- Kaynak sipariş / mal kabul ilişkileri kontrol edilmeden belge
  kesinleştirilemez.

## Önemli kullanıcı davranışı

**Düzenleme kopyası eski onaylı belgenin yerine sessizce geçmez.**
Onaylı siparişten yeni taslak açmak yeni bir ticari işlem başlatabilir;
eski onayın iptalini, sevkin düzeltmesini veya üretim işinin kapanmasını
otomatik gerçekleştirmez. Bu nedenle kullanıcıdan açık onay alınır.

Doğrudan kapalı belgenin tutarını değiştirmek, önceki stok hareketlerini
silmek veya posted orijinal belgeyi yeniden taslak statüsüne almak
bilerek desteklenmez.

Numaralı satış teklifinde mevcut `CreateQuoteRevision` akışı
korunmuş; `ui_meta` alanlarının revizyonda kaybolması düzeltilmiştir.

## Uygulama dosyaları

- `app/Actions/Documents/CreateEditableDocumentCopy.php`
- `app/Actions/Purchases/ReopenPurchaseDispatchForEditing.php`
- `app/Actions/Purchases/ConfirmPurchaseDispatch.php`
- `app/Livewire/Concerns/WithPrototypeDocumentControls.php`
- İki `Base*DocumentEditor` - idempotency anahtarları
- `resources/views/components/v65/document-prototype-heading.blade.php`
- İki `document-editor.blade.php` ve ortak `document-list.blade.php`
- `app/Actions/Sales/CreateQuoteRevision.php`
- `tests/Unit/UI/MarsDocumentSafeEditingContractTest.php`

## Yayın ve kabul sınırları

Bu değişiklikler program kodu ve sözleşme testleridir. PR merge
production veya Fresh UAT Docker dağıtımı olarak kabul edilmez.
Repo kuralına göre kalite workflow'u manuel `workflow_dispatch`
üzerinden çalıştırılır; ayrı onay olmadan tetiklenmez.

İzole period PostgreSQL testleri, gerçek stok/cari ters kayıt
döngüleri ve tarayıcıdaki görünüm kabul edilmeden üretimde
finansal düzeltme işlemleri yürütülmemelidir.
