# MarsProject satış / alım referans ekran entegrasyonu — 2026-10-17

## Onaylı referans

Kullanıcının paylaştığı dört ekran görseli:
Alış Teklifi, Satınalma Siparişi, Alış İrsaliyesi ve Alış Faturası.
Aynı görsel iskelet satışın dört türüne de uygulanmıştır.
Bu dosyadaki ekran örnekleri **demo veridir**; PHP tarafında örnek ürünler veya
örnek cari hesaplar oluşturulmaz.

## Ekran kapsamı

- Satış: `quote`, `sales_order`, `dispatch`, `sales_invoice`.
- Alım: `purchase_quote`, `purchase_order`, `purchase_dispatch`, `supplier_invoice`.
- `goods_receipt` (Mal Kabul) farklı operasyon: orijinal kaynak-kabul
  iş akışı ve ekranının form davranışı korunmuştur.

## Aynı tasarım iskeleti

1. Sol başlık, açıklama, belge kodu; sağ durum, gerçek Yazdır ve Kaydet.
2. Beş kolonlu Belge Bilgileri kartı, type-specific alanlar, kaynak belge
   ilişkilerinin yetki denetimli gösterimi, belge numarası ve durum adımları.
3. Ürün ve Hizmetler kartı: gerçek server-side kod/barkod/ad araması,
   hizmet satırı ekleme, toplu KDV seçimi ve özel KDV seçenekleri.
4. Tam on kolon: No, Ürün Kodu / Ürün Adı, Miktar, Birim, Fiyat,
   İskonto %, KDV %, Net Tutar, KDV Dahil ve İşlem.
5. Enter/Tab kullanımı ve tam sunucu durumuyla hesaplanan otomatik
   boş satır. Kullanıcı araması sonucunu alana kadar yanlış ilk ürün seçilmez.
6. Beşli toplam: Ara Toplam, Satır İndirimi, Ek İskonto (Tutar),
   KDV ve Genel Toplam. **Ek iskonto alanı tek kez form içinde yer alır.**
7. Alt bar: kalem sayısı, sadece kaydedilmemiş yeni formda sıfırlama,
   kayıtlı taslak için gerçek onay işlemlerine bağlantı ve Kaydet.

## Korumaya devam edilen iş kuralları

- Server-side belge hesaplayıcı, belge genel/ek iskonto dağıtımı,
  çok oranlı KDV, `DocumentTotals`, stok ve cari defter hareketleri korunur.
- Satış fiyatı `PriceResolver` üzerinden; manuel satış fiyatları
  özellikle talep edilmedikçe topluca yeniden hesaplanmaz.
- Alış fiyatları yeni ürün seçiminde 0 başlar ve operatör tarafından girilir;
  alış fiyatı otomatik olarak önceki satırdan taşınmaz.
- Veri kaydetme `SaveSalesDocumentDraft` ve
  `SavePurchaseDocumentDraft` aksiyonları üzerinden yapılır.
- Onay, sevk, kesinleştirme ve ters kayıt işlemleri önceki workflow'da kalır.
- Fatura ve irsaliyenin kaynak sipariş/mal kabul seçimi bir mock
  dropdown değildir: mevcut `DocumentRelation` bağlantıları gösterilir.
- Mal Kabul sırasında fiili stok girişinin ayrı ve gerçek işlem olması korunur.
- Kaydedilmemiş belgeye Yazdır sahte düğmesi eklenmez; gerçek yazdırma
  kaydedilen belge kimliğiyle `documents.print` rotasını kullanır.

## Kaynak değişiklikleri

- `config/document_prototype.php`: sekiz türün tasarım metaverileri.
- `resources/views/components/v65/document-prototype-{heading,stage,toolbar,footer}.blade.php`:
  ortak kontrol ve alanlar.
- `resources/views/livewire/{sales,purchases}/document-editor.blade.php`:
  belge kartı, ürün tablosu, tutar alanları ve gerçek workflow'a bağlantı.
- `app/Livewire/Concerns/WithPrototypeDocumentControls.php`:
  sunucu araması, hızlı ürün ekleme, KDV oranı seçimi ve yeni taslağı sıfırlama.
- İki `Base*DocumentEditor`: ortak trait ve türüne özgü sıfırlama.
- `resources/views/components/v65/document-summary.blade.php`:
  kompakt 5 toplam + ek iskonto inputu.
- `resources/views/components/v65/document-sources.blade.php`:
  alış teklifi/alış irsaliyesi kaynakları için gerçek ve yetkili bağlantılar.
- `resources/css/v65-shell.css`, `resources/js/app.js`:
  yalnız bu ekranlarda CSS ve klavye.
- `tests/Unit/UI/MarsPurchaseSalesReferenceScreensTest.php`
  ve eski statik kalite testleri: ekran ve kaynak kontratları.

## Kabul ve dağıtım sınırları

Bu çalışma kaynak kod uygulamasıdır. GitHub merge, UAT/PROD Docker dağıtımı
ve gerçek veritabanı doğrulaması birbirinden ayrıdır.

- Değişen Blade yönergeleri ve PHP delimiter'ları kaynak düzeyinde
  denetlenir; fakat bu, `php -l` veya `php artisan view:cache`
  çalıştırılmış olduğu anlamına gelmez.
- Source-quality workflow repo gereği `workflow_dispatch` ile manueldir.
- Gerçek UAT ekranları, mobil yerleşim, baskı, stok ve muhasebe
  regresyonları ayrı test kapısıdır.
- `ui_meta` içeren period migration'ın veritabanında bulunması,
  güncel UAT web container'ının aktarılmasından önce gereklidir.
- Onaysız production migration / Docker volume değişimi yapılmaz.
