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

### A-015 — KAPANDI: Tüm hesap türleri arasında aynı para birimli virman

- Kasa → Kasa desteklenir.
- Kasa → Banka desteklenir.
- Banka → Kasa desteklenir.
- Banka → Banka desteklenir.
- Kaynak ve hedef hesap currency değerleri aynı olmalıdır.
- Farklı para birimleri arasında virman yoktur.
- Virman kur dönüşümü veya kur farkı üretmez.
- Kaynak ve hedef aynı hesap olamaz.
- Virman iki finans hareketini tek transaction/idempotency zincirinde üretir.

### A-016 — KAPANDI: Genel ödeme formu + fatura ekranı kısayolu

- Ana işlem genel Tedarikçi Ödeme formundan yapılır.
- Alış faturası ekranında `Ödeme Yap` kısayolu bulunur.
- Fatura üzerinden başlatılırsa kaynak fatura ilişkisi bilgi amaçlı tutulur.
- Settlement zorunlu değildir.
- Kısmi ödeme serbesttir.
- Bir ödeme belirli faturaya bağlı olmak zorunda değildir.

### A-017 — KAPANDI: Toplam fiili bakiye + fark düzeltme hareketi

- Kullanıcı kasadaki gerçek toplam tutarı girer.
- Kupür bazlı sayım yoktur.
- Sistem beklenen bakiye ile fiili bakiye farkını gösterir.
- Fark blok değildir.
- Kullanıcı gerekçeyle onaylarsa ayrı kasa sayım farkı finans hareketi oluşturulur.
- Geçmiş hareketler mutate edilmez.

### A-018 — KAPANDI: İlk sürümde manuel banka mutabakatı

- Banka ekstresi dosya importu yoktur.
- Otomatik eşleştirme yoktur.
- Mevcut banka hareketleri kullanıcı tarafından manuel olarak `mutabık` / `mutabık değil` durumuyla işaretlenir.
- Mutabakat hareketi yeni finans hareketi üretmez; mevcut hareket üzerinde mutabakat metadata'sı/audit tutulur.
- Dosya formatı kararı Faz 5 ilk sürümü için gereksizdir.

### A-019 — KAPANDI: Tam kontrollü çek/senet yaşam döngüsü

Alınan kıymet:

- alındı,
- portföyde,
- ciro edildi **veya** tahsile verildi,
- tahsil edildi,
- karşılıksız / geri döndü.

Verilen kıymet:

- verildi,
- ödeme bekliyor,
- ödendi,
- geri döndü / iptal edildi.

K-082 korunur: ilk teslim cari etkisini üretir; tahsil/ödeme ikinci kez cari etkilemez; karşılıksız/geri dönüş ters cari hareket üretir; ciro ilk cariyi ikinci kez etkilemeden karşı cariyi etkiler.

### A-020 — KAPANDI: Geniş veri seti + banka operasyon alanları

Temel alanlar:

- tür: check | promissory_note,
- yön: received | issued,
- belge/seri numarası,
- tutar,
- vade,
- para birimi,
- ilk cari,
- keşideci / düzenleyen,
- banka ve şube bilgisi,
- açıklama,
- mevcut durum,
- actor snapshot.

Banka operasyon alanları:

- banka hesap bağlantısı,
- tahsil/ödeme referansı,
- banka teslim tarihi,
- protesto/karşılıksız detayları,
- operasyon metadata.

Ciroda karşı cari zorunludur; ciro tarihi ve açıklama tutulur. Kıymetin önceki kimliği/history zinciri korunur.

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
