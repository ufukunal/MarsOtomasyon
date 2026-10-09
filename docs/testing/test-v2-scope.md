# Test v2.0 — Denetlenebilir kalite kapsamı

## Dayanak ve doğru kapsam yorumu
- Denetlenen temel kod: main 4a6ebe96b8e263031b1e6a75f4a1d68302e07943 (9 Ekim 2026).
- GitHub repo envanteri: 1267 dosya, 181 app/Actions/*.php, 160 app/Support/*.php, 28 tests/Feature/*.php (v2 eklenmeden önce).
- Önceki başarılı üç tur: https://github.com/ufukunal/MarsOtomasyon/actions/runs/37908288952 (15/15 jobs PASS).
- Önceki %92,5 PCOV sonucu **genel proje kapsamı değildir**. R4 yalnız app/Actions/Stock içinde tanımlı Stock testlerinin satır kapsamıdır. Başka modüller için toplu yüzde üretilemez.
- ManualAudit/Architecture/CoverageManifestTest 273 kontrol kimliğinin varlığını gösterir, dinamik iş akışı kapsamı veya global coverage kanıtı değildir.
- Bu doküman "eksiksiz test edildi" iddiası yerine doğrulanabilen durumları ve açık boşlukları ayrı tutar.

## Modül/risk matrisi
| İş alanı | Mevcut dinamik test | Test v2.0 eklenen senaryo | Hâlâ açık kanıt açığı |
| --- | --- | --- | --- |
| Master/Period/Yetkilendirme | Mevcut Period, Security, Companies feature testleri | Satış/Alış/Finans/İthalat/Üretim/İade mutasyonlarında anonim aktör reddi; hassas web rotaları | Tüm roller, şirketler/dönemler arası çapraz negatif matris |
| Ürün/kartlar/fiyat | Mevcut Products, Contacts, Pricing ve Import feature testleri | Türkçe sayı biçimleme doğruluğu | UI E2E, yüksek hacim |
| Stok | Mevcut Stock dinamik suite, dar PCOV | Kanal stok scope boş/fason kontrolü | Depolar arası yoğun paralel yük ve tüm stok hareketi |
| Satış ve belge | Mevcut Sales ve Period feature suite | İlgili mutasyonlarda kimlik bilgisi olmadan reddetme | Satış–iade tam ters kayıt / muhasebe mutabakatı |
| Satın alma | Önceki doğrudan feature suite yok | Onay ve fatura posting işlevlerinde yetkisiz değişiklik reddi | Teklif → sipariş → kabul → fatura → ödeme → iade başarılı/geri-al akışları |
| Finans | Önceki doğrudan feature suite yok | Transferde anonim mutasyon reddi, gerçek PostgreSQL çift kayıt/idempotency ve rollback, format/decimal testleri | Banka mutabakatı, kur ve farklı finans hesap türleri arası transfer |
| İthalat | Önceki doğrudan feature suite yok | Dağıtım mutasyonunda anonim reddi, gerçek PostgreSQL maliyet dağıtım toplamı ve rounding residual | Çok dövizli ithalat maliyetinde hata enjeksiyonu, para birimi ve dönem izolasyonu |
| Üretim/fason | Önceki doğrudan feature suite yok | BCMath malzeme, servis, FIFO olmayan ortalama maliyet ve geçersiz girdiler; anonim posting reddi | Reçete revizyonu, üretim çıktı/eksik/fire, taşeron hizmet maliyeti tam DB |
| İade | Önceki doğrudan feature suite yok | İade mutasyonunda anonim reddi | Satış iadeleri karantina ve alış iadeleri rezervasyon/ledger tam DB |
| Pazar yerleri | Mevcut Faz9ChannelStockPriceTest | Event hash sıralama ve tipi, eksik mod/boş/fason stok, webhook bulunmayan hesap reddi | Canlı API sözleşmesi, HMAC negatif/pozitif, retry/backoff, rate limit/duplicate event |
| Raporlama | Önceki doğrudan feature suite yok | Filtre tipleri, leap-date, SQL-benzeri değer reddi, kolon/sort ve paging DTO | Yetki gizlenen maliyet verileri, toplam mutabakatı, dışa aktarma |
| Yazdırma ve şablon | Mevcut PrintProfileResolutionTest | ZPL/UTF8 sürücüsü, token allowlist, XSS, Blade ve ZPL injection negatif testleri | Gerçek PDF render, printer cihazı, bütün label geometrileri |
| İşletim ve güvenlik | Mevcut ManualAudit ve ops-health | OperationalErrorSanitizer token/url/JSON redaction ve limit | Sistem backup-restore, tam VM reboot/autostart, restore tatbikatı |

## Test sınıfı ve güvence seviyesi

1. **Unit/behavior:** Girdi/çıktı ve istisna gözlemleri doğrudan üretim sınıflarında doğrulanır; text grep testleri yerine gerçek metod çağrıları kullanılır.
2. **Feature/runtime:** Gerçek Laravel route, middleware, PostgreSQL test master migration ve gerektiğinde yeni period DB üzerinde sınanır; kopya üretim DB'sine dokunmaz.
3. **Static/ManualAudit:** Statik sözleşme doğrulamasıdır; üretilen 273 ID tam davranış kapsamı gibi sunulmaz.
4. **Integration contract:** Gerçek uzak platformların API sandbox veya erişim bilgileri olmadan Trendyol, Hepsiburada, N11 ve WooCommerce canlı entegrasyonu PASS sayılamaz.
5. **Coverage:** Mevcut %92,5 yalnız Stock Action ölçümüdür; global PCOV/Xdebug kapsamı ve mutasyon test skorları henüz yoktur.

## CI şartı
- tests/Unit/TestV2 altındaki tüm senaryolar R1 Unit tests ile çalışır.
- tests/Feature/TestV2 altındaki tüm senaryolar R1 Foundation Feature ile çalışır.
- Tüm testler gerçek DB kullanırken yalnız güvenli test veritabanları ile çalıştırılır.
- R2, R3 ve R4 mevcut kalite kapılarını korur; kritik yetkisiz çağrıda veya hata senaryosunda kırmızıya düşülmelidir.
- Test v2 için ayrı feature klasörünü CI dışında bırakmak yasaktır.

## V2 sonrasında hâlâ gerekecek fazlar
- V2.1: Purchasing, Return, Finance, Imports, Production içindeki **gerçek pozitif** çok-aşamalı DB mutasyon ve rollback senaryoları.
- V2.2: multi-company/multi-period eşzamanlılık, idempotency ve izolasyon matrisi; gerçek çok process load.
- V2.3: marketplace sahte HTTP sunucularıyla imza, retry, dedupe, failure injection ve ağ timeout testleri.
- V2.4: admin/readonly role matrisi, export sızıntı ve signed-download IDOR negatif senaryoları.
- V2.5: **global** sınıf/dal satır kapsamı, mutation score ve API/Livewire browser E2E ölçümü.
- V2.6: reproducible restore / disaster recovery, production benzeri staging smoke, erişim sağlandıktan sonra VM reboot/autostart.

Bu maddelerin varlığı veya yazılmış olması, çalıştığı doğrulanmadan PASS kabul edilmez.
