# G-114 — Faz 1 testleri


## Önkoşul
Faz 1 içindeki G-101…G-113 ve G-115

## Dokunulacak dosyalar
- Görevde tarif edilen migration/model/action/Livewire/test dosyaları; kapsam dışına çıkma.


## Şema / Kod

Bu bölümdeki mevcut şema örnekleri aşağıdaki kanonik mimari kurallarla birlikte uygulanır. Çelişkide kanonik kurallar üstündür.

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


## Kurallar

### Göreve özel kararlar
- Faz 1 bütün kartların period DB'de olduğunu schema seviyesinde doğrular.
- Hiçbir kart migration'ında company_id/BelongsToCompany/global scope kalmadığını test et.
- Dönem devri ID/kod sürekliliği için contract test ekle.


### Uygulama ayrıntıları
- Bu görev yeni business özellik üretmez; G-101…G-113 ve G-115 için entegrasyon/izolasyon kontratlarını topluca doğrular.
- Schema taramasında Faz 1 period tablolarında company_id, BelongsToCompany veya cross-DB FK kalmamalıdır.
- Aynı şirket dönem devri için kart ID+code sürekliliği; şirketler arası kopyada yeni hedef ID davranışı ayrı test edilir.
- Testler en az iki company ve ayrı period DB'lerle gerçek PostgreSQL üzerinde çalışır.

## Kabul ölçütü
```bash
./vendor/bin/pest
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
```


## İstem
> Yukarıdaki başlıkların her biri için Pest testi yaz. RefreshDatabase
> kullan. Gerekli factory'leri oluştur. İzolasyon ve yetki testlerini atlama.
