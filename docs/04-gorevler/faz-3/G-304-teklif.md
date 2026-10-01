# G-304 — Teklif, revizyon ve iç onay

## Amaç

v65 teklif ekranlarını gerçek belge altyapısına bağlamak; revizyon geçmişini kaybetmeden iç onay ve siparişe dönüşüm akışını kurmak.

## Önkoşul

G-301, G-302, G-006, G-018.

## Dokunulacak dosyalar

- `app/Actions/Sales/CreateQuoteRevision.php`
- `app/Actions/Sales/SubmitQuoteForInternalApproval.php`
- `app/Actions/Sales/ApproveQuote.php`
- `app/Actions/Sales/SendQuoteToCustomerReview.php`
- `app/Actions/Sales/ConvertQuoteToSalesOrder.php`
- `app/Livewire/Sales/QuoteList.php`
- `app/Livewire/Sales/QuoteEditor.php`
- `resources/views/livewire/sales/quote-*.blade.php`
- `tests/Feature/Sales/QuoteFlowTest.php`

## Şema / Kod

v65 ekranı:

- list: Teklif No, Cari, Revizyon, Tarih, Geçerlilik, Para Birimi, Tutar, İç Onay, Müşteri Durumu, Durum
- tabs: Hareketler, Bilgiler, Konfigürasyon, Revizyonlar, Onaylar, Yorum/Not, Dosyalar, PDF, Timeline
- detail'de Requirement Snapshot
- eylemler: Kaydet, İç Onaya Gönder, Müşteriye Gönder, Yeni Revizyon, İç Onay, Onayla, Siparişe Dönüştür

## Durum akışı

Minimum durumlar:

```
draft
  -> internal_review
  -> customer_review
  -> approved
  -> converted
```

İç onay gerekmeyen kullanımda yetkili akış doğrudan müşteri inceleme durumuna geçebilir; `sales.quote.approve` gerektiren "İç Onay" eylemi permission ile korunur.

Teklif oluşturulurken `revision_no = 1` atanır. İlk kez `draft` dışına çıkarken ana teklif numarası üretilir. Draft kaydetmeleri numara tüketmez. Kullanıcı hiçbir zaman `Rev.0` görmez.

## Revizyon

Yeni revizyon:

1. kaynak quote posted/approved gibi mutate edilmez,
2. yeni `documents` satırı oluşturulur,
3. aynı `number` kullanılır,
4. `revision_no = max + 1` (ilk kayıt 1 olduğu için sonraki 2, 3... devam eder),
5. header + lines + configuration + requirements snapshot kopyalanır,
6. `document_relations.revision_of` yazılır,
7. yeni revizyon `draft` başlar.

Eski revizyon hiçbir alanı güncellenmez.

## Fiyat / hesap

- Başlangıç fiyatı G-26 çözüm zinciri.
- Konfigüratör fiyatı etkilemez.
- %20+ sapma uyarı+audit; blok yok.
- KDV/iskonto G-302.
- Satış para birimi TRY.

## Siparişe dönüştürme

Yalnız onaylanmış teklif siparişe dönüştürülür.

- yeni `sales_order` document,
- satırlar kopyalanır,
- order line.source_line_id = quote line.id,
- `quote_to_order` relation,
- teklif status = converted.

Teklif stok/cari/rezervasyon etkisi üretmez.


## Kurallar

- Revizyon update değil yeni kayıttır.
- sales.quote.approve Action seviyesinde kontrol edilir.
- Teklif stok/cari etkilemez.
- Konfigürasyon fiyatı değiştirmez.

## Kabul ölçütü

- Draft kayıt numara tüketmiyor.
- İlk review geçişi concurrency'de tek numara alıyor.
- İlk teklif `revision_no=1`; sonraki revizyonlar aynı number ile Rev.2, Rev.3... oluşturuyor.
- Eski revizyon değişmiyor.
- İç onay izinsiz kullanıcıda 403.
- Requirement/configuration snapshot eski revizyonda değişmiyor.
- Approved quote order'a kaynak satır bağlarıyla dönüşüyor.
- Quote hiçbir stok/cari hareket üretmiyor.

## İstem

> v65 teklif ekranlarını G-304'e göre uygula. Revizyonu aynı kaydı update ederek yapma; yeni document oluştur. sales.quote.approve iznini Action seviyesinde doğrula. Teklif stok/cari hareket üretmesin ve sipariş dönüşümünde source_line_id + quote_to_order relation yaz.
