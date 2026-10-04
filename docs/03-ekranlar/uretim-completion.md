# Ekran — Üretim Completion

## Amaç

Gerçek component tüketimini, fireyi ve mamul output dağılımını tek işlemde kaydetmek.

## Component tablosu

Her satır:

- Component
- Planned Consumption
- Actual Consumption
- Fire
- Source Location
- Unit Cost Snapshot
- Total Cost

Kullanıcı actual consumption ve fire miktarını değiştirebilir.

## Output tablosu

K-139 gereği mamul sonucu birden fazla location'a bölünebilir:

- Target Location
- Quantity

```
SUM(output quantity) = completion quantity
```

## Maliyet özeti

- Material Cost
- Subcontract Service Cost — fason ise
- Production Total Cost
- Production Unit Cost
- Current Moving Average
- Projected Moving Average

## Eylemler

- Completion Post Et
- Satır Ekle — output location
- Reverse

## Kurallar

- Component stock out + mamul stock in aynı transaction.
- Fire maliyete dahil.
- Completion quantity remaining'i aşamaz.
