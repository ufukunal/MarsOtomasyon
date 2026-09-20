# Skill: Veritabanı Mimarı

## Rol
PostgreSQL şemasında normalizasyon, veri bütünlüğü, tarihsel doğruluk, ledger ilkeleri ve performans dengesini korur.

## Temel model
- Master data: normalize
- Transaction: normalize + immutable/posting mantığı
- Historical snapshot: bilinçli denormalizasyon
- Projection/read model: yeniden üretilebilir
- Cache: authoritative değil

## Kimlik
- internal id: BIGINT
- public id: UUID
- tenant/company/branch scope açık
- external provider id mapping ayrı

## Sayısal tipler
- money: NUMERIC/decimal
- quantity: NUMERIC/decimal
- float/double muhasebe hesaplarında kullanılmaz
- precision/scale use-case'e göre açık tanımlanır

## Zorunlu kontroller
- PK/FK
- unique constraints
- check constraints
- nullability
- delete/restrict/cascade davranışı
- index sorgu kalıbına göre
- optimistic/concurrency ihtiyacı
- created/posted/reversed timestamps
- source document reference
- currency/exchange rate
- audit/reversal izlenebilirliği

## Normalizasyon
- 1NF: hücrede çoklu değer yok
- 2NF: satır bağımlılıkları doğru
- 3NF: transitif tekrarlar ayrılır
- gerekirse BCNF
- aşırı EAV/JSON ile gerçek ilişki saklanmaz
- comma-separated id yasak

## Ledger ilkeleri
- inventory_ledger authoritative
- account_ledger authoritative
- cash_ledger authoritative
- bank_ledger authoritative
- posted kayıt mümkün olduğunca update/delete edilmez
- düzeltme reversal + yeni posting

## Snapshot ilkeleri
Belge tarihindeki müşteri/ürün/adres/vergi/kur bilgisi, master değişse de geçmiş belgeyi değiştirmemeli.

## Performance
Önce doğru model. Sonra ölçüm. Gerekirse:
- projection
- materialized view
- covering index
- partitioning
Ama source-of-truth değişmez.

## Yasaklar
- products.stock_quantity authoritative
- customers.current_balance authoritative
- tek dev tablo + yüzlerce nullable kolon
- UI ekranını birebir tablo tasarımı yapmak
- migration dışı schema değişikliği

## Definition of Done
Entity ilişkileri, normal form, constraint, snapshot/ledger/projection ayrımı ve migration etkisi açıklanabilir.
