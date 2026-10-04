# G-402 — Satınalma talebi

## Amaç

Tedarikçi zorunluluğu olmadan ürün ihtiyaçlarını toplayan purchase_request yaşam döngüsünü uygulamak.

## Önkoşul

G-401 ve ortak belge hesap/numara altyapısı.

## Dokunulacak dosyalar

- purchase request create/detail Livewire bileşenleri
- `ConfirmPurchaseRequest` Action
- request→quote / request→order dönüşüm Action'ları
- purchasing request feature testleri

## Şema / Kod

purchase_request mevcut documents/document_lines kullanır.

- contact_id null olabilir.
- draft numarasız olabilir.
- confirm geçişinde numara üretilir.
- satırlar ürün + birim + quantity + description taşır.
- conversion snapshot ortak satır kuralını kullanır.

## Kurallar

- Stok/cari/maliyet etkisi yok.
- Confirm sonrası immutable.
- Teklif toplama veya doğrudan purchase_order akışı K-086 gereği opsiyoneldir.
- Dönüşen satırlarda source_line ancestry korunur.
- State-changing action idempotency key taşır.

## Kabul ölçütü

- Tedarikçisiz talep kaydedilebiliyor.
- Confirm numarayı tek kez üretiyor.
- Confirm tekrarında duplicate numara/belge oluşmuyor.
- Talep stock/contact transaction üretmiyor.
- request→supplier_quote ve request→purchase_order child satırlar doğru source_line_id taşıyor.
- Stale version update reddediliyor.

## İstem

> purchase_request akışını mevcut document çekirdeği üzerinde uygula. Tedarikçi zorunlu yapma; stok/cari etkisi ekleme.
