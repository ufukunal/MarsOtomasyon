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


## Güncel açık blokajlar

- A-125: non-stock service purchase_invoice satır modeli.
- A-126: fason hizmet maliyetinin kısmi completion'lara dağıtım yöntemi.

Bu iki karar kapanmadan ilgili Faz 4/7/8 görevlerinde çözüm uydurulmaz.
