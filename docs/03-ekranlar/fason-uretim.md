# Ekran — Fason Üretim

## Amaç

Fasoncuya mal gönderimi, fason stokları, kısmi dönüş/completion ve hizmet maliyetini yönetmek.

## Fasoncu

- Supplier/Contact
- Bağlı Subcontractor Location

## Gönderim

- Production Order
- Source Location
- Component
- Quantity
- Target Subcontractor Location

Kısmi gönderim desteklenir.

## Fason stok görünümü

- Component
- Subcontractor Location
- Fiziksel Quantity
- Gönderilen
- Tüketilen
- Fire
- Kalan

## Completion

- kısmi dönüş desteklenir
- consumption fason location'dan
- fire fason location'dan
- mamul output birden fazla target location'a bölünebilir

## Hizmet faturası

- Bağlı purchase_invoice
- Hizmet maliyeti
- Fatura durumu

Fatura yoksa completion yapılabilir.

Sonradan gelen hizmet faturası production cost adjustment üretir.

## Eylemler

- Fasona Gönder
- Completion Gir
- Hizmet Faturasını Bağla
- Late Cost Adjustment Aç
