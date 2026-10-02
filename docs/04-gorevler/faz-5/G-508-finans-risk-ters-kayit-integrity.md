# G-508 — Finans ters kayıt, çek/senet risk ve integrity

## Amaç

Faz 5 finans belgeleri ve securities için ters kayıt, risk projeksiyonu ve integrity kontrollerini tamamlamak.

## Önkoşul

G-502…G-507, G-311 ters kayıt altyapısı, satış risk projeksiyonu.

## Dokunulacak dosyalar

- finance reverse Action genişletmeleri
- security reverse/event inverse helper
- satış siparişi risk query genişletmesi
- `IntegrityFinance`
- `IntegritySecurities`
- Faz 5 integrity/reversal testleri

## Şema / Kod

Risk seti K-078 + iş kuralı 38:

- received securities: portfolio + sent_to_collection
- endorsed/collected/bounced excluded
- issued excluded

Risk, cari bakiyeye eklenmez; satış risk projeksiyonunda ayrı bileşendir.

## Kurallar

- Posted finance document mutate edilmez.
- Reverse yeni movement/event üretir.
- Exact inverse relation korunur.
- finance_transfer çift taraflı terslenir.
- supplier_payment contact+finance terslenir.
- security current_status event history ile integrity kontrol edilir.
- Integrity farkı otomatik düzeltilmez.

## Kabul ölçütü

- Transfer reverse iki hareketi tersliyor.
- Supplier payment reverse contact credit + finance in üretiyor.
- Security bounce/return duplicate inverse üretemiyor.
- Portfolio/sent_to_collection riskte sayılıyor.
- Endorsed/collected/bounced ve issued riskte sayılmıyor.
- integrity:finance eksik transfer ayağını yakalıyor.
- integrity:finance cash count farkını yakalıyor.
- integrity:securities illegal status/event/cari etkisini yakalıyor.
- Risk security'yi çift saymıyor.

## İstem

> Faz 5 ters kayıt ve integrity zincirini iş kuralları 35–38'e göre tamamla. Risk toplamını cari bakiyenin içine yazma.
