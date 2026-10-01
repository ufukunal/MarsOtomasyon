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

**Tek posting sahibi:** `PostDispatch`, `PostSalesInvoice`, `PostCollection` gibi belgeye özel Action'lar yalnız kendi input/permission/source doğrulamasını ve typed posting context hazırlığını yapar; stok/cari/kasa-banka yan etkilerini kendi transaction'larında tekrar yazmaz. Kesinleştirme transaction'ının tek sahibi `PostDocument`dır.

Faz 3 profil özeti:

| Tür | stok | rezerv | cari | kasa/banka |
|---|---|---|---|---|
| dispatch | out | consume | yok | yok |
| sales_invoice / irsaliyeden | yok | yok | debit | yok |
| sales_invoice / doğrudan | out | varsa consume | debit | yok |
| collection | yok | yok | credit | in |
| contact_debit_credit | yok | yok | **typed context direction: debit\|credit** | yok |

Teklif, sipariş ve proforma kendi lifecycle Action'larında yönetilir; stok/cari posting etkisi yoktur.

`contact_debit_credit` yönü belge tipinden tahmin edilmez. `PostContactDebitCredit` doğrulanmış `debit|credit` değerini typed posting context içinde `PostDocument`a verir; `ResolveDocumentPostingProfile` bu context dışındaki serbest yön değerini kabul etmez.

## Transaction sırası

```
DB::connection('period')->transaction(function () {
    1. idempotency anahtarını doğrula
    2. EnsurePeriodOpen(document_date)
    3. gerekliyse GenerateDocumentNumber -> lockForUpdate
    4. belge toplamlarını G-302 ile tekrar doğrula
    5. stok etkili satırlar -> `RecordStockMovement` (fiziksel hareketin tek yazma noktası)
    6. rezerv etkili satırlar -> `ConsumeReservation` (yalnız reservation state + reserved; stock movement YAZMAZ)
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
- cari document transaction tekliği ve `contact_debit_credit` için typed context yönünün üretilen hareketle eşleşmesi,
- collection financial movement tutarı

eşleşmeli.

Farkta transaction rollback.


## Kurallar

- Tüm posting etkileri tek period transaction içinde.
- Belgeye özel wrapper Action aynı stock/contact/cash-bank posting zincirini yeniden implement etmez; `PostDocument`a delege eder.
- Idempotency ve deterministic lock sırası zorunlu.
- Belge tipinin üretmediği etki yazılmaz.
- `ConsumeReservation` stok hareketi yazmaz; aksi çift stok düşümüdür.
- Verify başarısızsa commit yok.

## Kabul ölçütü

- Aynı idempotency key ikinci kez stock/cari hareket üretmiyor.
- Kapalı document_date posting'i engelliyor.
- Numara concurrency'de çakışmıyor.
- Dispatch stok düşürüyor, cari yazmıyor.
- Dispatch kaynaklı invoice cari yazıyor, stok yazmıyor.
- Direct invoice hem stok hem cari yazıyor.
- PostCollection/PostDispatch/PostSalesInvoice/PostContactDebitCredit wrapper'ları posting yan etkilerini ikinci kez yazmıyor.
- `contact_debit_credit` debit ve credit yönleri typed context üzerinden doğru tek cari hareketi üretiyor.
- Verify kasıtlı bozulan senaryoda rollback ediyor.
- Deadlock retry çift etki üretmiyor.
- Gerçek PostgreSQL testleri geçiyor.

## İstem

> PostDocument, posting profile resolver ve verify Action'larını bu sırayla uygula. Her yan etki tek period DB transaction içinde olsun. İrsaliye kaynaklı faturada stok ikinci kez düşmesin. Idempotency, lockForUpdate, actor snapshot ve post-write verify atlanmasın.
