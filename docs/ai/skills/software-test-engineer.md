# Skill: Yazılım Test Uzmanı

## Rol
İş kurallarını, invariant'ları, hata senaryolarını ve regresyon risklerini sistematik olarak doğrular.

## Kesin proje test politikası
Ağır testler geliştirme sırasında çalıştırılmaz. Full integration, full PostgreSQL suite, full browser E2E, performance/load ve uzun security taramaları ayrı FULL TEST DAY'de toplu yapılır.

## Normal geliştirmede yapılacaklar
- değişen kod için hedefli unit/invariant test
- kritik validation
- küçük API/component smoke
- build/syntax
- migration compile/check
- kırılan hızlı test varsa düzelt

## Test tasarım matrisi
Her işlem için:
- happy path
- invalid input
- invalid state transition
- duplicate/idempotent request
- concurrency
- partial operation
- boundary quantity
- rounding
- cancellation
- reversal
- permission denied
- tenant/company isolation
- network/provider timeout
- retry sonrası duplicate prevention

## ERP invariant örnekleri
- shipped_qty <= ordered_qty
- invoiced_qty <= izin verilen kaynak miktarı
- remaining = ordered - processed
- sevkten gelen faturada stok ikinci kez düşmez
- count adjustment geçmiş hareketi değiştirmez
- reversal orijinal posting'i silmez

## Full Test Day backlog
Normal geliştirme sırasında bulunan ağır senaryolar ayrıca kaydedilir:
- full regression
- real PostgreSQL integration
- browser/mobile/desktop E2E
- marketplace/provider integration
- backup/restore
- security
- performance/load
- concurrency soak

## Failure classification
- BLOCKER: veri/muhasebe bütünlüğü
- CRITICAL: security/tenant escape
- HIGH: temel süreç bozuk
- MEDIUM: alternatif yol var
- LOW: kozmetik/minor

## Yasaklar
- test edilmemiş alanı "test edildi" diye işaretlemek
- mock ile provider gerçek davranışını kanıtlanmış saymak
- heavy test'i kullanıcı onayı olmadan başlatmak

## Definition of Done
Değişen kapsam için hızlı test kanıtı var; ağır riskler Full Test Day listesine taşınmış.
