# 00 — Karar Kaydı

Bu belge MarsOtomasyon için kilitli mimari ve ürün kararlarını tutar. Çelişki halinde bu belge, ilgili business-owner planı ve `26_V16_3_TASARIM_UYUMU.md` birlikte otoritedir.

## K-001 — Ürün tipi
MarsOtomasyon şirket içi kullanım odaklı, Türkçe, hızlı ve sade bir **ön muhasebe + operasyon** uygulamasıdır. İlk hedef SaaS değildir.

## K-002 — Backend
- PHP 8.5
- Laravel 13
- Laravel-native modular monolith
- Composer Laravel'in doğal bağımlılık yönetimi olarak kullanılır
- Gereksiz framework katmanı/repository abstraction kurulmaz

## K-003 — Veritabanı
- PostgreSQL 18 tek desteklenen production DB'dir
- CI gerçek PostgreSQL ile çalışır
- Arama: PostgreSQL FTS + `pg_trgm`
- İlk sürümde Elasticsearch/OpenSearch/Meilisearch yok

## K-004 — Cache / async
- Valkey cache/lock/rate-limit/queue destek katmanıdır
- Laravel Queue + worker
- Laravel Scheduler
- Kalıcı business authority Valkey değildir

## K-005 — Git ve repo
- Uygulama repo: `ufukunal/MarsOtomasyon`
- Ana geliştirme branch'i: `main`
- `MarsEski` application code taşınmaz; yalnız plan, business rule, edge-case ve migration referansıdır

## K-006 — UI referansı
Kullanıcı-visible acceptance baseline:
**MarsOtomasyon V16.3 — Genel Tasarım Temizliği**.

- görünür dead button yok
- generic placeholder form/detail yok
- detail readonly, edit ayrı route
- kesinleşmiş belge input formunda düzenlenmez
- teknik queue/outbox/provider/idempotency jargonları normal kullanıcıya gösterilmez

## K-007 — Cari finans
Authority: `account_transactions`.

- satış faturası müşteri bakiyesini artırır
- tahsilat müşteri bakiyesini azaltır
- alış faturası tedarikçi borcunu artırır
- ödeme tedarikçi borcunu azaltır
- tahsilat/ödeme faturaya zorunlu dağıtılmaz
- vade bilgi/raporlama alanıdır

OpenItem/fatura kapatma motoru core değildir.

## K-008 — Stok
Authority: `stock_movements`.

- stok bakiye doğrudan keyfi değiştirilemez
- rezervasyon stok hareketi değildir
- fiziksel her değişim hareket üretir
- aynı fiziksel olay iki kez stok hareketi üretemez

## K-009 — Satış miktarları
Sipariş satırında en az:
- ordered
- dispatched
- invoiced
- remaining

Kısmi sevk/faturalama desteklenir. Faturalanan miktar siparişten düşülür ve kalan miktar izlenir.

## K-010 — Posting atomikliği
Belge kesinleştirme işlemleri DB transaction içinde çalışır. Fatura posting örneği:
`invoice → order progress → stock movement → account transaction → outbox`.

Bir adım başarısızsa tüm işlem rollback olur. Tekrar çağrı idempotent olmalıdır.

## K-011 — Alış stoğu
Fiziksel stok girişi **Mal Kabul** olayında oluşur. Alış faturası tedarikçi cari etkisini oluşturur. Mal geldi/fatura gelmedi senaryosu desteklenir.

## K-012 — Fiyat
Core'da ürün başına tek satış + tek alış fiyatı vardır. Çoklu fiyat listesi ilk sürüm kapsamında değildir. Cari iskonto ayrıca uygulanabilir.

## K-013 — Lot/seri
Core V1'de lot/seri UI ve kapsam yoktur. İhtiyaç oluşmadan altyapı kurulmaz.

## K-014 — Üretim
Basit üretim:
`Reçete → Üretim Emri → Malzeme Çıkışı → Mamul Girişi → Tamamla`.

Generic MRP/QMS/ECO/OEE/Shop Floor platformu core değildir.

## K-015 — Fason
Fason ayrı stok motoru kullanmaz. Mars stok hareketleri üzerinden gönderilen malzeme, gelen mamul, fire/eksik ve kalan izlenir.

## K-016 — Kasa/Banka
Ana kullanıcı akışı:
`Tahsilat, Ödeme, Gider, Kasa Hareketleri, Banka Hareketleri, Virman, Ekstre İçe Aktar`.

Ekstre V1: Excel/CSV/MT940. Canlı open-banking ilk sürümde yok.

## K-017 — Ödeme türleri
Nakit, banka, POS, sanal POS, çek, senet ve diğer tipler type-specific alanlar açar. Business kuralları tek `payment_method` sabit listesine gömülmez; tanımlar kontrollü konfigüre edilebilir.

## K-018 — E-Ticaret/B2B
Tek Integration Core + WooCommerce/Trendyol/Mars B2B adapterları. Mars ürün/stok/fiyat/fatura authority'sidir.

B2B kullanıcı hesabı önceden bir cariye bağlıdır. B2B indirimi ayrı motor değildir; Cari İskontosu kullanılır.

## K-019 — Raporlama
Hazır Rapor Merkezi. İlk sürümde generic Visual Report Designer yok. Excel/CSV/PDF/yazdırma desteklenir.

## K-020 — Backup
Backup özelliği ancak restore testiyle tamamlanmış sayılır. DB + dosyalar + gerekli config/manifest kapsanır.

## K-021 — Delivery
Her modül dikey dilim olarak tamamlanır:
`schema → domain use-case → authorization → V16.3 UI → invariant tests → PostgreSQL CI → observability`.

Future kullanım için kullanılmayan interface/table/framework önceden eklenmez.
