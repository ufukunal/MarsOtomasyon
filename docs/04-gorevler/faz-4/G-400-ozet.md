# G-400 — Faz 4 Alış kapsam ve karar boşlukları

## Amaç

Faz 4 Alış dokümantasyonunu, Faz 3'te kurulmuş ortak `documents` / `document_lines` / `document_relations` / `contact_transactions` çekirdeğini tekrar etmeden genişletmek.

Bu belge uygulama görevi değildir; Faz 4'ün hangi parçalarının mevcut kararlarla kilitli olduğunu ve hangi iş akışı ayrıntılarının henüz kaynaklarda tanımlanmadığını gösterir.

## Önkoşul

- Faz 3 ortak ticari belge çekirdeği kullanılacaktır.
- Period DB mimarisi korunacaktır; period tablolarında `company_id` olmayacaktır.
- Posted/kesinleşmiş kayıt yerinde değiştirilmez; düzeltme ters kayıtla yapılır.
- Durum değiştiren eylemler idempotent olacaktır.
- Düzenlenebilir kayıtlarda `version` optimistic lock kullanılacaktır.
- Para aritmetiği Money/BCMath ile yapılacaktır; PHP float kullanılmayacaktır.
- Gerçek PostgreSQL kabul testleri zorunludur.

## Mevcut kararlardan kilitli Faz 4 kuralları

### Satınalma talebi ve teklif toplama

K-060 gereği satınalma talebi ve teklif toplama Faz 4 kapsamında **basit haliyle** bulunacaktır.

Kaynaklarda bu akışın ayrıntılı durum makinesi, onay zinciri, teklif karşılaştırma algoritması veya hangi aşamada siparişe dönüştüğü tanımlı değildir. Bunlar aşağıdaki karar boşluklarında ayrı tutulur.

### Para birimi ve kur

K-013 gereği döviz alışta kullanılabilir.

- Belge para birimi `documents.currency` üzerinden tutulur.
- Kur işlem anında dondurulur; `documents.exchange_rate` snapshot'tır.
- Kur farkı hesabı bu kapsamda yoktur.
- `exchange_rate > 0` CHECK'i korunur.

### Hesaplama

Faz 3'teki ortak satırlı belge hesap motoru alış belgelerinde yeniden kullanılacaktır:

- birim fiyat KDV hariç saklanır,
- satır ve belge iskontosu yüzde veya tutar girilebilir,
- iskonto KDV'den önce uygulanır,
- KDV oran grubu bazında hesaplanır,
- 2 hane half-up yuvarlama yalnız tanımlı belge/KDV grup seviyelerinde yapılır,
- `rounding_difference` saklanır.

Alış için ayrı ikinci bir hesap motoru oluşturulmaz.

### Birim dönüşümü

K-048/K-049 gereği:

- stok hareketi her zaman ürünün temel biriminde yazılır,
- belge satırındaki `quantity`, `unit_id`, `conversion_factor` ve `base_quantity` snapshot'tır,
- dönüşüm bulunamazsa işlem engellenir,
- katsayı 1 varsayılmaz.

### Maliyet

K-006 gereği tek maliyet yöntemi hareketli ortalamadır.

Alış kaynaklı maliyete giren gerçek stok girişlerinde:

- `product_costs.moving_average` güncellenir,
- `last_purchase_price` ve `last_purchase_at` güncellenir,
- çıkış/transfer hareketi ortalamayı değiştirmez.

K-007 gereği alış birim fiyatının mevcut maliyet referansından ±%25 veya üzeri sapması:

- kullanıcıya açık uyarı verir,
- period audit üretir,
- işlemi bloklamaz.

### Cari yönü

`contact_transactions` cari bakiyenin tek gerçek kaynağı olmaya devam eder.

Mevcut yön semantiği:

```
bakiye = debit - credit
```

Satış faturası `debit` ürettiği için tedarikçiye borç doğuran alış faturası aynı tabloda karşı yönde, yani `credit`, kullanacaktır.

Zorunlu fatura-ödeme settlement tablosu oluşturulmaz.

### Belge değişmezliği

- Taslak belge düzenlenebilir.
- Numara ilgili kesinleşme geçişinde üretilir.
- Posted/kesinleşmiş belge mutate edilmez.
- Ters kayıt yeni belge/hareket üretir.
- `document_date` iş tarihidir ve dönem kilidi bunu kullanır.
- Post-write doğrulama transaction içinde yapılır.

## Faz 3'ten yeniden kullanılacak yapılar

Aşağıdaki tablolar Faz 4 için kopyalanmayacaktır:

- `documents`
- `document_lines`
- `document_relations`
- `contact_transactions`
- `stock_movements`
- `stock_balances`
- `product_costs`
- `number_series`
- period `activity_log`

Yeni tablo yalnız mevcut çekirdeğin karşılayamadığı, açıkça gerekli bir Faz 4 verisi ortaya çıkarsa eklenir.

## Faz 4 dokümantasyon çıktıları

Karar boşlukları kapatıldıktan sonra şu dört katman yazılacaktır:

1. Gerekliyse alışa özel veri modeli ekleri.
2. Alış belge yaşam döngüsü, posting etkileri, maliyet ve kısmi işlem iş kuralları.
3. v65 genel UI diliyle uyumlu alış ekran dokümanları; v65'te hazır satınalma ekranı olmadığı için iş akışı kararı olmadan ekran davranışı uydurulmayacaktır.
4. Standalone `G-401...` görevleri ve faz sonu gerçek PostgreSQL test görevi.

## [KARAR GEREKİYOR]

Mevcut karar günlüğü aşağıdaki ürün davranışlarını belirlemiyor. Bunlar kilitlenmeden görevlerde varsayım yapılmayacaktır.

### A-009 — KAPANDI: Esnek Faz 4 belge zinciri

Kanonik belge ailesi:

- satınalma talebi
- tedarikçi teklifi / teklif toplama
- satınalma siparişi
- mal kabul / alış irsaliyesi
- alış faturası

Ara adımlar zorunlu değildir. Kullanıcı ihtiyaca göre doğrudan satınalma siparişi, doğrudan mal kabul/alış irsaliyesi veya doğrudan alış faturası oluşturabilir. Belge ilişkileri yalnız gerçekten kullanılan zinciri izler; sistem eksik ara belge üretmez.

### A-010 — KAPANDI: Stok ve cari etki yalnız alış faturasında

- Mal kabul/alış irsaliyesi operasyon kaydıdır; stok hareketi üretmez.
- Mal kabul/alış irsaliyesi tedarikçi cari hareketi üretmez.
- Alış faturası post edildiğinde stok girişi ve tedarikçi cari etkisi birlikte oluşur.
- Mal kabul kaynaklı alış faturasında stok, fatura posting anında ilk kez artar; ikinci stok etkisi diye ayrı bir aşama yoktur.
- Doğrudan alış faturası da aynı şekilde stok + tedarikçi cari etkisini birlikte üretir.
- Hareketli ortalama maliyet güncellemesi alış faturası posting transaction'ındaki gerçek stok girişiyle aynı noktada yapılır.

### A-011 — KAPANDI: Esnek kısmi alış akışı

- Satınalma siparişi kısmi teslim alınabilir.
- Kalan miktar açık kalabilir veya kullanıcı tarafından iptal edilebilir.
- İptal edilen miktar daha sonra teslim/fatura edilemez.
- Bir mal kabul birden fazla alış faturasına bölünebilir.
- Aynı tedarikçiye ait, aynı para birimi ve uyumlu alış koşullarındaki birden fazla mal kabul tek alış faturasında birleşebilir.
- Kısmi miktarlar kaynak satır ilişkileri üzerinden izlenir; ayrı ikinci bir delivered/invoiced gerçek kaynağı oluşturulmaz.
- Yarış koşulunda kaynak satır kalan miktarı transaction içinde yeniden okunur/kilitlenir.

### A-012 — KAPANDI: Tedarikçi ödeme akışı Faz 5'te

- Faz 4 alış faturası tedarikçi borcunu `contact_transactions.credit` hareketiyle oluşturur.
- Faz 4'te kasa/banka ödeme posting eylemi yoktur.
- Nakit, banka, çek/senet ve diğer tedarikçi ödeme akışları Faz 5 Kasa/Banka/Çek-Senet kapsamında ele alınır.
- Faz 4 ekranlarında borç görüntülenebilir; ödeme işlemi başlatılamaz.

### A-013 — KAPANDI: Belge + satır bazlı teklif karşılaştırma

- Bir satınalma talebine birden fazla tedarikçi teklifi bağlanabilir.
- Kullanıcı belge bazında tek bir tedarikçi teklifini seçebilir.
- Kullanıcı satır bazında farklı ürünleri farklı tedarikçilerden seçebilir.
- Satır bazlı seçimde oluşacak satınalma siparişleri seçilen tedarikçilere göre ayrıştırılır.
- Sistem otomatik en ucuz teklif, puanlama veya kazanan seçimi yapmaz.
- Karar kullanıcı tarafından verilir ve period audit'e seçim özeti yazılır.
- Seçim/onay yetkisi ayrı açık karar olarak netleştirilecektir.

## Faz bitiş ölçütü

Faz 4 tamamlanmış sayılmadan:

- tüm alış belge tipleri için etki matrisi açık olmalı,
- maliyet güncelleme noktası tek ve yarış koşuluna dayanıklı olmalı,
- kısmi akışlarda kaynak satır toplamları aşılmamalı,
- cari hareket yönü ve ters kayıtlar doğrulanmalı,
- döviz kur snapshot testleri bulunmalı,
- ±%25 alış fiyatı uyarısı + audit testi bulunmalı,
- idempotency ve optimistic lock testleri bulunmalı,
- tüm kabul testleri gerçek PostgreSQL üzerinde geçmelidir.
