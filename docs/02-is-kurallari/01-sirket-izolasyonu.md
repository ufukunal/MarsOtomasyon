# Şirket ve dönem izolasyonu

> **DOC2 hedef kararı (11.10.2026):** Çok şirketli ve dönemsiz mimari. Master kimlik/yetki
> veritabanı + şirket başına tek kalıcı fiziksel PostgreSQL veritabanı.
> Yıl/dönem için ayrı DB, seçici, devir veya yıllık fiziksel veri taşıma olmayacak.
> Eski period mimarisi aşağıda yalnız **mevcut legacy kodun geçiş referansı** olarak tutulmuştur.
> Veri konsolidasyonu ve uygulama dönüşümü doğrulanmadan DOC2 etkinleştirilmez.
> Yeni kanonik hedef: [docs/doc2/README.md](../doc2/README.md).

İzolasyon fiziksel DB seviyesindedir.

1. Login Master DB'de yapılır.
2. Kullanıcının `company_user` ile şirkete erişimi doğrulanır.
3. `period_user_access` ile seçilen döneme erişimi doğrulanır.
4. Sonra `PeriodContext` ilgili `periods.database_name` değerini `period` bağlantısına bağlar.
5. PeriodModel sorguları yalnız bu DB'de çalışır.

Period tablolarında `company_id`, `BelongsToCompany` veya şirket global scope'u yoktur.

Kuyruk işi company_id + period_id/yıl bağlamını taşır ve başlangıçta PeriodContext kurar. Web oturumundaki aktif bağlama güvenen queue kodu hatadır.

Şirketler arası kopyalama tek istisna olarak geçici `period_source` bağlantısı kullanır; yine global scope kaldırma yöntemi kullanılmaz.
