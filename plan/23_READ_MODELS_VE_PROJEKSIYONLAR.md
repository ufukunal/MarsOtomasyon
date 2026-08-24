# 23 — Read Models ve Projeksiyonlar

## İlke
Write authority ile hızlı liste/rapor okuma modeli aynı şey olmak zorunda değildir. Ancak V1'de gereksiz CQRS altyapısı kurulmaz.

## Authority örnekleri
- cari: `account_transactions`
- stok: `stock_movements`
- belge: kendi aggregate tabloları

Liste/dashboard için gerektiğinde özet/projection kullanılabilir.

## Aday projeksiyonlar
- cari güncel bakiye
- ürün/depo stok özeti
- reserved/available stok
- sipariş sevk/fatura progress
- dashboard günlük satış/tahsilat
- integration channel health/counts

## Tutarlılık
Finans/stok kritik kullanıcı işlemi sonrasında ekranda doğru sonuç gerekiyorsa projection aynı transaction içinde veya authoritative query ile sağlanır. Eventual consistency kullanıcıyı yanıltacak yerde kullanılmaz.

## Rebuild
Her projection authoritative kaynaktan yeniden üretilebilir olmalıdır. Projection delete/rebuild business geçmişini kaybetmez.

## Cache ilişkisi
Valkey projection authority değildir. DB read model cache'lenebilir; invalidation başarısız olsa bile cache temizlenerek doğru sonuç yeniden üretilebilir.

## Rapor
Hazır raporlar ilk aşamada PostgreSQL query/read service üzerinden çalışır. Performans ölçümü gerektirirse materialized view/summary table eklenir; tahmine dayalı optimizasyon yapılmaz.

## Search
FTS/trigram arama indexi bir read capability'dir; product/account authority değildir.

## İzleme
Projection lag/asenkron rebuild varsa health metriği üretir. Kullanıcı-visible kritik raporda stale veri açıkça engellenir veya zaman damgası gösterilir.
