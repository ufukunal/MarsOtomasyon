# 22 — M22 Product Installation PDF Builder Contract

Bu kayıt `08_MODULLER_MASTER_PLAN.md` içindeki **Ürün / Stok → Kurulum Kılavuzu** ile **Dosyalar / Önizleme / Yazdırma → server-owned versioned template** sahipliğini M22 exit contractına bağlar.

## Locked scope

- Kurulum rehberi taslağı `steps`, `warnings`, `tools`, `parts` ve seçilmiş ürün `images` alanlarını taşır.
- Görseller yalnız aktif company/product boundary içindeki `ProductFile(kind=media)` kayıtlarından seçilir; detached, archived veya quarantined varlık fail-closed reddedilir.
- Önizleme A4 page contractını kullanır.
- Yayın `product-installation-pdf.v1` renderer kimliği, canonical source fingerprint ve SHA-256 ile private `FileAsset` üretir.
- Aynı renderer + aynı source fingerprint tekrar yayınlanırsa yeni artifact üretilmez; mevcut versiyon döner.
- İçerik değişirse ürün bazında monotonik yeni versiyon oluşur.
- Yayınlanmış document row ve bağlı PDF `FileAsset` PostgreSQL trigger ile immutable'dır.
- Download sırasında `%PDF-`, storage existence ve hem document hem `FileAsset` SHA-256 doğrulanır.
- Published snapshot, mutable drafttan ayrıdır; sonraki draft değişiklikleri eski PDF byte'larını değiştirmez.

## Exit evidence

Representative contract testi: `tests/Integration/Products/ProductInstallationPdfTest.php`.

Final kapanış kanıtı yalnız exact PR head canonical Foundation 4/4, merge ve exact merged `main` Foundation 4/4 yeşil olduğunda geçerlidir.