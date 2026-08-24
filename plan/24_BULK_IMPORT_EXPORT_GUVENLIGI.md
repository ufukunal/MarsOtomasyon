# 24 — Bulk Import / Export Güvenliği

## Import ilkesi
CSV/Excel/API bulk import doğrudan kontrolsüz model insert değildir. Akış:
`Dosya → parse → normalize → validate → preview/dry-run → commit → sonuç raporu`.

## Güvenlik
- permission kontrolü
- dosya boyutu/satır limiti
- MIME/extension kontrolü
- formula injection savunması
- dynamic column mapping whitelist
- kullanıcıdan gelen class/table/SQL adı çalıştırılmaz
- temp file lifecycle temizlenir

## Validasyon
Satır bazında:
- zorunlu alan
- format
- referans/master eşleşmesi
- unique/duplicate
- business invariant

Hatalı satırların nedeni kullanıcıya export edilebilir sonuç dosyasında verilir.

## Idempotency
Aynı import dosyasının tekrar yüklenmesi duplicate business kayıt üretmemelidir. Kaynağa göre fingerprint/external key/import row key kullanılır.

## Transaction
Küçük import tek transaction olabilir. Büyük import chunk'lanacaksa partial commit davranışı açıkça tanımlanır ve sonuç manifest'i hangi chunk/satırların işlendiğini tutar.

## Finans/Stok import
Ledger etkisi yaratacak import normal domain use-case'i kullanır; tabloya doğrudan movement insert bypass edilmez.

## Export
Export permission'ı ayrı olabilir. Hassas alanlar role göre maskelenir/çıkarılır.

CSV export formula injection'a karşı `=`, `+`, `-`, `@` başlayan kullanıcı verisini güvenli üretir.

## Büyük export
Queue/job ile üretilebilir. Geçici download link'i süreli ve authorization kontrollü olmalıdır.

## Audit
Kim hangi dosyayı ne zaman import/export etti; satır sayısı, başarı/hata ve çıktı referansı audit edilir.
