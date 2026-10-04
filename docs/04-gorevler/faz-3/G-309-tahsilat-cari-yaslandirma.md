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

v65 görselinde Şube, Cari, İşlem, Tutar, Para Birimi, Kasa/Banka, Tarih, Vade, Evrak No ve Not görünür.

Faz 3'te gerçekten persist edilen input:

- contact_id
- amount
- account_type + account_id
- document_date
- note
- optional source_invoice_id
- idempotency_key

Satış tahsilatı TRY'dir; işlem tipi `collection` olarak sabittir.

**Şube, Vade ve Evrak No için Faz 3 kaynaklarında backing kolon/ilişki tanımlı değildir. Claude bunlar için yeni kolon, JSON alanı veya lookup tablosu uydurmaz.** Bu üç v65 alanı Faz 3'te aktif input değildir.

Form yalnız `Post` ile kalıcı hareket üretir. Ayrı kaydedilmiş draft collection detayı uydurma.

## PostCollection

`PostCollection` bir **finance façade**'ıdır; G-303 posting zincirini kopyalamaz. Form input'unu doğrular, collection document verisini ve cash/bank account context'ini hazırlar, ardından kesinleştirmeyi `PostDocument`a delege eder.

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

Kesinleştirme:

1. `PostCollection` account_type/account_id, contact, amount ve optional source invoice doğrulamasını yapar.
2. Collection document başlığı `subtotal = tax_base = grand_total = amount`, `vat_amount = 0`, `rounding_difference = 0` olacak şekilde G-303'e verilir.
3. `PostDocument` idempotency, period lock, number series, `contact_transactions.credit`, cash/bank movement, posted actor, verify ve audit adımlarının **tek sahibidir**.
4. source invoice verilmişse `collection_source` relation bilgi amaçlı yazılır.
5. Wrapper Action ayrı ikinci transaction veya ikinci contact/cash/bank hareketi yazmaz.

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

Posting sırasında wrapper input'u doğrular ve G-303'e delege eder:

- `contact_debit_credit` number series kullanılır,
- documents satırı `subtotal = tax_base = grand_total = amount`, `vat_amount = 0` ile kesinleşir,
- direction yalnız üretilen `contact_transactions` kaydında finansal yön olarak saklanır,
- gerekçe period audit'e yazılır,
- contact transaction/numara/audit zinciri wrapper içinde ikinci kez yazılmaz.

Ayrı draft yön alanı için yeni tablo/kolon uydurma.

## Cari bakiye

```
SUM(debit) - SUM(credit)
```

contacts üzerinde balance kolonu yok. Cache yok.

## FIFO yaşlandırma

`BuildContactAging` DB'ye settlement yazmaz.

1. rapor as_of tarihinden sonraki hareketleri dışarıda bırak,
2. `reversal_of_id` ile bağlı exact inverse çiftleri aging setinden birlikte nötrle,
3. kalan borç/debit hareketlerini `COALESCE(due_date, transaction_date)`, transaction_date, id sırasına koy,
4. kalan toplam credit'i en eski borçtan başlat,
5. her satır için applied_credit ve remaining runtime hesapla,
6. effective due date'e göre bucket ata,
7. renk ata:
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
- Reversed invoice başka eski borcu FIFO ile kapatmıyor; original+reversal aging setinde nötr.
- Reversed collection yeni açık debit satırı gibi yaşlandırılmıyor.
- Aging toplamı cari bakiye ile tutarlı.
- Gerçek PostgreSQL testleri geçiyor.

## İstem

> contact_transactions ve minimum cash/bank tablolarını veri modeli 32/34'e göre uygula. PostCollection ve manuel cari borç/alacak fişini idempotent tek transaction yap. Fatura settlement tablosu oluşturma. FIFO yaşlandırmayı runtime hesapla; yeşil/sarı/kırmızı durumlarını K-064'e göre üret.
