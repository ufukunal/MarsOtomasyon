# G-015 — Giriş ekranı ve firma kurulum sihirbazı

## Amaç
Sisteme giriş, şirket/dönem seçimi ve yeni firma kurulumu.
Master/dönem mimarisi bu ekranlar olmadan kullanılamaz.

## Önkoşul
G-002, G-003, G-005, G-012

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
