# G-114 — Faz 1 testleri

## Amaç
Faz 1'in doğru çalıştığını kanıtlamak.

## Yazılacak testler

### Kartlar
- Cari kodu otomatik üretiliyor, benzersiz, değiştirilemiyor
- Farklı şirkette aynı kod açılabiliyor (**izolasyon**)
- Vade boşsa şirket varsayılanı dönüyor
- Hareketi olan kart silinemiyor

### Ürün
- KDV dahil girilen fiyat hariç saklanıyor
- `cost.view` izni olmayan kullanıcıda maliyet kolonu **HTML'de yok**
- Barkodla arama çalışıyor

### Varyant
- Bir ürün iki gruba bağlanamıyor
- Aynı özellik kombinasyonu uyarı veriyor

### Set
- `min(bileşen/gerekli)` doğru hesaplanıyor
- Bir bileşen 0 olunca set 0
- Set içine set eklenemiyor

### Fiyat listesi
- Çakışan tarih aralığı reddediliyor
- Toplu yüzde güncelleme doğru

### Kopyalama
- İzinsiz kopyalama 403
- Kopya kayıt oluşuyor, kaynak değişmiyor
- Kod çakışması `-2` ile çözülüyor

### İçe aktarma
- 1000 satır hatasız
- Hatalı satır raporlanıyor
- Aynı dosya iki kez yüklenince mükerrer yok

## Kabul ölçütü
```bash
./vendor/bin/pest
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
```

## İstem
> Yukarıdaki başlıkların her biri için Pest testi yaz. RefreshDatabase
> kullan. Gerekli factory'leri oluştur. İzolasyon ve yetki testlerini atlama.
