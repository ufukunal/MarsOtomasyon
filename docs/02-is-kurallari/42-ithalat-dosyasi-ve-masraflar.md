# İthalat dosyası ve masraflar

## Temel ilke

K-114/K-125:

- stok/cari ilk etkisi purchase_invoice'dadır,
- import file kendi stok/cari hareketini üretmez,
- import file ek maliyet toplama/dağıtma/finalize aracıdır.

## Yaşam döngüsü

K-126:

```
draft
→ cost_collection
→ finalized
→ adjusted
```

### draft

- purchase invoice satırları bağlanabilir,
- notlar düzenlenebilir.

### cost_collection

- expense eklenir/değiştirilir,
- allocation preview hesaplanır.

### finalized

- immutable,
- tüm inventory-cost expenses dağıtılmış,
- inventory_cost_adjustments oluşturulmuş,
- product_costs.import_cost snapshot güncellenmiş,
- moving average adjustment uygulanmış.

### adjusted

Finalized dosyaya sonradan gelen ayrı import cost adjustment bulunduğunu gösteren türetilmiş durumdur; eski dosya açılmaz.

## Kaynak purchase invoice satırları

K-120/K-127:

- tam satır bazında bağlanır,
- aynı invoice line ikinci import file'a bağlanamaz,
- bir import file birden fazla purchase_invoice ve supplier içerebilir,
- kaynak satır posted purchase_invoice olmalıdır.

## Masraf türleri

K-116:

- freight
- customs_duty
- insurance
- storage
- customs_brokerage
- port_terminal
- other

`other` açıklama gerektirir.

## Masraf kaynağı

K-117:

### purchase_invoice

- mevcut purchase_invoice kaynağı,
- cari etkisi kendi faturasında oluşmuştur,
- import file ikinci cari hareket üretmez,
- K-257 gereği faturalı import masrafı service satırıyla temsil edilir,
- mixed mal+hizmet faturasında expense kaynağı `source_document_line_id` ile yalnız ilgili service satırıdır; stock satırlar masraf havuzuna girmez,
- expense amount, service satırın frozen net tutarıdır.

### manual

- yalnız maliyet kaydı,
- cari hareket üretmez,
- ödeme kaydı üretmez.

## Vergi

K-118:

Maliyete dahil:

- customs duty,
- freight,
- insurance,
- storage,
- customs brokerage,
- port/terminal,
- geri alınamayan vergi/harç.

İndirilebilir ithalat KDV'si maliyete girmez.

Genel muhasebe kaydı üretilmez.
