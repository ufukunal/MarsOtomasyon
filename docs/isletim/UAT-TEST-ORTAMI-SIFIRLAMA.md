# MarsProject — workflow'suz TEST/UAT düzeni

## Karar
2026-10-12 tarihinde `.github/workflows/` altındaki **14 GitHub Actions workflow dosyası main'den kaldırıldı**. Eski Actions çalıştırma geçmişi GitHub üzerinde kalır; mevcut sıradaki/çalışan GitHub Actions run'larını bu dosya silme işlemi iptal etmez. Test kodları (`tests/**`), CI yardımcı scriptleri (`scripts/ci/**`) ve kaynak kod korunmuştur.

**Bu değişiklik sunuculara dağıtım değildir.** GitHub Actions otomatik Docker imaj yayını DURDU. Yeni commit için `mars update` otomatik yeni GHCR imajı bulamaz; yeni dağıtım prosedürü ayrı onayla kurulana kadar kullanılmamalıdır. Mevcut çalışan UAT web/PostgreSQL/Valkey verilerine dokunulmadı.

## Katmanlar ve roller
- **CI/runner sunucusu**: PHP 8.4, Composer, Node 22, Docker builder, testler. CI üzerinde PostgreSQL/Valkey kurulmaz. PHP bağımlılıkları `composer install` ile yüklenir.
- **UAT Lab**: mevcut `mars-uat-lab` Compose projesi, kendi `mars_uat_master` ve `mars_uat_demo_2026` veritabanları, `mars_uat_app` kullanıcısı ve kendi Valkey servisi. PROD veritabanına bağlanılmaz.
- **PROD**: yazılımın gerçek verileri/servisleri; test veya migration hedefi OLAMAZ.
- **UAT erişimi**: sadece Tailscale `100.127.235.30:18081`. Port köprüsü host üzerinde ayrı servis; Docker Compose web portu doğrudan host'a açılmaz.

## Önce salt okunur kontroller
CI runner'da, repo kökünden:
```bash
git status --short
php -v
composer --version
node --version
npm --version
docker version
```
UAT sunucusunda:
```bash
sudo docker ps --filter name=mars-uat-lab
sudo docker inspect mars-uat-lab-web-1 --format '{{index .Config.Labels "org.opencontainers.image.revision"}}'
sudo ss -lntp | grep ':18081' || true
curl --noproxy '*' -sS -o /dev/null -w 'UAT HTTP %{http_code}\n' http://100.127.235.30:18081/giris
```

## Onaylı çalışma sırası
1. **Statik/derleme**: runner'da `composer validate --strict`, `npm ci`, `npm run build`, `vendor/bin/pint --test`, `vendor/bin/phpstan analyse` (sürümün config'iyle), `php artisan view:cache`, `php artisan route:list`.
2. **Unit + Contracts**: `MARS_TEST_EXECUTION_APPROVED=MARS_V4_EXECUTION_APPROVED bash scripts/ci/test-v4.sh unit` ve `contracts`; bunlar da ancak kullanıcı test çalıştırmayı onayladığında.
3. **Feature**: aynı scriptte `feature`; kalıcı UAT DB'ye bağlanmadığını ayrıca doğrula.
4. **Integration/Valkey**: `scripts/ci/test-v4.sh` içindeki ek izolasyon ve test onayı değişkenleri zorunlu; yalnızca ayrı TEST veritabanı/namespace. UAT/PROD gerçek verisine test yazımı YOK.
5. **Performans + tarayıcı**: gerçek kullanıcı/veri olmadan, kaynak limitleriyle ayrı onay.
6. **Görsel UAT**: login, firma/dönem, dashboard, sol menü, açık sekmeler, ürün listesi/resimler, dışa aktarma, ambar fişi. UAT ile karşılaştırmalı ekran görüntüsü ve HTTP/JS konsol hata kontrolü.
7. **Yayın**: CI'da imaj üret, build smoke geçir, değişmez commit SHA ile taşınabilir imaj oluştur; UAT izolasyon preflight'ı ve revizyon doğrulaması başarılı olmadan web geçişi YOK. Bu yeni akış henüz uygulanmadı; workflowsuz dağıtımın ayrıca tasarlanması gerekiyor.

## Test kabul ölçütleri
- Güncel kaynak SHA, imajın OCI revision etiketi ve çalışan UAT konteynerinin revision'ı **aynı**.
- `/up` ve `/giris` HTTP 200; tarayıcıda oturum açma başarılı.
- Şirket/dönem üst seçicisi ile dashboard bağlamı aynı.
- Hata ekranı ve PHP Blade derleme hatası yok; boş dashboard kartı yok.
- Browser smoke; ürün kaydı + demo kapak resmi + dışa aktar seçimi + sekme kapanması + ambar çıktısı.
- Test raporu: PASS/FAIL/NOT RUN, tam commit, test süresi, izolasyon kanıtı ve açık hatalar.

## Yasaklar
- PROD DB'de test/migration/seeding, PROD Valkey flush, sunucu reboot, Docker volume silme.
- Test sonuçlarını ölçülmeden 'başarılı' ilan etmek.
- CI çalıştırma veya yeni dağıtım için kullanıcı onayı olmadan PROD'a dokunmak.
