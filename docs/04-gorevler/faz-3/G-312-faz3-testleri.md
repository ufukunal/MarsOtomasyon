# G-312 — Faz 3 bütünleşik testler

## Amaç

Faz 3 satış çekirdeğinin hesap, lifecycle, kısmi işlem, stok, cari, tahsilat, ters kayıt ve eşzamanlılık sözleşmelerini gerçek PostgreSQL üzerinde bütünleşik doğrulamak.

## Önkoşul

G-301…G-311 tamamlanmış olmalı.

## Dokunulacak dosyalar

- `tests/Feature/Sales/Faz3SalesFlowTest.php`
- `tests/Feature/Sales/Faz3ConcurrencyTest.php`
- `tests/Feature/Finance/Faz3LedgerIntegrityTest.php`
- `app/Console/Commands/IntegrityDocuments.php`
- `app/Console/Commands/IntegrityContacts.php`
- `app/Console/Commands/IntegrityPartials.php`


## Şema / Kod

Yeni production şeması yok. Bu görev yalnız Faz 3 şemalarını ve `integrity:documents`, `integrity:contacts`, `integrity:partials` kontrollerini test/komut olarak tamamlar.

## Test ortamı

- gerçek PostgreSQL,
- Master DB,
- en az iki şirket,
- her şirket için ayrı period DB,
- company_user + period_user_access,
- gerçek CHECK/FK/lockForUpdate davranışı.

SQLite kullanılmaz.

## Hesap testleri

- line discount ve document discount KDV'den önce.
- KDV rate group bazında tek hesap.
- 100 satırlık belgede kuruş sapması yok.
- rounding_difference CHECK ile uyumlu.
- PHP float'a dönüşüm yok.
- "Tümüne KDV uygula" / "KDV temizle" yalnız draftta.

## Teklif

- draft number yok.
- review'a çıkışta tek number.
- Rev.1 → Rev.2 ayrı immutable kayıt.
- same main number.
- internal approval permission.
- quote stok/cari etkisiz.
- approved quote order dönüşümü source line zincirini koruyor.

## Sipariş / rezervasyon

- risk limit aşımı uyarı, blok değil.
- rezerv stock quantity'yi düşürmüyor.
- çok lokasyon reservation.
- yetersiz stock'ta mevcut kadar reserve.
- cancelled quantity tekrar kullanılamıyor.
- concurrency reserved toplamını available üstüne çıkarmıyor.

## İrsaliye

- partial dispatch.
- source remaining aşımı engel.
- reservation consume + stock out aynı transaction.
- cari etkisi yok.
- direct dispatch doğru location'dan stock out.

## Fatura

- dispatch-source invoice cari debit, stok yok.
- direct invoice stock out + debit.
- order-direct invoice reservation consume.
- partial 60/40 invoice.
- multi-dispatch same-contact merge.
- different contact merge reddi.
- due date contact/company default.
- posted immutable.

## Proforma

- proforma series numarası.
- stok/cari etkisiz.
- invoice conversion direct stock+cari.
- aynı kaynak miktar çift invoice edilemiyor.

## Tahsilat / cari

- contact balance hareket toplamı.
- collection cash/bank movement.
- invoice settlement zorunlu değil.
- manual debit/credit gerekçeli.
- FIFO aging.
- green/yellow/red renk sonucu.
- aging remaining toplamı cari bakiye ile tutarlı.

## Sıcak satış

- transfer teslim öncesi vehicle stock yok.
- vehicle direct invoice araçtan stock out.
- collection otomatik değil.

## Reverse

- original immutable.
- stock/cari/cash inverse effect.
- duplicate reverse engel.
- reversed child partial toplamdan çıkar.

## Transaction / concurrency

- idempotency çift tıklama.
- number_series concurrency.
- stock lock concurrency.
- source_line partial concurrency.
- optimistic lock stale draft.
- post-write verify failure rollback.
- deadlock retry çift etki üretmiyor.
- kapalı document_date bütün posting türlerini engelliyor.

## Integrity

`integrity:documents`:
- totals / VAT / rounding.

`integrity:contacts`:
- contact_transactions / balance / reversal.

`integrity:partials`:
- source line fulfillment / cancellation.

Mevcut Faz 2:
- integrity:stock
- integrity:reservations
- integrity:units

Fark raporlanır, otomatik düzeltme yok.


## Kurallar

- Testler gerçek PostgreSQL kullanır.
- Beklenen parasal değerler production hesap fonksiyonundan türetilmez.
- İki fiziksel period DB ile izolasyon test edilir.
- Integrity farkı otomatik düzeltilmez.

## Kabul ölçütü

- Tüm Faz 3 test suite gerçek PostgreSQL'de yeşil.
- Pint yeşil.
- Larastan level 6 yeni hata yok.
- İki period DB arasında veri sızıntısı yok.
- cost.view olmayan kullanıcı payload/export'ta maliyet görmüyor.
- E-Belge veya kalite modülü route/tab eklenmemiş.
- Faz 3'te tanımlanmamış yeni business karar kodda uydurulmamış.

## İstem

> Faz 3 için yukarıdaki bütünleşik testleri ve integrity komutlarını yaz. Testlerde beklenen parasal değerleri production hesap fonksiyonundan türetme; elle sabit değer kullan. Gerçek PostgreSQL, iki şirket/period izolasyonu, concurrency ve rollback senaryolarını çalıştır.
