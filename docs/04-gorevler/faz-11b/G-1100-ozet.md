# Faz 11b — Dönem devri

Bu faz aynı şirketin bir yıldan sonraki yıla fiziksel DB geçişini ve çok dönemli rapor sorgusunu kapsar.

## Görevler

| No | Durum | Görev |
|---|---|---|
| G-1110 | yazıldı/güncellendi | Dönem devri action + ekran |
| G-1111 | planlı | Devir öncesi kontrol listesi ayrıntıları |
| G-1112 | yazıldı/güncellendi | Çok dönemli rapor altyapısı |
| G-1113 | planlı | Faz 11b bütünlük/eşzamanlılık testleri |

## Bitiş ölçütü

- [ ] Aktif kartlar + bakiye/hareket ilişkili gerekli pasif kartlar taşınıyor
- [ ] Taşınan bütün kart ID+kodları korunuyor
- [ ] Taşınan stock_balance ID'leri korunuyor
- [ ] Sequence'ler MAX(id)+1 yapılıyor
- [ ] Geçmiş stock_movements/documents taşınmıyor
- [ ] Açık teklif/sipariş/taslak/yoldaki transfer/karantina taşınmıyor
- [ ] Açılış maliyeti kaynak kapanış moving average
- [ ] product_costs taşınıyor
- [ ] cari/kasa/banka açılışları korunuyor
- [ ] vadesi gelmemiş çek/senet taşınıyor
- [ ] integrity:carry farkta devri tamamlatmıyor
- [ ] kaynak period closed oluyor
- [ ] devir sonunda önceki dönem kullanıcı erişim/override'larını seçerek kopyalama soruluyor
- [ ] çok dönemli sorgu her DB'yi ayrı sorgulayıp PHP'de birleştiriyor
- [ ] sorgu sonunda aktif PeriodContext geri yükleniyor
