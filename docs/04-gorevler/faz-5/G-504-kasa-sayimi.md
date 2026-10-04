# G-504 — Kasa sayımı ve fark hareketi

## Amaç

K-094'e göre toplam fiili bakiye ile sistem kasa bakiyesini karşılaştırmak ve onaylı farkta ayrı adjustment hareketi üretmek.

## Önkoşul

G-501.

## Dokunulacak dosyalar

- CashCount create/detail bileşenleri
- `ConfirmCashCount`
- cash_count_adjustment posting
- cash count feature/concurrency testleri

## Şema / Kod

Confirm anında:

```
system = SUM(cash in) - SUM(cash out)
difference = actual - system
```

difference:

- >0 => adjustment in
- <0 => adjustment out
- =0 => movement yok

## Kurallar

- Kupür sayımı yok.
- Fark varsa reason zorunlu.
- System balance ekrandaki eski snapshot'tan alınmaz; confirm transaction'ında yeniden hesaplanır.
- Confirmed cash_count immutable.
- Geçmiş cash movement mutate edilmez.
- Money/BCMath kullanılır.

## Kabul ölçütü

- Farksız count movement üretmiyor.
- Pozitif fark in üretiyor.
- Negatif fark out üretiyor.
- Farkta gerekçesiz confirm reddediliyor.
- Arada yeni kasa hareketi oluşursa confirm güncel system balance ile yeniden hesaplıyor.
- Stale version reddediliyor.
- adjustment_document_id doğru belgeyi gösteriyor.
- Audit system/actual/difference/reason/actor içeriyor.

## İstem

> Kasa sayımını veri modeli 37 ve iş kuralı 36'ya göre uygula. Mevcut hareketleri düzeltme; farkı yeni cash_count_adjustment hareketiyle kaydet.
