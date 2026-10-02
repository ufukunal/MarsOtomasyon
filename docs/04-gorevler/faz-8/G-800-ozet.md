# G-800 — Faz 8 Basit üretim/fason özeti

## Amaç

Faz 8 reçete, üretim emri, completion, fire, çoklu target location, fason stok ve fason hizmet maliyetini mevcut stok/maliyet/alış altyapısı üzerinde tamamlar.

## Kilit kararlar

- K-131…K-162.

## Veri modeli

- `41-production-and-subcontracting.md`

## İş kuralları

- `45-recete-ve-uretim-emri.md`
- `46-uretim-completion-ve-maliyet.md`
- `47-fason-uretim.md`

## Ekranlar

- `receteler.md`
- `uretim-emri.md`
- `uretim-completion.md`
- `fason-uretim.md`

## Görev sırası

| Görev | İçerik |
|---|---|
| G-801 | üretim/fason şeması |
| G-802 | reçete revizyonları |
| G-803 | production order |
| G-804 | production completion |
| G-805 | production cost/moving average |
| G-806 | fason location/gönderim |
| G-807 | fason completion/hizmet maliyeti |
| G-808 | reverse/integrity/dönem devri |
| G-809 | gerçek PostgreSQL Faz 8 testleri |

## Faz bitiş ölçütü

Faz 8 dokümantasyonu K-131…K-162 kararlarıyla yazılmıştır. Kodlama/uygulama G-801…G-809 kabul ölçütleri gerçek PostgreSQL üzerinde geçmeden tamamlanmış sayılmaz.
