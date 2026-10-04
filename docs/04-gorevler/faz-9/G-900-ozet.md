# G-900 — Faz 9 E-ticaret özeti

## Amaç

Faz 9 kanal hesapları, listing mapping, ürün/içerik/stok/fiyat outbound sync, order/cancel/return inbound akışı, webhook/polling, hata yönetimi ve dönemler arası idempotency davranışını tamamlar.

## Kilit kararlar

- K-163…K-201.

## Veri modeli

- `42-ecommerce-channels.md`

## İş kuralları

- `48-kanal-listing-ve-yayin.md`
- `49-kanal-stok-ve-fiyat-sync.md`
- `50-kanal-siparis-cancel-return.md`
- `51-kanal-webhook-sync-integrity.md`

## Ekranlar

- kanal-hesaplari.md
- kanal-urun-listing.md
- kanal-sync-merkezi.md
- kanal-siparisleri.md
- kanal-stok-fiyat-onizleme.md

## Görev sırası

| Görev | İçerik |
|---|---|
| G-901 | kanal hesapları + adapter sözleşmesi |
| G-902 | listing mapping/yayın |
| G-903 | içerik/görsel/kategori sync |
| G-904 | stok sync |
| G-905 | fiyat sync |
| G-906 | inbound order import |
| G-907 | cancel/return/shipment status |
| G-908 | webhook/polling/retry/history |
| G-909 | dönem devri/external registry/integrity |
| G-910 | gerçek PostgreSQL Faz 9 testleri |

## Faz bitiş ölçütü

Faz 9 dokümantasyonu K-163…K-201 ile tamamlanır. Kodlama/uygulama G-901…G-910 gerçek PostgreSQL kabul testleri geçmeden tamamlanmış sayılmaz.
