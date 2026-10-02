# G-500 — Faz 5 Kasa/Banka/Çek-Senet kapsam ve karar boşlukları

## Amaç

Faz 5 Kasa/Banka/Çek-Senet dokümantasyonunu, Faz 3'te K-075 ile erkenden kurulan minimum `cash_accounts`, `bank_accounts`, `cash_movements`, `bank_movements` ve mevcut `contact_transactions` çekirdeğini tekrar etmeden genişletmek.

Bu belge uygulama görevi değildir. Kaynaklarda kilitli davranışları ve kullanıcı kararı gerektiren Faz 5 boşluklarını ayırır.

## Önkoşul

- Period DB mimarisi korunur; period tablolarında `company_id` yoktur.
- Cari bakiye K-062 gereği yalnız `contact_transactions` toplamıdır.
- Tahsilat için minimum kasa/banka Faz 3'te vardır.
- Tedarikçi ödeme akışı K-089 gereği Faz 5'tedir.
- Çek/senet temel cari etkileri K-082 ile kilitlidir.
- Risk projeksiyonunda portföydeki henüz tahsil edilmemiş kıymetler K-078 gereği ayrıca dikkate alınır.
- Posted/kesinleşmiş finans hareketi yerinde değiştirilmez; ters kayıt/yeni hareket kullanılır.
- Money/BCMath, idempotency, document_date, period lock, actor snapshot ve gerçek PostgreSQL test kuralları devam eder.

## Faz 3'ten yeniden kullanılacak yapılar

- `cash_accounts`
- `bank_accounts`
- `cash_movements`
- `bank_movements`
- `contact_transactions`
- `documents` / `document_relations`
- period `activity_log`
- idempotency / number series / posting period altyapısı

Yeni tablo ancak Faz 5 iş davranışı mevcut çekirdekle temsil edilemiyorsa eklenir.

## Kaynaklarda kilitli Faz 5 kuralları

### Kasa / banka

K-075:

- Faz 3 minimum hesap CRUD + tahsilat hareketi altyapısını sağlar.
- Virman, banka ekstresi, mutabakat ve kasa sayımı Faz 5 kapsamındadır.

K-089:

- Tedarikçi ödeme akışı Faz 5 kapsamındadır.

K-062:

- Ödeme/tahsilat için fatura settlement zorunlu değildir.
- Cari gerçek bakiye hareket toplamıdır.

### Çek / senet

K-082:

- çek/senet teslim alındığında veya verildiğinde cari etkisi oluşur,
- tahsil/ödeme aşamasında cari ikinci kez etkilenmez,
- karşılıksız/geri dönüş ters cari hareket üretir,
- ciro, kıymeti veren müşteriyi ikinci kez etkilemeden karşı taraf carisini etkiler.

K-078:

- portföydeki henüz tahsil edilmemiş çek/senet tutarı ticari risk projeksiyonunda ayrıca gösterilir.

## v65 durumu

Onaylı v65 prototipinde Faz 5'e ait kasa/banka/çek/senet ekran davranışı doğrudan kaynak olarak bulunmuyor. Bu nedenle Faz 5 ekranları yalnız kilitlenen iş kararları ve mevcut genel UI dili üzerinden yazılacaktır; prototipte varmış gibi route, alan veya workflow uydurulmayacaktır.

## [KARAR GEREKİYOR]

### A-015 — Virman kapsamı ve para birimi

Kaynaklar virmanın Faz 5'te olacağını söyler; şu ayrıntılar kilitli değildir:

- kasa → kasa,
- kasa → banka,
- banka → kasa,
- banka → banka

kombinasyonlarının hangilerinin destekleneceği,
- farklı para birimli hesaplar arasında virman olup olmayacağı,
- varsa kur/kur farkı davranışı.

K-013 dövizi yalnız ithalat ve alışla sınırlar; bu nedenle finans hesapları arası döviz dönüşümü ayrıca karar verilmeden varsayılmayacaktır.

### A-016 — Tedarikçi ödeme girişi

K-089 ödemenin Faz 5'te olduğunu, K-062 settlement'ın zorunlu olmadığını kilitler. Şunlar net değildir:

- ödeme ayrı genel formdan mı yapılacak,
- alış faturası ekranından ödeme başlatma kısayolu olacak mı,
- kaynak alış faturası seçilirse ilişkinin yalnız bilgi amaçlı mı tutulacağı,
- kısmi ödeme kullanıcı arayüzü.

### A-017 — Kasa sayımı ve fark

Kasa sayımı Faz 5 kapsamındadır; ancak:

- yalnız toplam fiili bakiye mi girilecek,
- kupür bazlı sayım gerekip gerekmediği,
- sistem bakiyesi ile farkta blok mu, uyarı mı, düzeltme hareketi mi üretileceği

tanımlı değildir.

### A-018 — Banka ekstresi ve mutabakat

Kaynaklar ekstre/mutabakatı Faz 5'e bırakır fakat:

- ilk sürümde dosya importu olup olmayacağı,
- dosya formatı,
- otomatik eşleştirme yapılıp yapılmayacağı,
- mutabakatın hareket bazında hangi durumları taşıyacağı,
- eşleşmeyen satırdan yeni hareket oluşturulup oluşturulamayacağı

tanımlı değildir.

### A-019 — Çek/senet kanonik yaşam döngüsü

K-082 etkileri tanımlar fakat durum makinesini tanımlamaz. Ayrı ayrı netleştirilmesi gerekenler:

- alınan çek/senet,
- verilen çek/senet,
- portföy,
- ciro,
- tahsil/ödeme,
- karşılıksız/geri dönüş,
- iptal/ters kayıt

durumlarının kanonik sırası ve hangi geçişlerin destekleneceği.

### A-020 — Çek/senet veri alanları ve ciro hedefi

Kaynaklarda tam veri modeli yoktur. En az şu alanların zorunlu/opsiyonel kapsamı karara bağlanmalıdır:

- çek/senet türü,
- belge/seri numarası,
- keşideci/veren,
- banka/şube bilgisi,
- vade,
- tutar,
- para birimi,
- ilk cari,
- ciro edilen karşı cari,
- açıklama.

Ciroda K-082 gereği ilk cari ikinci kez etkilenmeyecektir; fakat ciro hedefinin hangi contact rolüyle tutulacağı ve seçim kuralları ayrıca kilitlenmelidir.

## Faz 5 dokümantasyon çıktıları

A-015…A-020 kapandıktan sonra:

1. gerekiyorsa Faz 5 veri modeli ekleri,
2. kasa/banka hareketleri, virman, ödeme, sayım, ekstre/mutabakat ve securities iş kuralları,
3. genel UI diliyle Faz 5 ekran belgeleri,
4. standalone `G-501...` görevleri,
5. gerçek PostgreSQL bütünleşik/concurrency/integrity test görevi

yazılacaktır.

## Faz bitiş ölçütü

Faz 5 dokümantasyonu tamamlanmış sayılmadan:

- kasa/banka etki matrisi,
- tedarikçi ödeme cari yönü ve finans hareketi,
- virman çift taraflı hareket bütünlüğü,
- kasa sayımı fark davranışı,
- ekstre/mutabakat gerçek kaynağı,
- çek/senet lifecycle + cari etki matrisi,
- riskte hangi securities durumlarının sayıldığı,
- ters kayıt,
- idempotency / optimistic lock / concurrency,
- integrity kontrolleri,
- gerçek PostgreSQL kabul testleri

açıkça tanımlanmış olmalıdır.
