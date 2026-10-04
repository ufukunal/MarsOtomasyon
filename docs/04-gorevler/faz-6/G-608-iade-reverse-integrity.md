# G-608 — İade reverse ve integrity

## Amaç

Posted iadelerin immutable reverse davranışını ve return/quarantine bütünlük kontrollerini tamamlamak.

## Önkoşul

G-603…G-607, G-311 reverse altyapısı.

## Dokunulacak dosyalar

- return reverse Action
- IntegrityReturns
- IntegrityQuarantine
- reverse/integrity tests

## Şema / Kod

Sales return reverse:
- customer debit
- stock out
- ilgili aktif quarantine etkisini tersle

Purchase return reverse:
- supplier credit
- stock in
- moving average etkisi stok giriş kuralına göre doğrulanır

## Kurallar

- Original mutate edilmez.
- Duplicate reverse yok.
- Reverse partial return capacity'yi geri açar.
- Karantina üzerinde karar verilmiş miktar varsa reverse yalnız tutarlı ters zincirle yapılır; sessiz miktar silme yok.
- Integrity otomatik düzeltmez.

## Kabul ölçütü

- Sales return reverse net stock/cari/quarantine etkisini tersliyor.
- Purchase return reverse net stock/cari etkisini tersliyor.
- Duplicate reverse reddediliyor.
- integrity:returns source aşımını/snapshot tutarsızlığını buluyor.
- integrity:quarantine summary farkını buluyor.

## İstem

> Faz 6 reverse ve integrity zincirini immutable yeni kayıtlarla tamamla.
