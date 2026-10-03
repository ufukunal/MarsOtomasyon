# G-1112 — Çok dönemli rapor Faz 11b entegrasyon/doğrulama

## Amaç
Faz 10 G-1006'da kanonik olarak kurulan `MultiPeriodQuery` altyapısının dönem devri sonrasında eski/kapalı period'ları güvenli okuyabildiğini doğrulamak; aynı rapor altyapısını ikinci kez kurmamak.

## Önkoşul
G-1006, G-003 PeriodContext ve Master period erişim/yetki altyapısı.

## Dokunulacak dosyalar
- `tests/Feature/Reporting/MultiPeriodCarryIntegrationTest.php`
- Faz 11b PeriodRangeSelector entegrasyon testleri
- gerekiyorsa mevcut `MultiPeriodQuery` için yalnız Faz 11b uyumluluk düzeltmeleri; paralel servis yok

## Şema / Kod
Yeni production şeması veya ikinci MultiPeriodQuery implementasyonu yok.

Kanonik sözleşme:
- `docs/02-is-kurallari/54-export-ve-cok-donem.md`
- G-1006 implementation'ı

Faz 11b bu altyapıyı yalnız şu bağlamda doğrular:
- source period closed olduktan sonra read-only raporlama,
- target period ile birlikte çok dönemli sorgu,
- period_id/year metadata,
- try/finally context restore,
- `reports.consolidated` ve `cost.view` yetkileri.

## Kurallar
- Her period DB ayrı sorgulanır; normal cross-database JOIN/FDW/dblink zorunlu değildir.
- Sonuçlar PHP DTO/Collection katmanında birleştirilir.
- Context her durumda geri yüklenir.
- İş tarihi rapor tanımının kanonik business-date alanıdır; `created_at` iş tarihi yerine kullanılmaz.
- Her period için Master access kontrolü yapılır.
- Closed period read-only okunabilir.
- Archived/detached period sessiz atlanmaz; açık restore gereksinimi/hata üretilir.
- `reports.consolidated` olmadan konsolide rapor açılamaz.
- `cost.view` yoksa maliyet/kâr alanları query/select seviyesinde üretilmez.
- Money toplamlarında PHP float kullanılmaz.
- Queue raporu session PeriodContext'e güvenmez.
- Bu görev **dönem devri kart/açılış/sequence kopyalama kurallarını tekrar tanımlamaz**; onlar G-1110/G-1111'in sorumluluğudur.

## Kabul ölçütü
- Bir source closed period + target active period sonucu aynı raporda birleşiyor.
- Her satır period_id/period_year taşıyor.
- Query başarı/hata sonrası önceki PeriodContext geri yükleniyor.
- Erişimi olmayan period reddediliyor.
- `reports.consolidated` olmadan konsolide rapor açılamıyor.
- `cost.view` olmadan cost/profit select/payload oluşmuyor.
- Closed period okunabiliyor.
- Archived/detached period açık hata veriyor; sessiz skip yok.
- Queue execution session context'ini değiştirmiyor.
- Money toplamları decimal string/BCMath ile tutarlı.
- Gerçek PostgreSQL multi-database testi geçiyor.
- Pint/Larastan/Pest geçiyor.

## İstem
> G-1006 MultiPeriodQuery altyapısını yeniden yazma. Faz 11b source/target period senaryolarında read-only closed-period erişimi, yetki, context restore ve multi-period aggregation sözleşmesini gerçek PostgreSQL üzerinde doğrula.
