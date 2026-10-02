# G-407 — Kısmi alış faturalama ve lineage

## Amaç

K-088'e göre purchase_order → goods_receipt → purchase_invoice ve direct order → purchase_invoice miktar zincirlerini tek source_line ancestry modeliyle güvenli yürütmek.

## Önkoşul

G-404…G-406.

## Dokunulacak dosyalar

- purchasing lineage resolver genişletmesi
- goods receipt→invoice conversion
- multi-receipt invoice builder
- partial purchasing feature/concurrency testleri
- `integrity:purchasing`

## Şema / Kod

Order remaining:

```
ordered - cancelled - received - direct_invoiced
```

Receipt invoice remaining:

```
receipt quantity - effective posted purchase_invoice child total
```

Effective = kendisini hedef alan reversal_of bulunmayan belge.

## Kurallar

- Aynı order miktarı receipt ve direct invoice ile iki kez kullanılamaz.
- Bir receipt birden fazla invoice'a bölünebilir.
- Aynı supplier + currency + uyumlu alış koşullarındaki receipt'ler tek invoice'da birleşebilir.
- Child invoice line kendi gerçek source receipt/order line'ını gösterir.
- Cycle guard zorunlu.
- Source remaining transaction içinde yeniden hesaplanıp kilitlenir.

## Kabul ölçütü

- 100 order → 60 receipt + 40 direct invoice kalan 0.
- 100 receipt → 60 + 40 iki invoice kabul.
- 60 receipt sonrası ikinci 50 receipt reddediliyor.
- Farklı supplier receipt merge reddediliyor.
- Farklı currency merge reddediliyor.
- Reversed child fulfillment toplamından çıkıyor.
- Concurrent invoice aynı remaining'i iki kez kullanamıyor.
- integrity:purchasing sapmayı yakalıyor, düzeltmiyor.

## İstem

> Faz 4 partial lineage'ı iş kuralı 34'e göre uygula. Fulfillment için denormalize ikinci gerçek kolon ekleme.
