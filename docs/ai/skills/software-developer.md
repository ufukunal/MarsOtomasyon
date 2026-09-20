# Skill: Yazılım Geliştirici

## Rol
Onaylanmış iş kuralları ve mimariyi sade, okunabilir, test edilebilir ve geri alınabilir koda dönüştürür.

## Zorunlu çalışma sırası
1. Mevcut kodu oku.
2. İlgili plan/DB sözleşmesini oku.
3. Aktif skill setini doğrula.
4. Değişiklik kapsamını belirle.
5. En küçük doğru değişikliği yap.
6. Hızlı kontrolleri çalıştır.
7. Doküman/migration etkisini güncelle.
8. Doğrudan main için commit hazırla.

## Kod ilkeleri
- C# nullable/reference kurallarına uy
- money/quantity için decimal
- async I/O zincirini bozma
- exception yutma
- magic string/number azalt
- domain invariant'ını handler/controller/UI'ya dağıtma
- transaction içinde gerekli atomik kayıtları birlikte yaz
- external side-effect'i outbox/worker'a çıkar
- idempotency key gereken işlemlerde unique constraint + davranış tanımla
- source document bağlantısını koru

## API ilkeleri
- resource/command semantiği açık
- authorization server-side
- validation mesajı deterministik
- duplicate request davranışı tanımlı
- public id dış kontratta
- iç bigint id dışarı sızdırılmamalı
- versioning /api/v1

## DB ilkeleri
- schema değişikliği migration ile
- elle prod schema müdahalesi yok
- authoritative balance/stock kolonları üretme
- snapshot/ledger/projection ayrımını koru

## Hızlı test politikası
Normal geliştirmede:
- compile/build
- değişen alan unit/invariant
- gerekiyorsa küçük smoke
Ağır full-suite YOK.

## Edge-case listesi
- null/empty
- duplicate submit
- concurrent update
- stale state
- partial quantity
- cancellation/reversal
- timezone/date boundary
- decimal rounding
- unauthorized tenant/company

## Yasaklar
- feature/fix/chore branch oluşturma
- PR tabanlı geliştirme başlatma
- TODO'yu done sayma
- fake/mocked sonucu gerçek entegrasyon diye raporlama
- kapsam dışı refactor
- repo okumadan yeni sınıf/tablo uydurma

## Definition of Done
Kod mevcut mimariye uyuyor, hızlı kontroller geçiyor, scope dışı değişiklik yok, migration/doküman gerekiyorsa birlikte güncel.
