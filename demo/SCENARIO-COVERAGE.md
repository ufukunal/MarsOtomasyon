# MarsProject 2026 – Modül Bazlı Demo Senaryoları

| Alan | Demo hacmi | Veri referansı | Planlanan durum varyasyonları |
|---|---:|---|---|
| Ürün | 250 | Ürün kodu DM26-Pxxxx, iki görsel, teknik PDF | Stok/üretim beslemeli, farklı IP, güç, boyut, fiyat |
| Cari | 100 | DM26-Cxxx | 65 müşteri, 25 tedarikçi, 10 fason |
| Cari adres | 200 | DM26-Cxxx | Fatura ve teslimat |
| Lokasyon | 10 | DM26-Lxx | Merkez, ithalat, hammadde, üretim, sevk, karantina, 2 şube, araç, fason |
| Stok | 750 depo+ürün bakiyesi | Açılış hareketi | Çift kayıt önleyici ilk açılış; üç depo |
| Teklif | 160 | DM26-Q-xxxx | Açık, onaylı hedef senaryolar |
| Satış siparişi | 250 | DM26-SO-xxxx | Tamamlandı, kısmi, açık hedef senaryolar |
| Sevk irsaliyesi | 160 | DM26-DIS-xxxx | Taslak ve sevk edilmiş hedef senaryolar |
| Satış faturası | 250 | DM26-SI-xxxx | Taslak ve muhasebeleşti hedef senaryolar |
| İade | 60 satış, 30 alış | DM26-SR / PR | Tam ve kısmi iade referansları |
| Satın alma | 100 sipariş + 100 mal kabul + 100 fatura | DM26-PO/GR/PI | Tedarik zinciri referansları |
| Ambar fişi | 100 | DM26-WS-xxxx | Giriş/çıkış, taslak/kesinleşmiş hedef |
| Transfer | 50 | DM26-TR-xxxx | Depolar arası açık/kısmi/tamamlanan hedef |
| Taşıma fişi | 100 | DM26-TS-xxxx | Kargo/araç, barkod takip, kırılgan cam, etiket |
| İthalat | 4 dosya | DM26-IMP-xxx | Taslak, transit, gümrük senaryosu |
| Konteyner | 4 | DM26-CONT-xxx | 20GP / 40HC, mühür, ağırlık, hacim, ETA |
| Koli | 120 | DM26-PKG | Konteyner–koli–ürün eşleştirme; 20 eşleşmemiş örnek |
| İthalat masrafları | 12 | DM26-IC | Değer/ağırlık/hacim bazlı dağıtım taslakları |
| Üretim reçetesi | 60 | DM26-BOM | Her reçeteye 3 bileşen, adet/birim dönüşümü |
| Üretim emri | 40 | DM26-MO | 15 fason, 25 iç üretim; tamamlandı/kısmi/taslak hedef |
| Tahsilat/ödeme | 120/100 | DM26-COL/PAY | Kısmi/tam ödeme hedef referansları |
| Finans örnekleri | 60 | DM26-FIN | Gider, avans, virman, kasa sayımı |

**Dikkat:** Bu satırların tamamı dosyalarda senaryo olarak hazırlanmıştır. UAT DB'ye seed edilecek parasal/stok etkili belgeler `draft` bırakılır. Bir iş akışına **gerçekten tamamlandı** demek için ilgili uygulama Action'ını ve entegrasyon kontrolünü çalıştırmak şarttır.


## İlave süreçler
- 4 fiyat listesi ve 1000 ürün fiyat satırı
- 25 varyant grubu ve 150 varyant ilişkisi
- 30 stok sayımı ve 180 sayım satırı; 90 rezervasyon; 30 karantina
- 360 konteyner bazlı ithalat masraf dağıtım taslağı (oran/tutar toplamları kontrol edilir)
- 25 üretim tamamlama hedefi, 75 bileşen tüketim hedefi
- 6 finans hesabı, 100 banka satırı, 25 çek/senet senaryosu
- 4 pasif pazaryeri hesabı, 400 listing, 100 pazar yeri siparişi, 100 event, 40 iade örneği
- 24 rapor özeti/preset senaryosu

*Ek alanlar CSV senaryo paketi olarak hazırlanmış olup kaynak Laravel şeması/Domain Actions ile entegrasyonları henüz yapılmamıştır.*
