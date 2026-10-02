# G-802 — Reçete ve revizyon yönetimi

## Amaç

Tek aktif reçete ve immutable Rev.N geçmişini, output_quantity tabanlı component miktarlarıyla uygulamak.

## Önkoşul

G-801.

## Dokunulacak dosyalar

- Recipe list/detail Livewire
- CreateRecipeRevision Action
- recipe validation
- recipe tests

## Şema / Kod

Reçete:

- product
- revision_no
- output_quantity
- component/unit/quantity/base_quantity/conversion_factor

## Kurallar

- Bir mamulde tek aktif reçete.
- Eski revision mutate edilmez.
- Fire yüzdesi yok.
- Eksik unit conversion blok.
- Yeni production order varsayılan son aktif revizyonu kullanır.

## Kabul ölçütü

- Rev.1 → Rev.2 ayrı kayıt.
- Rev.1 değişmiyor.
- Aynı mamulde iki aktif reçete engelleniyor.
- output_quantity oranlama doğru.
- unit conversion snapshot doğru.

## İstem

> K-132…K-134 reçete revizyon modelini immutable şekilde uygula.
