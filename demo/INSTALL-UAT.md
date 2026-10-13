# MarsProject Rich Demo — mars-uat-fresh 18082 yükleme yönergesi

Bu paketin içinde `deploy/uat-fresh-import.sh`, `deploy/preflight.php`, `deploy/postflight.php` ve `laravel/database/seeders/UatRichDemoSeeder.php` bulunur. Git/GitHub CLI/PHP kurulumu gerekmez: yalnız Docker Compose, Python 3, sudo ve çalışan `mars-uat-fresh` uygulama görüntüsü gereklidir.

**Kritik önkoşul:** `/kurulum` sihirbazı tamamlanmış, `mars_uat_master` içinde *aktif* `mars_uat_demo_2026` dönemi oluşturulmuş ve dönem migration'ları tamamlanmış olmalı. Sadece `/kurulum` URL'sinin HTTP 200 dönmesi yeterli değildir. `--check` bunu otomatik doğrular; eksikse herhangi bir veritabanına yazmaz.

## Yükleme

1. ZIP dosyasını Windows'ta indir ve WinSCP aracılığıyla `ufuk@192.168.239.132` üzerinde `/home/ufuk/MarsProject_UAT_250Urun_100Cari_Kapsamli_Demo_Set_2026.zip` konumuna kopyala. Canlı sunucuda Git/gh kurulmayacak.
2. PuTTY'ye aşağıdaki üç komutu sırayla uygula:

```bash
mkdir -p "$HOME/mars-demo-rich-stage"
python3 -m zipfile -e "$HOME/MarsProject_UAT_250Urun_100Cari_Kapsamli_Demo_Set_2026.zip" "$HOME/mars-demo-rich-stage"
bash "$HOME/mars-demo-rich-stage/deploy/uat-fresh-import.sh" --check
```

3. `PASS: READ-ONLY CHECK DONE` gördükten sonra, veri yüklemek için:

```bash
bash "$HOME/mars-demo-rich-stage/deploy/uat-fresh-import.sh" --apply
```

`--apply` önce yalnız `mars_uat_demo_2026` veritabanını `~/mars-uat-fresh-backups/` dizinine pg_dump -Fc ile yedekler. Sonra aynı Docker imajından **geçici one-off** web konteyneri oluşturup importer PHP kodunu ve veri paketini **salt okunur bind mount** olarak bağlar. Kaynak uygulama imajını değiştirmez, PostgreSQL/Valkey'i yeniden başlatmaz, migration çalıştırmaz, volume silmez, `mars-uat-lab` veya PROD veritabanlarına dokunmaz.

## Kontrol kapsamı

İçe aktarma sınıfı şu varlıkları **gerçek UAT period tablolarına** yazar: 250 ürün, 100 cari, 10 lokasyon, 750 açılış stoğu/hareketi, 1210 **taslak** belge ve 2740 satır, 100 ambar fişi, 50 transfer, 100 taşıma fişi, 4 ithalat, 4 konteyner, 120 ürün kolisi, 12 ithalat masraf kalemi, 60 üretim reçetesi, 40 **taslak** üretim emri, 500 ürün resmi ve 250 teknik PDF (toplam 750 ek). İlgili satır kayıtları da eklenir. Demo `scenario_status` ile `database_seed_status` farklıdır: **fatura/muhasebe ve stok kesinleştirmeleri doğrudan SQL ile taklit edilmez**.

Diğer CSV'ler (fiyat listesi, varyantlar, sayım/rezervasyon/karantina, ithalat masraf dağıtımları, üretim tamamlama/tüketim, finans, pazaryerleri, rapor presetleri) kaynak veri olarak disk üzerinde hazır olur **ama bu betikte DB'ye aktarılmaz**. Bunların domain iş akışları ayrıca çalıştırılmalıdır; kullanıcı arayüzünde görünmüş sayılmazlar.

## Güvenlik

- Her adım `mars-uat-fresh` proje/konteyner etiketlerini, DB adını, dönem yılını ve health değerlerini kontrol eder.
- `--check` veritabanını değiştirmez. `--apply` mevcut ürün/kartları silmez ve aynı belge numarasını iki kez oluşturmaz.
- **Asla** `down -v`, `volume rm`, `migrate:fresh`, `truncate`, `drop` yapma.
- Sentetik teknik föyler, temsili çizimler ve örnek cari kayıtları ticari üretim verisi değildir.
- Hata çıkarsa terminal çıktısını paylaş; şifre/.env içeriğini paylaşma.
