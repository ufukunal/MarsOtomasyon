# Faz 2 — Stok

Sistemin en kritik hesabı burada. Satış, alış, üretim ve ithalat hep bu
katmana yazar. **Burada yapılan hata her rakama yayılır.**

## Görevler

| No | Görev | Önkoşul |
|---|---|---|
| G-201 | stock_movements + stock_balances | G-101, G-106 |
| G-202 | RecordStockMovement action (tek yazma noktası) | G-201 |
| G-203 | Hareketli ortalama maliyet + sapma uyarısı | G-202 |
| G-204 | Stok Durumu ekranı | G-202, G-0b2 |
| G-205 | Stok Hareketleri ekranı | G-202 |
| G-206 | Depo transferi | G-202 |
| G-207 | Ambar fişi | G-202 |
| G-208 | Stok sayımı ve elle onaylı fark | G-202 |
| G-209 | Karantina | G-202 |
| G-210 | Rezervasyon altyapısı | G-202 |
| G-211 | Açılış bakiyesi aktarımı | G-202, G-112 |
| G-212 | Faz 2 testleri | hepsi |

## Bitiş ölçütü

- [ ] Stok yalnızca `RecordStockMovement` üzerinden değişiyor
- [ ] Hareketli ortalama doğru hesaplanıyor, çıkışta değişmiyor
- [ ] ±%25 sapmada uyarı çıkıyor, engellemiyor, loglanıyor
- [ ] Negatif stok ürün bazında izinli/engelli çalışıyor
- [ ] Transfer maliyeti değiştirmiyor
- [ ] Sayım farkı elle onaylanmadan hareket oluşmuyor
- [ ] Karantinadaki mal satılamıyor, rezerve edilemiyor
- [ ] `stock:verify` komutu fark bulmuyor
- [ ] Kapalı döneme hareket yazılamıyor
