# G-404 — Satınalma siparişi

## Amaç

Doğrudan veya teklif seçimlerinden üretilebilen purchase_order yaşam döngüsünü ve kısmi kalan hesabını uygulamak.

## Önkoşul

G-401…G-403, Faz 3 source_line lineage altyapısı.

## Dokunulacak dosyalar

- purchase order create/detail bileşenleri
- `ConfirmPurchaseOrder`
- `CancelPurchaseOrderRemainder`
- order→goods_receipt / order→purchase_invoice dönüşüm Action'ları
- purchase order testleri

## Şema / Kod

purchase_order mevcut documents/document_lines kullanır.

- contact_id zorunlu.
- currency/exchange_rate snapshot.
- cancelled_quantity ortak document_lines alanıdır.
- seçimden üretimde source supplier_quote line'a bağlanır.

Kalan:

```
ordered - cancelled - received - direct_invoiced
```

## Kurallar

- Sipariş stok/cari/maliyet etkisi üretmez.
- Kısmi teslim/fatura desteklenir.
- cancelled_quantity sonrası miktar tekrar kullanılamaz.
- quantity küçültülmez.
- confirmed sipariş immutable'dır.
- remaining hesabı child satırlardan türetilir.

## Kabul ölçütü

- Direct purchase_order oluşturulabiliyor.
- Quote selections supplier'a göre doğru ayrı order'lara dönüşüyor.
- Kısmi kalan hesabı doğru.
- Kalanı iptal child toplamını aşmıyor.
- İptal edilmiş miktar receipt/invoice'a gidemiyor.
- Concurrent iki fulfillment kalan miktarı aşamıyor.

## İstem

> purchase_order akışını K-086/K-088 ve iş kuralı 34'e göre uygula. Fulfillment için ayrı delivered/invoiced gerçek kolon ekleme.
