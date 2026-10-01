# Görev dosyası biçimi

Her kod üretim görevi **tek başına uygulanabilir** olmalıdır. **Satır sayısı hedef değildir.** Görev, işi eksiksiz tarif edecek kadar uzun; gereksiz tekrar içermeyecek kadar kısa olmalıdır.

## Zorunlu bölümler

```markdown
# G-xxx — Başlık
## Amaç
## Önkoşul
## Dokunulacak dosyalar
## Şema / Kod
## Kurallar
## Kabul ölçütü
## İstem
```

## Yazım ilkeleri

- Ortak mimari metni her göreve kopyalanmaz; yalnız görevi doğrudan etkileyen kurallar yazılır.
- Aynı test veya kontrol maddesi tekrar edilmez.
- Kaynakta olmayan tablo/alan/sınıf/Action/iş kuralı uydurulmaz.
- Eksik karar varsa `[KARAR GEREKİYOR]` yazılır.
- Göreve özel Şema/Kod, işlem sırası, hata durumu ve kabul testi genel checklistten daha değerlidir.
- 300–500 satır zorunlu değildir; uzunluk için dolgu yasaktır.

## Kontrol listesi

- Doğru DB/connection açıkça yazılmış mı?
- Period tablosunda company_id/BelongsToCompany/global scope yok mu?
- Period içi kart ilişkileri gerçek FK mı?
- Master user cross-DB FK yapılmadan user_id + user_name snapshot mı?
- Money + BCMath, doğru decimal hassasiyetleri mi?
- document_date kullanılıyor mu?
- Idempotency + version + lockForUpdate gereken yerde var mı?
- CHECK constraints yazılmış mı?
- Stok yalnız RecordStockMovement üzerinden mi?
- base_quantity + frozen conversion_factor var mı?
- Türetilmiş/kopyalanmış veri için integrity kontrolü var mı?
- Yetki ve cost.view veri sızıntısı test edilmiş mi?
- Gerçek PostgreSQL kabul testleri tanımlı mı?
- Yeni kart tablosu dönem devrine eklendi mi?

Model şemayı veya mimariyi kendiliğinden değiştirmez. Karar gerekiyorsa kod üretmeden raporlar.
