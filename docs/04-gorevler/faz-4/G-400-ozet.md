# G-400 — Faz 4 Alış özeti

## Amaç

Faz 4'te satınalma talebi, tedarikçi teklif toplama, satınalma siparişi, mal kabul ve alış faturası akışını Faz 3'teki ortak ticari belge çekirdeği üzerinde kurmak.

Faz 4 ortak `documents`, `document_lines`, `document_relations`, `contact_transactions`, stok ve maliyet altyapısını tekrar kurmaz.

## Kilit kararlar

- K-086: alış belge ailesi esnek; ara adımlar zorunlu değil.
- K-087: goods_receipt operasyon kaydı; stok+cari+maliyet etkisi purchase_invoice posting'inde.
- K-088: kısmi teslim/faturalama esnek.
- K-089: tedarikçi ödeme akışı Faz 5'te.
- K-090: teklif karşılaştırma belge + satır bazlı; otomatik kazanan yok.
- K-091: ayrı teklif approval state/eşik yok; seçim izin tabanlı.

Açık Faz 4 A kararı yoktur.

## Faz 4 kaynakları

### Veri modeli

- `docs/01-veri-modeli/30-documents.md`
- `31-document_lines.md`
- `32-contact_transactions.md`
- `33-document_relations.md`
- `35-purchase_quote_selections.md`
- stok/maliyet için mevcut `20-stock_movements.md`, `21-stock_balances.md`, `22-product_costs.md`

### İş kuralları

- `28-belge-hesaplama.md`
- `29-belge-yasam-dongusu.md`
- `30-kismi-islem.md`
- `09-maliyet.md`
- `25-birim-donusumu.md`
- `32-satinalma-akisi.md`
- `33-alis-posting-ve-maliyet.md`
- `34-alis-kismi-islem-ve-teklif.md`

### Ekranlar

- `satinalma-talebi.md`
- `tedarikci-teklif-karsilastirma.md`
- `satinalma-siparisi-detay.md`
- `mal-kabul-detay.md`
- `alis-faturasi-detay.md`

v65 içinde hazır satınalma ekranı yoktur; bu ekranlar v65'in genel UI dilini izler, prototipte olmayan route/tab davranışı kaynak gibi gösterilmez.

## Belge tipleri

- `purchase_request`
- `supplier_quote`
- `purchase_order`
- `goods_receipt`
- `purchase_invoice`

## Etki matrisi

| Tür | Stok | Cari | Maliyet | Kasa/Banka |
|---|---|---|---|---|
| purchase_request | yok | yok | yok | yok |
| supplier_quote | yok | yok | yok | yok |
| purchase_order | yok | yok | yok | yok |
| goods_receipt | yok | yok | yok | yok |
| purchase_invoice | in/purchase | supplier credit | moving average | yok |

Dövizli purchase_invoice'da stok unit_cost ve cari ledger tutarı frozen exchange_rate ile şirket temel para birimine çevrilir. Orijinal currency/tutar/kur belge üzerinde snapshot kalır.

## Teklif toplama

- Bir request'e birden fazla supplier_quote bağlanabilir.
- Belge veya satır bazlı seçim yapılabilir.
- Seçim gerçek kaynağı `purchase_quote_selections` tablosudur.
- Belge bazlı seçim de her request line için seçim satırı üretir.
- Satır bazlı seçim purchase_order üretirken supplier'a göre gruplanır.
- Otomatik winner/puanlama yok.
- `purchasing.quote.select` izni gerekir.
- Ayrı approval state/tutar eşiği yok.
- Seçim period audit'e yazılır.

## Kısmi işlem

Purchase order remaining:

```
ordered - cancelled - received - direct_invoiced
```

Goods receipt invoice remaining:

```
receipt quantity - effective posted purchase_invoice child total
```

Bir receipt bölünebilir; aynı supplier + currency + uyumlu alış koşullarındaki receipt'ler bir faturada birleşebilir.

## Maliyet

- Tek yöntem hareketli ortalama.
- PHP float yok; Money/BCMath.
- Stok temel birimde.
- Purchase invoice inventory cost KDV hariç net alış bedelinin frozen kurla temel para birimine çevrilip base_quantity'ye bölünmesidir.
- ±%25 sapma uyarı+audit; blok değil.
- Eşik Master `companies.cost_deviation_threshold`.

## Görev sırası

| Görev | İçerik |
|---|---|
| G-401 | Faz 4 belge tipleri, relation tipleri, purchase_quote_selections |
| G-402 | satınalma talebi |
| G-403 | tedarikçi teklif toplama ve karşılaştırma |
| G-404 | satınalma siparişi |
| G-405 | mal kabul / alış irsaliyesi |
| G-406 | alış faturası posting + maliyet + döviz |
| G-407 | kısmi alış faturalama / lineage |
| G-408 | ters kayıt + integrity |
| G-409 | gerçek PostgreSQL bütünleşik testler |

## Faz bitiş ölçütü

Dokümantasyon seti yazılmıştır. Kodlama/uygulama Faz 4 tamamlanmış sayılmaz; G-401…G-409 kabul ölçütlerinin gerçek PostgreSQL üzerinde geçmesi gerekir.

Faz 5'e geçiş ayrıca kullanıcı onayıyla yapılır.
