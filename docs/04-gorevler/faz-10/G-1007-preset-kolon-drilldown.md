# G-1007 — Preset, kolon kişiselleştirme ve drill-down

## Amaç
Kullanıcı rapor filtre/kolon/sort presetlerini ve kaynak kayda drill-down davranışını eklemek.

## Önkoşul
G-1001.

## Dokunulacak dosyalar
- report_filter_presets migration/model
- preset UI/actions
- column preference UI
- drill-down resolver
- tests

## Şema / Kod
Master report_filter_presets.

## Kurallar
- Shared preset company kapsamı.
- Preset veri formülü değiştiremez.
- Kolon yalnız visibility/order.
- Drill-down mevcut permission'a tabi.

## Kabul ölçütü
- Personal/shared preset çalışıyor.
- Başka company preset görünmüyor.
- Invalid column/formula eklenemiyor.
- Drill-down yetkisiz hedefi açmıyor.

## İstem
> K-210/K-231/K-232 rapor kişiselleştirme katmanını uygula.
