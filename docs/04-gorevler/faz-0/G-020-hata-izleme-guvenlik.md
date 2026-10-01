# G-020 — Hata yönetimi, izleme ve güvenlik

## Amaç
Kullanıcıya anlamlı hata, geliştiriciye izlenebilir log, sisteme
güvenlik temeli.

## Önkoşul
G-003, G-005


## Dokunulacak dosyalar
- Bu görevde tarif edilen mevcut uygulama/migration/test dosyaları; kapsam dışına çıkma.


## Şema / Kod

Bu görev yeni tablo gerektirmiyorsa migration ekleme. Mevcut kod örnekleri ve aşağıdaki mimari sözleşme normatiftir.

## 1. Hata yönetimi

İstisna hiyerarşisi:
```
DomainException   → kullanıcıya gösterilir, LOGLANMAZ
  PeriodClosedException, NegativeStockException,
  StaleRecordException, NoActivePeriodException
RuntimeException  → loglanır, kullanıcıya hata kodu gösterilir
```

**İstek kimliği (correlation id):** her istekte 6 karakterlik kod
üretilir, log bağlamına yazılır, beklenmeyen hatada kullanıcıya
gösterilir: *"İşlem tamamlanamadı. Hata kodu: A7F3C2"*

Hata sayfaları Türkçe: 403, 404, 419 (oturum), 500, 503.

## 2. Günlük

| Kanal | Saklama |
|---|---|
| `daily` | 30 gün |
| `queue` | 14 gün |
| `integrity` | 90 gün |

**Maskelenecekler:** parola, oturum anahtarı, API anahtarı,
TC kimlik numarası, tam telefon.

Yavaş sorgu (>100 ms) loglanır. Geliştirmede `preventLazyLoading()` açık.

## 3. Sağlık kontrolü

`GET /saglik` — kimlik doğrulaması yok, yalnız yerel ağdan erişilir.
Kontrol: sürüm, master bağlantısı, bir dönem bağlantısı, Valkey,
kuyruk işçisi, `failed_jobs` sayısı, son yedek, son bütünlük kontrolü.
Biri kötüyse HTTP 503.

## 4. Uyarı

Yöneticiye e-posta: `failed_jobs` boş değil · bütünlük farkı ·
yedek 36 saattir alınmadı · disk %85 · aynı hata 1 saatte 10+ kez.
Aynı uyarı 6 saatte bir tekrarlanır.

## 5. Güvenlik

- Parola: en az 10 karakter, harf + rakam
- 5 hatalı giriş → 15 dk kilit (IP + e-posta)
- Oturum 8 saat hareketsizlikte düşer
- Parola değişince diğer oturumlar sonlanır

**Dosya yükleme:** uzantı beyaz listesi, **MIME içerikten doğrulanır**,
dosya adı UUID ile yeniden üretilir, web kökü dışında saklanır, erişim
yetki kontrollü rota üzerinden, **SVG yasak**, çift uzantı reddedilir.

**İstek sınırlama:** giriş 5/dk · parola sıfırlama 3/saat ·
dosya yükleme 30/dk · rapor 10/dk · webhook 120/dk.

## 6. Kişisel veri

- TC kimlik numarası maskelenir (`123*****89`); tam hali
  `contacts.view_sensitive` iznine bağlı
- Kişisel veri içeren dışa aktarma `activity_log`'a düşer
- Cari kartında "kişisel verileri anonimleştir" eylemi — belgeler ve
  bakiye korunur
- Yedekler şifrelenir


## Kurallar

**Faz bağlamı:** Faz 0 — altyapı.

### Göreve özel kararlar
- DomainException business error; unexpected exception correlation id.
- TC tam görünüm ayrı izin; upload MIME/UUID/SVG kuralları zorunlu.


### Uygulama ayrıntıları
- `DomainException` beklenen iş kuralı hatasıdır; teknik error log'u kirletmez.
- Beklenmeyen exception correlation ID ile loglanır ve kullanıcıya stack trace yerine hata kodu gösterilir.
- TC kimlik/tam hassas veri ayrı izin gerektirir; log/payload/export içinde gereksiz tam değer tutulmaz.
- Upload güvenliği MIME içerik doğrulaması, UUID fiziksel ad ve SVG yasağını birlikte uygular.

## Kabul ölçütü
- İş kuralı hatası kullanıcıya Türkçe gösteriliyor, loga yazılmıyor
- Beklenmeyen hata kod gösteriyor, log o kodla bulunabiliyor
- `/saglik` doğru bilgi dönüyor, bozukken 503
- SVG yüklemesi reddediliyor
- Uzantısı doğru ama içeriği farklı dosya reddediliyor
- 6. hatalı girişte kilit devreye giriyor
- TC kimlik numarası yetkisiz kullanıcıda maskeli


## İstem
> İstisna hiyerarşisini, correlation id middleware'ini, Türkçe hata
> sayfalarını, log kanallarını, /saglik uç noktasını, uyarı bildirimlerini,
> dosya yükleme doğrulamasını (MIME içerikten), istek sınırlamalarını ve
> TC kimlik maskelemesini yaz. DomainException türevlerini LOGLAMA.
