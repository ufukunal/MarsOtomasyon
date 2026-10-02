# G-605 — Kısmi ve çoklu iade

## Amaç

Aynı kaynak fatura satırına birden fazla kısmi iade uygulanmasını güvenli sınırlandırmak.

## Önkoşul

G-602…G-604.

## Dokunulacak dosyalar

- ReturnableQuantity resolver
- source lock helper
- partial return tests
- integrity:returns

## Şema / Kod

```
remaining =
source_quantity
- effective_posted_return_quantity
```

Reverse edilen return effective toplamdan çıkar.

## Kurallar

- Return quantity remaining'i aşamaz.
- Aynı source satır birden çok return'a bölünebilir.
- Source transaction içinde yeniden okunur/kilitlenir.
- Prior-period source snapshot quantity üst sınırdır.

## Kabul ölçütü

- 100 kaynak => 60+40 kabul.
- 60+50 reddediliyor.
- Reversed 60 sonrası 60 tekrar kullanılabilir.
- Concurrent iadeler toplamı source'u aşmıyor.
- integrity:returns aşımı yakalıyor.

## İstem

> K-106 kısmi iade miktarını source-line toplamından hesapla; ikinci gerçek remaining kolonu ekleme.
