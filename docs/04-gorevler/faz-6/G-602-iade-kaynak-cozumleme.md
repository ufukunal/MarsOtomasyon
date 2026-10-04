# G-602 — İade kaynak çözümleme

## Amaç

Aynı dönem, önceki dönem ve kaynaksız iade kaynaklarını tek doğrulama akışında çözmek.

## Önkoşul

G-601.

## Dokunulacak dosyalar

- ReturnSourceResolver
- iade kaynak seçim bileşeni
- cross-period read service
- source snapshot tests

## Şema / Kod

same_period:

- posted source invoice zorunlu
- source_line_id gerçek FK
- return_sources source_mode=same_period

prior_period:

- eski period read-only açılır
- posted source doğrulanır
- scalar ids + frozen snapshot kopyalanır

manual:

- ayrı permission
- reason/audit
- source ids null

## Kurallar

- Eski period mutate edilmez.
- Kaynak ürün/cari doğrulanır.
- Frozen fiyat/KDV/unit/conversion kaynaklı iadede değiştirilmez.
- Kaynaksız fiyat/KDV manuel.
- Cross-period context işlem bitince kapatılır.

## Kabul ölçütü

- Üç source mode çalışıyor.
- Kapalı eski period değişmiyor.
- Prior-period snapshot doğru.
- Manual izinsiz reddediliyor.
- Kaynaklı satır frozen değerleri UI'da değiştirilemiyor.

## İstem

> K-099/K-107/K-109/K-110 kaynak çözümünü tek servisle uygula; cross-period FK kurma.
