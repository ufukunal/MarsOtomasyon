# G-303 — PostDocument

## Amaç

Kesinleşen satış belgelerinin dönem, numara, stok, rezerv, cari, finansal hareket, doğrulama ve audit etkilerini tek transaction zincirinde yürütmek.

## Önkoşul

G-301, G-302, G-006, G-007, G-018, G-202, G-210.

## Dokunulacak dosyalar

- `app/Actions/Documents/PostDocument.php`
- `app/Actions/Documents/ResolveDocumentPostingProfile.php`
- `app/Actions/Documents/VerifyPostedDocument.php`
- `app/DataObjects/Documents/PostingProfile.php`
- `tests/Feature/Documents/PostDocumentTest.php`

## Şema / Kod

`PostDocument` document type için etkileri profile üzerinden çözer; Livewire içinde switch yazılmaz.

Faz 3 profil özeti:

| Tür | stok | rezerv | cari | kasa/banka |
|---|---|---|---|---|
| dispatch | out | consume | yok | yok |
| sales_invoice / irsaliyeden | yok | yok | debit | yok |
| sales_invoice / doğrudan | out | varsa consume | debit | yok |
| collection | yok | yok | credit | in |
| contact debit/credit | yok | yok | profile yönü | yok |

Teklif, sipariş ve proforma kendi lifecycle Action'larında yönetilir; stok/cari posting etkisi yoktur.

## Transaction sırası

```
DB::connection('period')->transaction(function () {
    1. idempotency anahtarını doğrula
    2. EnsurePeriodOpen(document_date)
    3. gerekliyse GenerateDocumentNumber -> lockForUpdate
    4. belge toplamlarını G-302 ile tekrar doğrula
    5. stok etkili satırlar -> RecordStockMovement
    6. rezerv etkili satırlar -> ConsumeReservation
    7. cari etkili belge -> contact_transactions
    8. collection ise cash/bank movement
    9. status + posted_at + actor snapshot
   10. VerifyPostedDocument
   11. activity_log
   12. idempotency done/result
}, attempts: 3);
```

## Kilit sırası

- Kaynak document / source line'lar deterministik ID sırasıyla.
- Rezervasyon ve stock balance satırları ürün/lokasyon sırasıyla.
- Number series yalnız numara adımında.
- Farklı Action'larda ters kilit sırası oluşturma.

## İrsaliye kaynaklı fatura

PostDocument stok etkisini yalnız relation/source_line zincirinden belirler.

- invoice line source dispatch line ise stok yazma.
- doğrudan invoice line ise stok çıkışı yaz.
- aynı quantity'nin hem dispatch hem direct invoice etkisi olmasını validation engeller.

## Actor

Authenticated Master user:

- `posted_by`
- `posted_by_name`
- hareketlerde created_by + name
- audit actor

olarak snapshot edilir. Cross-DB FK yok.

## Post-write verify

Commit öncesi:

- document totals,
- stock movement miktarı,
- reservation consume miktarı,
- cari document transaction tekliği,
- collection financial movement tutarı

eşleşmeli.

Farkta transaction rollback.

## Kabul ölçütü

- Aynı idempotency key ikinci kez stock/cari hareket üretmiyor.
- Kapalı document_date posting'i engelliyor.
- Numara concurrency'de çakışmıyor.
- Dispatch stok düşürüyor, cari yazmıyor.
- Dispatch kaynaklı invoice cari yazıyor, stok yazmıyor.
- Direct invoice hem stok hem cari yazıyor.
- Verify kasıtlı bozulan senaryoda rollback ediyor.
- Deadlock retry çift etki üretmiyor.
- Gerçek PostgreSQL testleri geçiyor.

## İstem

> PostDocument, posting profile resolver ve verify Action'larını bu sırayla uygula. Her yan etki tek period DB transaction içinde olsun. İrsaliye kaynaklı faturada stok ikinci kez düşmesin. Idempotency, lockForUpdate, actor snapshot ve post-write verify atlanmasın.
