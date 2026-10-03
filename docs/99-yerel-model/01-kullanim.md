# Yerel model ile çalışma

## Uygulama yöntemi

1. Önce görevde `[KARAR GEREKİYOR]` veya karar günlüğünde o görevi etkileyen açık blokaj var mı kontrol et; varsa kod yazma.
2. Bir seferde tek G görevi ver.
3. Görev dosyasını tamamen bağlama koy.
4. Yalnız “Dokunulacak dosyalar” kapsamını değiştir.
5. Şemayı birebir uygula.
6. Kabul ölçütlerini gerçek PostgreSQL ile çalıştır.
7. Geçmeyen görevi bitmiş sayma ve sonraki G'ye geçme.

## Mimari kontrol

- PeriodModel aktif `period` bağlantısı.
- Period tablosunda company_id yok.
- BelongsToCompany/global scope yok; izolasyon fiziksel DB.
- Master şirket+dönem erişimi PeriodContext kurulmadan kontrol edilir.
- Master user cross-DB FK yok; user_id + user_name snapshot.
- Para Money + BCMath, float yok.
- Stok yalnız RecordStockMovement.
- document_date, idempotency, version ve gerekli lockForUpdate kullanılır.
- Dağıtım migrate:periods.
- Test gerçek PostgreSQL.
- Her türetilmiş/kopyalanmış veri integrity kontrolüne sahiptir.

Yanlış şirket verisi görülürse global scope ekleme; önce PeriodContext ve database_name doğrula.


## Güncel karar durumu

A-125 K-257, A-126 K-258, A-127 K-256 ile kapalıdır. Açık ürün kararı yoktur. Görev uygulanırken yine de yeni bir karar boşluğu görülürse kod/şema uydurulmaz; karar günlüğüne geri dönülür.
