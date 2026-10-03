# Ekran — Dönem Devri

**Bağlantılar:** Master + kaynak period + hedef period.

## Yetki

Dönem devri ayrı yetkidir. Kaynak kapatma / gerekirse yeniden açma gerekçeli ve auditli yapılır.

## Ön kontrol

Kapanmamış ay, açık teklif, taslak, **aktarılacak açık satış/satınalma siparişleri**, yoldaki transfer, karantina, negatif stok, son integrity sonucu ve hedef DB'nin varlığı kontrol edilir.

Açık teklif/taslak ve yoldaki transfer taşınmaz. **Açık sales_order ve purchase_order K-256 gereği yalnız kalan miktarlarıyla yeni period'a aktarılır.** **Açık karantina blok değildir; open quantity/snapshot ile yeni döneme taşınır.** Açık production/subcontract order ve yoldaki transfer carry tamamlanmasını bloklar.

## Önizleme

Taşınacak:
- aktif kartlar (cari, ürün, lokasyon, birim/kategori/marka, varyant/set/konfigürasyon, fiyat listesi, **cash_accounts, bank_accounts**),
- bakiye/hareket ilişkisi bulunan gerekli pasif kartlar,
- stok açılışları,
- product_costs,
- cari açılış bakiyesi,
- kasa/banka açılışı,
- vadesi gelmemiş çek/senet,
- açık quarantine kayıtları,
- aktif production recipe/revision,
- subcontractor location fiziksel stokları,
- channel listing/location mapping,
- açık satış/satınalma siparişlerinin kalan miktar snapshot'ları,
- açık satış siparişlerinin aktif rezervasyonları.

Aynı şirket devrinde taşınan bütün kart ID+kodları ve taşınan stock_balance ID'leri korunur.

## Akış

1. Hedef DB oluştur.
2. `migrate:periods`.
3. Kartları aynı ID/kodla kopyala; `cash_accounts` ve `bank_accounts` kart ID/kodları da korunur.
4. Stok bakiyelerini/devir kimliklerini koru; geçmiş stock_movements kopyalama.
5. Opening stock movements yaz; maliyet kapanış hareketli ortalaması.
6. product_costs.
7. cari açılış hareketleri ile kasa/banka kapanış bakiyelerini yeni dönemde **opening movement** olarak yaz; eski cash/bank movement geçmişini kopyalama.
8. vadesi gelmemiş çek/senet.
9. açık quarantine kayıtlarını taşı.
10. production recipe/revision ve channel listing/location mapping taşı; açık production/subcontract order ve channel order/sync history taşıma.
11. Açık sales_order/purchase_order için yalnız kalan miktarlardan target-year yeni sipariş snapshot'ları oluştur; sales-order aktif rezervasyonlarını location bazında yeniden kur; cross-period provenance yaz.
12. sequence'leri `MAX(id)+1` ayarla.
13. `integrity:carry`.
14. kaynak period'u closed yap.
15. periods carry metadata doldur.
16. **Devri bitirdikten sonra** “Önceki dönemin kullanıcı yetkilerini yeni döneme kopyala?” adımı göster; kullanıcılar seçilebilir ve period erişim + dönemsel permission override kopyalanır.

## Geri alma

Hedefte yeni iş kaydı yoksa kontrollü olarak hedef DB kaldırılıp tekrar denenebilir. İş kaydı oluşmuşsa otomatik destructive rollback yapılmaz.
