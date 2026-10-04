# Çek/Senet yaşam döngüsü

## Temel ilke

K-096 yaşam döngüsünü, K-082 cari etkisini belirler. Kıymet kaydı silinmez; durum geçişleri immutable `security_events` ile tarihçelenir.

`securities.current_status` son etkin event'in snapshot'ıdır ve integrity ile doğrulanır.

## Alınan çek/senet

Kanonik akış:

```
received
  → portfolio
      ├─ endorsed
      └─ sent_to_collection
             ├─ collected
             └─ bounced_or_returned
```

Kıymet portföydeyken doğrudan karşılıksız/geri dönüş senaryosu da olay geçmişiyle kaydedilebilir; izinli transition listesi Action katmanında merkezî tutulur.

### İlk teslim

Received kıymet müşteri carisini azaltır:

- original contact,
- `contact_transactions.credit`,
- amount = kıymet tutarının temel para birimi ledger değeri.

Aynı security için ilk cari etki ikinci kez üretilemez.

### Ciro

Ciroda:

- original contact ikinci kez etkilenmez,
- target/counterparty contact zorunludur,
- target contact için `contact_transactions.debit` oluşur,
- security kimliği değişmez,
- endorse event geçmişe eklenir.

Bu, kıymetin karşı tarafa ödeme olarak verilmesinin cari etkisidir.

### Tahsile verme

`sent_to_collection`:

- bank_account_id zorunlu,
- bank_delivery_date ve gerekirse reference snapshot edilir,
- cari ikinci kez etkilenmez.

### Tahsil

`collected`:

- cari hareket üretmez,
- ilgili banka hesabında `bank_movements.in` üretir,
- settlement_reference saklanabilir.

## Karşılıksız / geri dönüş

Alınan kıymetin geri dönmesi:

- ilk customer credit etkisini veya ciro edilmişse ilgili etkin cari etkileri kurala göre exact inverse hareketlerle tersler,
- önceki contact transaction silinmez,
- `reversal_of_id` ile bağlanır,
- protesto/karşılıksız detayları saklanır.

Aynı etki iki kez terslenemez.

## Verilen çek/senet

Kanonik akış:

```
issued
  → awaiting_payment
      ├─ paid
      └─ returned_or_cancelled
```

### İlk verme

Verilen kıymet supplier/karşı cari borcunu azaltır:

- original contact,
- `contact_transactions.debit`.

### Ödeme

`paid`:

- cari ikinci kez etkilenmez,
- ilgili banka hesabında `bank_movements.out` üretir,
- settlement_reference saklanabilir.

### Geri dönüş / iptal

İlk debit cari etkisinin exact inverse'i olan `credit` hareketi üretilir. Original hareket silinmez.

## Para birimi

`securities.currency` veri modelinde snapshot alanıdır. K-013 genişletilmediği için Faz 5 ilk sürümünde cari/finans posting etkileri şirket temel para biriminde yürütülür; yeni FX dönüşümü veya kur farkı hesabı yoktur.

## Yetkiler

En az:

- `securities.create`
- `securities.endorse`
- `securities.send_to_collection`
- `securities.collect`
- `securities.issue`
- `securities.pay`
- `securities.return`

Rol adı sabitlenmez.

## Transaction

Her state transition:

1. idempotency,
2. EnsurePeriodOpen(event_date),
3. security row lock + version,
4. transition doğrulama,
5. gerekiyorsa contact/finance movement,
6. immutable security_event,
7. current_status snapshot update,
8. post-write verify,
9. audit,
10. idempotency done

tek period transaction içindedir.
