# G-700 — Faz 7 İthalat özeti

## Amaç

Faz 7, Faz 4 alış çekirdeğini yeniden kullanarak ithalat dosyası, masraf toplama/dağıtma, finalized import cost ve miktarı değiştirmeyen inventory cost adjustment davranışını tamamlar.

## Kilit kararlar

- K-114…K-130.

## Veri modeli

- `40-imports-and-cost-adjustments.md`

## İş kuralları

- `42-ithalat-dosyasi-ve-masraflar.md`
- `43-ithalat-masraf-dagitimi.md`
- `44-ithalat-maliyet-finalize-ve-adjustment.md`

## Ekranlar

- `ithalat-dosyalari.md`
- `ithalat-dosyasi-detay.md`
- `ithalat-masraf-dagitimi.md`
- `ithalat-maliyet-adjustment.md`

## Görev sırası

| Görev | İçerik |
|---|---|
| G-701 | ithalat şeması |
| G-702 | ithalat dosyası yaşam döngüsü |
| G-703 | purchase invoice line bağlama |
| G-704 | import expense |
| G-705 | masraf dağıtımı |
| G-706 | finalize + import cost |
| G-707 | inventory cost adjustment |
| G-708 | late cost/reverse/integrity |
| G-709 | gerçek PostgreSQL Faz 7 testleri |

## Faz bitiş ölçütü

Faz 7 dokümantasyonu K-114…K-130 kararlarıyla tamamlanmıştır. Kodlama/uygulama G-701…G-709 gerçek PostgreSQL kabul testleri geçmeden tamamlanmış sayılmaz.
