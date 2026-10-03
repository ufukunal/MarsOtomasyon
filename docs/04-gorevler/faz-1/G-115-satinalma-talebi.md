# G-115 — Satınalma talebi + teklif toplama kapsam sözleşmesi

## Amaç
K-060 ile Faz 1 planına eklenen basit satınalma talebi + tedarikçi teklif toplama ihtiyacını **Faz 4 belge çekirdeğini tekrar kurmadan** kanonikleştirmek.

Bu görev production tablo/Action görevi değildir. Gerçek `purchase_request`, `supplier_quote`, karşılaştırma ve siparişe dönüşüm uygulaması Faz 4 **G-401…G-404** içinde ortak `documents/document_lines` çekirdeğiyle yapılır.

## Önkoşul
G-104 (cari), G-106 (ürün), K-060.

## Dokunulacak dosyalar
- `docs/00-genel/06-proje-plani.md`
- `docs/03-ekranlar/satinalma-talebi.md`
- `docs/03-ekranlar/tedarikci-teklif-karsilastirma.md`
- Faz 4 G-401…G-404 görev sözleşmeleri

## Şema / Kod
Bu görev **yeni production schema üretmez**.

Yasak:
- ayrı `purchase_requests` / `supplier_quotes` tablo ailesi kurmak,
- Faz 4 gelmeden `purchase_order` üretmek,
- ortak documents altyapısına paralel ikinci satınalma belge çekirdeği oluşturmak.

Faz 4 kanonik belge tipleri:
- `purchase_request`
- `supplier_quote`
- `purchase_order`

## Kurallar
- K-060 kapsamı korunur: basit talep + teklif toplama vardır.
- K-086…K-091 Faz 4'te davranışı somutlaştırır ve bu görevin eski/erken varsayımlarına üstün gelir.
- Otomatik kazanan/puanlama yoktur.
- Talep→teklif→sipariş business implementation sahibi Faz 4'tür.
- G-115 duplicate schema/scope oluşmasını önleyen sınır sözleşmesidir.

## Kabul ölçütü
- Faz 1 kaynaklı ayrı `purchase_requests` / `supplier_quotes` production tablo planı yok.
- G-402 purchase_request'i ortak documents üzerinde uygular.
- G-403 supplier_quote + selection modelini ortak documents üzerinde uygular.
- G-404 purchase_order üretiminin tek sahibidir.
- K-090'a aykırı otomatik winner davranışı yoktur.

## İstem
> K-060 satınalma talebi + teklif toplama kapsamını koru; production schema ve sipariş dönüşümünü Faz 4 G-401…G-404'e bırak. Ayrı purchase_requests/supplier_quotes tablo ailesi kurma.
