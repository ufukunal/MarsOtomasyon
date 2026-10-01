# Yerel model ile çalışma

## Uygulama yöntemi

1. Bir seferde tek G görevi ver.
2. Görev dosyasını tamamen bağlama koy.
3. Yalnız “Dokunulacak dosyalar” kapsamını değiştir.
4. Şemayı birebir uygula.
5. Kabul ölçütlerini gerçek PostgreSQL ile çalıştır.
6. Geçmeyen görevi bitmiş sayma ve sonraki G'ye geçme.

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
