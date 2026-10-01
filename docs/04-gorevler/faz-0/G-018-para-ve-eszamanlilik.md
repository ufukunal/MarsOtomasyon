# G-018 — Para aritmetiği, eşzamanlılık ve tarih kuralları

## Amaç
Üç temel altyapı: BCMath tabanlı para hesabı, çift gönderim koruması ve
iyimser kilit, iş tarihi kuralları.

**Bunlar sonradan eklenemez.** Para hesabı float ile yazılırsa her
hesap fonksiyonu yeniden yazılır; `version` kolonu sonradan eklenirse
her form ve her update elden geçirilir.

## Önkoşul
G-001, G-003

## Dokunulacak dosyalar
- `app/Support/Money/Money.php`, `MoneyCast.php`
- `app/Support/Concurrency/IdempotencyKey.php`
- `app/Support/Concurrency/HasOptimisticLock.php` (trait)
- `app/Exceptions/StaleRecordException.php`
- `app/Actions/Documents/ValidateDocumentDate.php`
- `database/migrations/period/xxxx_create_idempotency_keys_table.php`
- `config/app.php` (timezone)

## 1. Money

`docs/02-is-kurallari/17-para-aritmetigi.md` içindeki `Money` sınıfını
birebir uygula. Kurallar:

- Tutar **hiçbir yerde float olmaz**; string + BCMath
- Para birimi nesnenin içinde; farklı birimler toplanırsa istisna
- Yuvarlama **yalnızca belge toplamında**, 2 hane, yarım yukarı
- KDV oran gruplarına göre toplanıp **bir kez** hesaplanır
- Yuvarlama farkı `rounding_difference` alanında saklanır

`MoneyCast` model cast'i yazılır. **`float` cast kullanılmaz.**

## 2. İstek anahtarı

`idempotency_keys` tablosu ve `IdempotencyKey::run($key, $action, $callback)`
yardımcısı. Davranış:

- Anahtar yoksa: `processing` yazılır, iş çalışır, `done` + sonuç
- `processing` ise: "işlem sürüyor" hatası
- `done` ise: iş **tekrarlanmaz**, önceki sonuç döner
- 7 günden eski kayıtlar gecelik temizlenir

Zorunlu olduğu işlemler: belge kesinleştirme/iptal, tahsilat, ödeme,
transfer, sayım kesinleştirme, dönem devri, içe aktarma.

## 3. İyimser kilit

`version` kolonu ve `HasOptimisticLock` trait'i. Güncelleme
`where('version', $expected)` ile yapılır; etkilenen satır 0 ise
`StaleRecordException`.

Hata mesajı kullanıcıya **hangi alanların değiştiğini** gösterir.

Uygulanacak tablolar: `documents`, `contacts`, `products`,
`price_lists`, `recipes`, ayar tabloları.

## 4. Tarih kuralları

- `APP_TIMEZONE=Europe/Istanbul`
- `timestamp` → UTC saklanır; `date` → olduğu gibi
- **Dönem kilidi ve raporlar `document_date`'e bakar**, `created_at`'e değil
- `ValidateDocumentDate`: dönem aralığı dışı **engellenir**, gelecek
  tarih **uyarı**, kapalı ay **engellenir**
- Vade `document_date` + `term_days`
- Gece yarısını geçen işlemlerde iş tarihi kullanıcıdan alınır

## 5. Deadlock

`DB::transaction(..., attempts: 3)`. Kilit sırası her zaman aynı:
ürün id'leri **sıralanarak** kilitlenir.

## Kabul ölçütü
- `Money::of('0.1')->plus(Money::of('0.2'))` → `0.3000` (float olsa 0.30000000000000004)
- TRY + USD toplanınca istisna
- 100 satırlık, birim fiyat 33,33 olan faturada kuruş farkı **yok**
- Aynı istek anahtarıyla iki kez gönderim → tek belge
- İki kullanıcı aynı belgeyi kaydedince ikincisi `StaleRecordException`
- 2025 tarihli belge 2026 dönemine yazılamıyor
- Kapalı aya `document_date` ile kayıt engelleniyor

## İstem
> Money sınıfını ve MoneyCast'i 17-para-aritmetigi.md'deki kodla birebir
> yaz. idempotency_keys tablosunu, IdempotencyKey yardımcısını,
> HasOptimisticLock trait'ini, StaleRecordException'ı ve
> ValidateDocumentDate action'ını yaz. Hiçbir yerde float cast KULLANMA.
> Yuvarlamayı yalnızca belge toplamında yap.
