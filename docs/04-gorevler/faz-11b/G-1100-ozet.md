# Faz 11b — Dönem devri

Yıl sonu kapanışı ve bir sonraki döneme geçiş. Master/dönem mimarisinin
gerektirdiği faz; tek veritabanlı bir sistemde bu faz olmazdı.

## Görevler

| No | Görev |
|---|---|
| G-1110 | Dönem devri action'ı ve ekranı |
| G-1111 | Devir öncesi kontrol listesi |
| G-1112 | Çok dönemli rapor altyapısı |
| G-1113 | Testler |

## Bitiş ölçütü

- [ ] Devir sonrası stok miktarları ve cari bakiyeleri korunuyor
- [ ] **Açılış birim maliyeti = kaynak kapanış hareketli ortalaması**
- [ ] Kartlar taşınmıyor (master'dalar)
- [ ] Açık sipariş/teklif taşınmıyor, kullanıcı uyarılıyor
- [ ] İkinci devir denemesi reddediliyor
- [ ] Geri alma çalışıyor
- [ ] Çok dönemli rapor sonrası aktif dönem değişmiyor
- [ ] `integrity:carry` yazıldı: hedef açılış toplamı = kaynak kapanış toplamı
