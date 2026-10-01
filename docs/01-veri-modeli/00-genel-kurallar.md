# Veri modeli — genel kurallar

## Veritabanı ayrımı

**Master:** şirket, dönem, kullanıcı, rol/izin, şirket+dönem erişimi, kur, sistem ayarı, company_copy_permissions, print_profiles, master audit.

**Period:** kartlar dahil yıla bağlı bütün işletme verisi.

Period tablolarında `company_id` yoktur. Fiziksel DB seçimi şirket izolasyonudur. Period modelleri `PeriodModel`, Master modelleri `MasterModel` kullanır.

## Foreign key

Aynı period DB içindeki ilişkiler gerçek PostgreSQL FK kullanır. Örnek: documents.contact_id → contacts.id, document_lines.product_id → products.id.

Master ve period arasında gerçek FK kurulmaz. Period kaydındaki actor alanlarında `user_id` scalar ve `user_name` snapshot saklanır.

Şirketler arası kopyalama provenance alanı `source_company_id` de scalar'dır; Master companies tablosuna period DB'den FK kurulmaz.

## Kimlik ve kod

- Period içi kart kodu benzersizdir.
- Pasif kartın kodu başka karta tekrar verilmez.
- Aynı şirket dönem devrinde taşınan kart ID ve kodları korunur.
- Taşınan stock_balance kayıtlarının ID'si de korunur.
- Şirketler arası kopyalamada hedef yeni ID üretir.
- Devir sonrası sequence değerleri `MAX(id)+1` seviyesine alınır.

## Hassasiyet

- money: decimal(18,4)
- quantity: decimal(18,3)
- rate: decimal(7,4)
- exchange rate / conversion: decimal(18,6)
- PHP float yasak; Money + BCMath.

## Zaman

İş tarihi gereken belgede `document_date` kullanılır. `created_at` sistem kayıt zamanıdır. Dönem kilidi `document_date` üzerinden çalışır.

## Eşzamanlı düzenleme

Kullanıcı tarafından düzenlenebilir ana kayıtlar `version unsignedInteger default(1)` taşır ve optimistic lock kullanır. Append-only hareket/audit tabloları, yalnız türetilmiş bakiye tabloları ve saf pivot/join tabloları bu `version` zorunluluğunun dışındadır; bunların bütünlüğü transaction/FK/lock/integrity kurallarıyla korunur.

## Değişmezlik

Posted/kesinleşmiş hareket ve belge fiziksel silinmez/değiştirilmez; ters kayıt kullanılır. Kartlar `is_active=false` ile pasifleştirilir. Taslak belge numara almadan fiziksel silinebilir.

## Bütünlük

İhlal edilemez kurallar DB CHECK ile korunur. Türetilmiş/kopyalanmış her veri için aynı fazda `integrity:` kontrolü tanımlanır.
