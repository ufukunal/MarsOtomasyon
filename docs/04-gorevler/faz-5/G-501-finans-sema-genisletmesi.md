# G-501 — Faz 5 finans şema genişletmesi

## Amaç

Faz 3 minimum kasa/banka altyapısını K-092…K-095 için genişletmek; yeni finans hareket ailesi oluşturmadan virman, tedarikçi ödeme, kasa sayımı ve banka mutabakatı veri modelini hazırlamak.

## Önkoşul

G-309, G-301, G-303 ve K-092…K-095.

## Dokunulacak dosyalar

- cash_movements migration değişikliği
- bank_movements migration değişikliği
- `cash_counts` migration/model
- DocumentType genişletmesi
- DocumentRelationType genişletmesi
- `tests/Feature/Finance/Faz5FinanceSchemaTest.php`

## Şema / Kod

Kaynaklar:

- `docs/01-veri-modeli/34-cash-bank-minimum.md`
- `36-finance-movements-faz5.md`
- `37-cash_counts.md`

Yeni document type:

- supplier_payment
- finance_transfer
- cash_count_adjustment

Yeni relation type:

- payment_source

Faz 3 `unique(document_id)` kısıtları, aynı virman document'ının iki aynı-tablo hareketi üretebilmesi için veri modeli 36'daki composite unique yapısına dönüştürülür.

## Kurallar

- Period tablolarında company_id yok.
- Mevcut cash/bank hesap ve movement tabloları yeniden oluşturulmaz.
- Bank mutabakatı finansal movement içeriğini değiştirmez.
- cash_counts confirmed kayıt immutable.
- Master user actor alanlarında cross-DB FK yok.

## Kabul ölçütü

- Migration gerçek PostgreSQL'de up/down çalışıyor.
- Cash→Cash aynı document altında source out + target in yazılabiliyor.
- Bank→Bank aynı document altında source out + target in yazılabiliyor.
- Aynı document/account/direction duplicate reddediliyor.
- bank_movements reconciliation metadata alanları çalışıyor.
- cash_counts FK/CHECK kuralları geçerli.
- Yeni document/relation type'lar kabul ediliyor.

## İstem

> Faz 3 minimum finans tablolarını veri modeli 36–37'ye göre genişlet. İkinci cash/bank movement tablosu kurma; virman için eski unique kısıtını kontrollü composite unique'e çevir.
