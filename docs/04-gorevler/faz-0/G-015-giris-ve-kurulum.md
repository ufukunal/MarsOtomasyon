# G-015 — Giriş ekranı ve firma kurulum sihirbazı

## Amaç
Sisteme giriş, şirket/dönem seçimi ve yeni firma kurulumu.
Master/dönem mimarisi bu ekranlar olmadan kullanılamaz.

## Önkoşul
G-002, G-003, G-005, G-012


## Dokunulacak dosyalar
- Bu görevde tarif edilen mevcut uygulama/migration/test dosyaları; kapsam dışına çıkma.


## Şema / Kod

Bu görev yeni tablo gerektirmiyorsa migration ekleme. Mevcut kod örnekleri ve aşağıdaki mimari sözleşme normatiftir.

## Ekranlar
`docs/03-ekranlar/giris-ve-donem-secimi.md` ve
`docs/03-ekranlar/firma-kurulum-sihirbazi.md` belgelerini uygula.

## Giriş
- E-posta + parola, "beni hatırla", parola sıfırlama
- Başarısız giriş IP ile loglanır; 5 denemeden sonra 15 dk kilit
- Giriş sonrası şirket/dönem seçim ekranı

## Şirket/dönem seçimi
- Yalnızca `company_user`'da bağlı olduğu şirketler
- Bağlanılacak **veritabanı adı ekranda gösterilir**
- `closed` seçilebilir (salt okunur, uyarı), `archived` seçilemez
- Seçim `PeriodContext::use()` çağırır

## Kurulum sihirbazı
Sekiz adım, ekran belgesindeki tabloya göre. Son adımda:
`companies` → `CREATE DATABASE` → migration → `periods` → varsayılanlar
→ Yönetici kullanıcı → açılış verisi (kuyruk) → yeni firmaya geçiş.

**`CREATE DATABASE` transaction içinde çalışmaz.** Migration hata
verirse `periods` satırı yazılmaz ve kullanıcıya veritabanı adı
verilerek elle silme talimatı gösterilir.


## Kurallar

**Faz bağlamı:** Faz 0 — altyapı.

### Göreve özel kararlar
- Login Master'da; şirket ve dönem erişimi ayrı doğrulanır.
- Erişimsiz period seçicide görünmez.
- Yeni form/kurulum ekranı başka kaydın örnek verisiyle dolu açılmaz.


### Uygulama ayrıntıları
- Login Master kullanıcı tablosunda yapılır.
- Şirket listesi `company_user`, dönem listesi `period_user_access` üzerinden filtrelenir.
- `last_company_id` ve `last_period_id` yalnız kullanım kolaylığıdır; erişim hakkı yerine geçmez.
- Seçim doğrulandıktan sonra PeriodContext kurulur; erişimsiz dönem URL/istek yoluyla da açılamaz.

## Kabul ölçütü
- Giriş çalışıyor, hatalı denemede kilit devreye giriyor
- Yetkisiz şirket seçilemiyor
- Arşiv dönem seçilemiyor, kapalı dönem salt okunur açılıyor
- Sihirbaz baştan sona yeni firma kuruyor, veritabanı oluşuyor
- `db_prefix` kayıt sonrası değiştirilemiyor
- Migration hatasında yarım kayıt kalmıyor


## İstem
> Giriş ekranını, şirket/dönem seçim ekranını ve sekiz adımlı firma
> kurulum sihirbazını yaz. Seçim ekranı bağlanılacak veritabanı adını
> göstersin. Sihirbazın son adımı CreatePeriod'u çağırsın, hata
> durumunda periods satırı yazmasın ve kullanıcıyı bilgilendirsin.
