# Hata yönetimi, günlük ve izleme

## Kullanıcıya gösterilen hata

Kullanıcı **asla** yığın izi (stack trace) veya SQL hatası görmez.
Üç tür mesaj vardır:

| Tür | Örnek | Davranış |
|---|---|---|
| **İş kuralı hatası** | "09.2026 dönemi kapalı. Bu tarihe kayıt girilemez." | Ne olduğu ve **ne yapılacağı** yazılır |
| **Çakışma** | "Bu kayıt siz düzenlerken değiştirildi. Sayfayı yenileyin." | Çözüm önerilir |
| **Beklenmeyen hata** | "İşlem tamamlanamadı. Hata kodu: A7F3C2" | Kod verilir, detay loglanır |

**Hata kodu (correlation id)** her istekte üretilir, loga yazılır,
hatada kullanıcıya gösterilir. Kullanıcı "A7F3C2 hatası aldım" der,
log o kodla aranır.

```php
// middleware
$requestId = Str::upper(Str::random(6));
Log::withContext(['request_id' => $requestId, 'company' => ..., 'year' => ...]);
```

## İstisna sınıfları

```
DomainException           → iş kuralı, kullanıcıya gösterilir, loglanmaz
  PeriodClosedException
  NegativeStockException
  StaleRecordException
  NoActivePeriodException

RuntimeException          → sistem hatası, loglanır, kod gösterilir
```

**Ayrım önemli:** iş kuralı hatası log kirletmez. "Stok yetersiz" günde
elli kez olabilir, bu bir arıza değildir.

## Günlük (log)

| Kanal | İçerik | Saklama |
|---|---|---|
| `daily` | uygulama hataları | 30 gün |
| `queue` | kuyruk işleri | 14 gün |
| `integrity` | bütünlük kontrolü sonuçları | 90 gün |
| `audit` | **veritabanında** (`activity_log`) | süresiz |

Log dosyası **günlük döner** ve eski dosyalar silinir. 55 GB diski log
doldurmamalı.

**Loga yazılmayacaklar:** parola, oturum anahtarı, API anahtarı,
tam kart numarası, TC kimlik numarası. Bunlar maskelenir.

## Sağlık kontrolü

```
GET /saglik   (kimlik doğrulaması gerekmez, yalnız yerel ağdan)
```

Dönen bilgi: uygulama sürümü, veritabanı bağlantısı (master + bir
dönem), Valkey bağlantısı, kuyruk işçisi çalışıyor mu, `failed_jobs`
sayısı, son yedek zamanı, son bütünlük kontrolü.

Herhangi biri kötüyse HTTP 503 döner.

## Uyarı gönderimi

Şu durumlarda **yöneticiye e-posta** gider:

- `failed_jobs` boş değil
- Bütünlük kontrolü fark buldu
- Yedek 36 saattir alınmadı
- Disk %85 doldu
- Aynı hata kodu 1 saatte 10+ kez

Gürültü yapmaması için aynı uyarı 6 saatte bir tekrarlanır.

## Yavaş sorgu

100 ms'yi aşan sorgular `daily` kanalına yazılır. Geliştirme ortamında
**N+1 sorgu tespiti** açıktır (`Model::preventLazyLoading()`).

## Kuyruk izleme

`failed_jobs` tablosu gecelik kontrol edilir. Boş değilse uyarı ve
ana sayfada kırmızı bildirim: "3 kuyruk işi başarısız".
