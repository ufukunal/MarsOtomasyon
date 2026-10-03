# G-1111 — Devir öncesi kontrol listesi

## Amaç
Carry başlamadan source/target durumunu ve taşınacak veriyi deterministik preview ile doğrulamak.

## Önkoşul
G-1110 kanonik carry sözleşmesi.

## Dokunulacak dosyalar
- PreviewPeriodCarry
- carry checklist DTO
- PeriodCarry Livewire
- preview tests

## Şema / Kod
İş kuralı 57 kanoniktir.

## Kurallar
- Preview read-only.
- Target business data varsa block.
- In-transit transfer ve open production/subcontract order block.
- Açık sales_order/purchase_order block değildir; carry preview'da kalan miktar ve reservation özetiyle transfer item olarak gösterilir.
- Açık quarantine warning/transfer item; block değil.
- Tüm kapanış toplamları preview'da görünür.

## Kabul ölçütü
- Pass/warning/block ayrımı doğru.
- Quarantine taşınacak olarak listeleniyor.
- Açık production order carry'yi blokluyor.
- Preview veri mutate etmiyor.

## İstem
> Dönem devri ön kontrolünü iş kuralı 57'ye göre uygula.
