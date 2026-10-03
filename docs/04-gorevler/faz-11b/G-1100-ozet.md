# Faz 11b — Dönem devri

Bu faz aynı şirketin bir yıldan sonraki yıla fiziksel DB geçişini ve çok dönemli rapor sorgusunu kapsar.

## Görevler

| No | Durum | Görev |
|---|---|---|
| G-1110 | yazıldı/güncellendi | Dönem devri action + ekran |
| G-1111 | yazıldı/güncellendi | Devir öncesi kontrol listesi |
| G-1112 | yazıldı/güncellendi | Çok dönemli rapor altyapısı |
| G-1113 | yazıldı/güncellendi | Faz 11b bütünlük/eşzamanlılık testleri |

## Bitiş ölçütü

- [ ] Aktif kartlar + bakiye/hareket ilişkili gerekli pasif kartlar taşınıyor
- [ ] Taşınan bütün kart ID+kodları korunuyor
- [ ] Taşınan stock_balance ID'leri korunuyor
- [ ] Sequence'ler MAX(id)+1 yapılıyor
- [ ] Geçmiş stock_movements/documents taşınmıyor
- [ ] Açık teklif/taslak/yoldaki transfer taşınmıyor; **açık sales_order/purchase_order K-256 ile kalan miktar snapshot'ı olarak taşınıyor**, açık karantina kayıtları taşınıyor
- [ ] Açılış maliyeti kaynak kapanış moving average
- [ ] product_costs taşınıyor
- [ ] cash_accounts/bank_accounts kart ID+kodları korunuyor
- [ ] cari/kasa/banka açılışları korunuyor; geçmiş cash/bank movements taşınmıyor
- [ ] vadesi gelmemiş çek/senet taşınıyor
- [ ] integrity:carry farkta devri tamamlatmıyor
- [ ] kaynak period closed oluyor
- [ ] devir sonunda önceki dönem kullanıcı erişim/override'larını seçerek kopyalama soruluyor
- [ ] production recipe/revision ve channel account/listing/location mapping taşınıyor; açık production/subcontract order taşınmıyor
- [ ] K-256 ile taşınan açık kanal sales_order için gerekli channel_order_snapshot aktif provenance olarak taşınıyor; eski sync history taşınmıyor
- [ ] çok dönemli sorgu her DB'yi ayrı sorgulayıp PHP'de birleştiriyor
- [ ] sorgu sonunda aktif PeriodContext geri yükleniyor
