# G-309 — Tahsilat, cari hareket, minimum kasa/banka ve yaşlandırma

## Amaç

Cari bakiyenin tek kaynağı olan `contact_transactions` altyapısını kurmak; nakit/banka tahsilatını gerçek hesaba bağlamak; fatura settlement zorunluluğu olmadan FIFO yaşlandırma raporu üretmek.

## Önkoşul

G-301, G-303, G-007, G-018.

## Dokunulacak dosyalar

- `database/migrations/period/*_create_contact_transactions_table.php`
- `database/migrations/period/*_create_cash_accounts_table.php`
- `database/migrations/period/*_create_bank_accounts_table.php`
- `database/migrations/period/*_create_cash_movements_table.php`
- `database/migrations/period/*_create_bank_movements_table.php`
- `app/Models/Period/ContactTransaction.php`
- `app/Models/Period/CashAccount.php`
- `app/Models/Period/BankAccount.php`
- `app/Actions/Finance/PostCollection.php`
- `app/Actions/Finance/PostContactDebitCredit.php`
- `app/Queries/Finance/BuildContactAging.php`
- `app/Livewire/Finance/CollectionForm.php`
- `app/Livewire/Finance/ContactAging.php`
- `tests/Feature/Finance/CollectionAndAgingTest.php`

## Şema / Kod

Şema kaynakları:

- `docs/01-veri-modeli/32-contact_transactions.md`
- `docs/01-veri-modeli/34-cash-bank-minimum.md`

Period tablolarında company_id yoktur.

## Tahsilat formu

v65 alanları:

- Şube
- Cari
- İşlem
- Tutar
- Para Birimi
- Kasa/Banka
- Tarih
- Vade
- Evrak No
- Not

Faz 3 satışta TRY.

Form yalnız `Post` ile kalıcı hareket üretir. Ayrı kaydedilmiş draft collection detayı uydurma.

## PostCollection

Input:

- contact_id
- amount decimal string
- document_date
- account_type = cash|bank
- account_id
- optional source_invoice_id
- note
- idempotency_key
- actor snapshot

Tek transaction:

1. idempotency
2. EnsurePeriodOpen
3. GenerateDocumentNumber('collection')
4. `documents` collection başlığı oluştur; subtotal = tax_base = grand_total = amount, vat_amount = 0, rounding_difference = 0
5. `contact_transactions` credit
6. cash ise `cash_movements.in`; bank ise `bank_movements.in`
7. source invoice verilmişse yalnız bilgi amaçlı `collection_source` relation
8. post-write verify: üç tutar aynı
9. activity_log

Tahsilat herhangi bir faturaya zorunlu dağıtılmaz.

## Manuel Cari Borç / Alacak Fişi

K-081.

Tek adım form input'u:

- contact
- direction = debit|credit
- amount
- document_date
- reason zorunlu
- note
- idempotency key

Posting sırasında:

- `contact_debit_credit` number series kullan,
- documents satırı oluştur; subtotal = tax_base = grand_total = amount, vat_amount = 0,
- direction yalnız üretilen contact_transaction'da gerçek finansal yön olarak saklanır,
- gerekçe period audit'e yazılır.

Ayrı draft yön alanı için yeni tablo/kolon uydurma.

## Cari bakiye

```
SUM(debit) - SUM(credit)
```

contacts üzerinde balance kolonu yok. Cache yok.

## FIFO yaşlandırma

`BuildContactAging` DB'ye settlement yazmaz.

1. rapor as_of tarihinden sonraki hareketleri dışarıda bırak,
2. borç/debit hareketlerini due_date, transaction_date, id sırasına koy,
3. toplam credit'i en eski borçtan başlat,
4. her satır için applied_credit ve remaining runtime hesapla,
5. due_date'e göre bucket ata,
6. renk ata:
   - green: remaining = 0
   - yellow: 0 < remaining < original
   - red: applied_credit = 0

Dilimler:

- future/not due
- 1-30
- 31-60
- 61-90
- 91-120
- 120+

Satır remaining toplamı `max(cari bakiye, 0)` ile tutarlı olmalı. Credit fazlası varsa açık alacak 0, fazla credit ayrı cari alacak/avans olarak gösterilir.

## Minimum kasa/banka

G-309 yalnız:

- hesap CRUD,
- tahsilat movement,
- hareket listesi

yapar.

Virman, ekstre, mutabakat, kasa sayımı, çek/senet Faz 5.


## Kurallar

- Cari bakiye yalnız contact_transactions toplamıdır.
- Tahsilat zorunlu fatura settlement'ı üretmez.
- Collection contact + cash/bank etkilerini tek transaction yazar.
- Aging yalnız runtime FIFO raporudur.

## Kabul ölçütü

- Collection credit cari bakiyeyi azaltıyor.
- Cash collection aynı tutarda cash movement yazıyor.
- Bank collection aynı tutarda bank movement yazıyor.
- Aynı idempotency key çift tahsilat üretmiyor.
- Fatura source relation olsa da bakiye hesabı değişmiyor.
- Manuel debit/credit gerekçesiz post edilemiyor.
- Aging DB'ye settlement yazmıyor.
- Tam kapanan satır green, kısmi yellow, hiç kapanmayan red.
- FIFO en eski borcu önce kapatıyor.
- Aging toplamı cari bakiye ile tutarlı.
- Gerçek PostgreSQL testleri geçiyor.

## İstem

> contact_transactions ve minimum cash/bank tablolarını veri modeli 32/34'e göre uygula. PostCollection ve manuel cari borç/alacak fişini idempotent tek transaction yap. Fatura settlement tablosu oluşturma. FIFO yaşlandırmayı runtime hesapla; yeşil/sarı/kırmızı durumlarını K-064'e göre üret.
