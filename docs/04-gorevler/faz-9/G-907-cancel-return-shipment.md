# G-907 — Kanal cancel, return ve shipment status

## Amaç

External cancel/return eventlerini mevcut satış/iade çekirdeklerine bağlamak ve shipment status'u kanala geri göndermek.

## Önkoşul

G-906, Faz 3 partial fulfillment, Faz 6 sales_return.

## Dokunulacak dosyalar

- ImportChannelCancellation
- ImportChannelReturn
- ChannelShipmentStatusSync
- cancel/return/shipment tests

## Şema / Kod

Cancel:

- yalnız kalan fulfill edilmemiş quantity,
- existing cancelled_quantity.

Return:

- draft sales_return,
- channel source metadata.

Shipment:

- adapter-specific outbound mapping.

## Kurallar

- Fulfilled miktar cancel ile geri alınmaz.
- Return otomatik post edilmez.
- Sales return quarantine/cari/stok Faz 6 kuralı.
- Ayrı cargo API yok.
- Shipment push retry/history ortak altyapıyı kullanır.

## Kabul ölçütü

- Partial cancel doğru kalan miktarı iptal ediyor.
- Shipped quantity cancel olmuyor.
- Return draft sales_return oluşturuyor.
- Post öncesi stok/cari etkisi yok.
- Shipment status adapter'a gidiyor.
- Duplicate external event ikinci business action üretmiyor.

## İstem

> K-188…K-191 cancel/return/shipment entegrasyonunu mevcut çekirdekler üzerinden uygula.
