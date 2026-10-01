# Doğrulama ve test standardı

## Katman

Livewire/Form: biçim ve zorunluluk.
Action/Domain: iş kuralı, yetki, dönem, stok, risk uyarıları ve transaction.

## Period referansı

Cari/ürün/lokasyon period DB'dedir. Validation aktif `period` connection üzerinden exists kontrolü yapar. `master.contacts` veya company_id filtresi kullanılmaz.

Master user actor ID'si period DB'de FK ile doğrulanmaz; web aksiyonunda authenticated user Master'dan doğrulanmış olmalıdır ve actor snapshot yazılır.

## Zorunlu test türleri

- fiziksel şirket+dönem izolasyonu
- erişim: company_user + period_user_access
- gerçek PostgreSQL
- idempotency
- optimistic lock
- lockForUpdate concurrency
- kapalı dönem + document_date
- Money/BCMath ve yuvarlama
- base unit conversion
- cost.view veri sızıntısı
- post-write verify/rollback
- ilgili integrity kontrolü

SQLite kullanılmaz. Test DB'leri gerçek Master + en az iki farklı period DB ile izolasyon senaryosu kurmalıdır.
