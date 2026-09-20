# Skill: Veritabanı Mimarı

## 1. Misyon
PostgreSQL veri modelini doğru normalize edilmiş, tarihsel olarak güvenilir, constraint'lerle korunan ve performans açısından sürdürülebilir tutar.

## 2. Source-of-truth kuralları
Authoritative:
- inventory_ledger
- account_ledger
- cash_ledger
- bank_ledger
- ana document/transaction tabloları

Authoritative olmayan:
- cache
- projection
- materialized view
- dashboard aggregate
- UI state

## 3. Normalizasyon hedefi
OLTP için varsayılan hedef 3NF'tir.

### 1NF
- hücrede liste yok
- comma-separated ID yok
- tekrar eden kolon grubu yok

### 2NF
- ilişki/junction tablolarında alan anahtarın tamamına bağlı

### 3NF
- transitif master tekrarları ayrılır
- category_name gibi türetilebilir alan gereksiz tutulmaz

### Bilinçli denormalizasyon
Yalnız:
- historical snapshot
- projection/read model
- performans ölçümle kanıtlandığında
kabul edilir.

## 4. Kimlik stratejisi
- internal PK: BIGINT
- public_id: UUID
- external provider ID ayrı mapping
- doğal anahtar varsa unique constraint olabilir ama PK olmak zorunda değil

## 5. Sayısal veri
- money: NUMERIC uygun precision/scale
- quantity: NUMERIC uygun precision/scale
- exchange rate daha yüksek scale gerekebilir
- float/double yasak
- rounding kuralı uygulama/domain ile uyumlu olmalı

## 6. Constraint checklist
Her tabloda değerlendir:
- primary key
- foreign key
- unique
- check
- not null
- default
- delete behavior
- status validity
- positive/non-negative quantity
- date ordering
- source uniqueness

Uygulama validation'ı DB constraint'in yerine geçmez.

## 7. Ledger tasarımı
- append ağırlıklı
- posted movement update/delete edilmez
- source_type/source_id/source_line_id izlenebilir
- reversal_of_id veya eşdeğer bağ
- posting date
- company/branch/warehouse/account scope
- currency ve amount ayrımı
- audit actor

## 8. Snapshot tasarımı
Belge tarihindeki:
- cari unvanı
- vergi bilgisi
- adres
- ürün kod/ad
- birim
- vergi oranı
- kur
master değişse de eski belgeyi değiştirmemelidir.

Snapshot "normalizasyon hatası" olarak görülmez; tarihsel doğruluk kararıdır.

## 9. Document engine
Ortak alanlar commercial_documents / lines gibi çekirdekte olabilir.
Belgeye özel alanlar:
- subtype/extension tablo
- gerekirse ayrı bounded context tablosu
ile tutulur.
Tek dev nullable tablo yasaktır.

## 10. Multi-company
Her entity için gerçekten gerekli scope belirlenir:
- tenant
- company
- branch
- warehouse
Scope belirsiz bırakılmaz.

## 11. Index stratejisi
Index eklemeden:
- sorgu paterni
- cardinality
- filter/order
- join
- write cost
düşünülür.

Her FK otomatik olarak doğru index değildir; gerçek erişim paterni incelenir.

## 12. Migration kuralları
- yalnız migration
- destructive değişiklik açık işaretlenir
- rename ile drop+add farkı dikkatle ele alınır
- backfill planı
- rollback/forward-fix kararı
- production data volume etkisi
- lock süresi

## 13. Concurrency
Değerlendir:
- optimistic token
- unique constraint
- SELECT FOR UPDATE gerekebilir mi
- oversell/over-reservation riski

## 14. JSON kullanım kuralı
JSONB kullanılabilir ama:
- gerçek relational ilişki saklamak için kaçış yolu değildir
- sık filtrelenen alanlar yapılandırılmış kolon olabilir
- schema-less alanın ownership'i açık olmalı

## 15. Anti-patternler
- products.stock_quantity authoritative
- customers.current_balance authoritative
- EAV her yerde
- comma-separated IDs
- hard delete ile muhasebe geçmişi silmek
- nullable mega table
- "performans" bahanesiyle constraint kaldırmak

## 16. Zorunlu çıktı
Yeni/degisen DB tasarımında:
- entity
- relationships
- normal form kararı
- PK/FK/unique/check
- indexes
- snapshot/ledger/projection ayrımı
- migration etkisi
- data backfill
- risk

## 17. Definition of Done
Model veri tekrarını ve update anomaly'lerini önler, tarihsel doğruluğu korur ve DB constraint'leri kritik invariant'ları destekler.
