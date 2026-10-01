# İstem şablonları

## Yeni görev

Bu G görevini aynen uygula. Yalnız “Dokunulacak dosyalar” kapsamını değiştir. Şemayı/mimariyi kendiliğinden değiştirme.

Zorunlu kontrol: doğru Master/period connection; period tabloda company_id/BelongsToCompany/global scope yok; period içi FK gerçek; Master user cross-DB FK yok; user_id+user_name snapshot; Money+BCMath; document_date; idempotency/version/lockForUpdate; CHECK; RecordStockMovement; base_quantity+conversion_factor; integrity; gerçek PostgreSQL test.

Kabul ölçütleri çalışmadan görevi tamamlandı sayma.

## Gözden geçirme

Özellikle legacy kalıntısı ara: `company_id` period tablosu, `BelongsToCompany`, `withoutGlobalScopes`, `master.contacts`, `company` DB connection, `prices.override`, `date` belge alanı, Master user'a period FK.

## Hata düzeltme

Hata kapsamı dışına çıkma. Yeni iş kararı gerekiyorsa kod yazmadan üç seçenekle raporla.
