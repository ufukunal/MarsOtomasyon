# MarsOtomasyon

Avize toptan ticareti için firmaya özel ERP. Ön muhasebe, stok, satış, alış,
iade, ithalat, basit üretim, fason ve e-ticaret entegrasyonlarını kapsar.

**Kapsam dışı:** genel muhasebe, e-belge, parti/lot takibi, bütçe, amortisman,
ileri üretim (rota, iş merkezi, kapasite, OEE, MRP).

## Bu depo nasıl kullanılır

Kodun büyük kısmı **yerel bir dil modeli** tarafından yazılacaktır. `docs/`
altındaki belgeler bu amaçla yazılmıştır: her görev dosyası **tek başına
yeterlidir**, model başka dosyaya bakmadan görevi tamamlayabilmelidir.

| Klasör | İçerik |
|---|---|
| `docs/00-genel` | Teknoloji, mimari, isimlendirme, sözlük |
| `docs/01-veri-modeli` | Tablo başına bir dosya |
| `docs/02-is-kurallari` | Konu başına iş kuralları |
| `docs/03-ekranlar` | Ekran başına: alanlar, butonlar, etki zinciri |
| `docs/04-gorevler` | Sıralı görevler — kod bunlardan yazılır |
| `docs/05-karar-gunlugu` | Kararlar ve gerekçeleri |
| `docs/99-yerel-model` | Yerel model kılavuzu ve istem şablonları |

## Çalışma sırası

1. `docs/99-yerel-model/01-kullanim.md` oku
2. `docs/04-gorevler/faz-0/` içindeki görevleri **numara sırasıyla** yap
3. Her görevin kabul ölçütünü çalıştır; geçmeden sonrakine geçme

## Kurulum

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```
