# Ekran — Dönem Devri

**Bağlantılar:** Master + kaynak period + hedef period.

## Yetki

Dönem devri ayrı yetkidir. Kaynak kapatma / gerekirse yeniden açma gerekçeli ve auditli yapılır.

## Ön kontrol

Kapanmamış ay, açık teklif/sipariş, taslak, yoldaki transfer, karantina, negatif stok, son integrity sonucu, hedef DB'nin varlığı kontrol edilir.

Açık teklif/sipariş/taslak/yoldaki transfer/karantina **taşınmaz** ve kullanıcıya adet/tutar ile gösterilir.

## Önizleme

Taşınacak:
- aktif kartlar,
- bakiye/hareket ilişkisi bulunan gerekli pasif kartlar,
- stok açılışları,
- product_costs,
- cari açılış bakiyesi,
- kasa/banka açılışı,
- vadesi gelmemiş çek/senet.

Aynı şirket devrinde taşınan bütün kart ID+kodları ve taşınan stock_balance ID'leri korunur.

## Akış

1. Hedef DB oluştur.
2. `migrate:periods`.
3. Kartları aynı ID/kodla kopyala.
4. Stok bakiyelerini/devir kimliklerini koru; geçmiş stock_movements kopyalama.
5. Opening stock movements yaz; maliyet kapanış hareketli ortalaması.
6. product_costs.
7. cari/kasa/banka açılışları.
8. vadesi gelmemiş çek/senet.
9. sequence'leri `MAX(id)+1` ayarla.
10. `integrity:carry`.
11. kaynak period'u closed yap.
12. periods carry metadata doldur.
13. **Devri bitirdikten sonra** “Önceki dönemin kullanıcı yetkilerini yeni döneme kopyala?” adımı göster; kullanıcılar seçilebilir ve period erişim + dönemsel permission override kopyalanır.

## Geri alma

Hedefte yeni iş kaydı yoksa kontrollü olarak hedef DB kaldırılıp tekrar denenebilir. İş kaydı oluşmuşsa otomatik destructive rollback yapılmaz.
