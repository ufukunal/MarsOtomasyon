# Reçete ve üretim emri

## Reçete

K-132/K-133:

- bir mamulde tek aktif reçete,
- değişiklik ayrı immutable Rev.N,
- eski revizyon korunur.

## Reçete tabanı

K-134:

```
component_required =
recipe_line.base_quantity
× planned_production_quantity
÷ recipe.output_quantity
```

Tüm stok miktarları temel birimdedir.

## Production order snapshot

K-135:

Production order oluşturulurken:

- recipe revision id/no,
- component,
- unit,
- conversion_factor,
- planned quantity

snapshot edilir.

Sonradan reçete revize edilmesi emri değiştirmez.

## Production-mode satış siparişi

K-158:

`channel_stock_mode=production` ürün içeren confirmed sales order için ihtiyaç kadar draft production order açılabilir.

Draft emir kullanıcı tarafından confirm edilir.

K-159:

- source sales order ilişkisi opsiyoneldir,
- bağımsız production order mümkündür.

## Yaşam döngüsü

K-142:

```
draft
→ confirmed
→ in_progress
→ completed
   veya
→ cancelled
```

Kısmi completion varsa status in_progress.

Remaining:

```
planned - completed - cancelled
```

Kalan iptal edilebilir.

## Dönem

document_date dönem kilidine tabidir.

Açık production order dönem devrinde taşınmaz.
