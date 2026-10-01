# Görev dosyası biçimi

Her kod üretim görevi **tek başına uygulanabilir** olmalıdır. Hedef uzunluk 300–500 satırdır; gerekli bağlam görev içinde tekrarlanır.

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
