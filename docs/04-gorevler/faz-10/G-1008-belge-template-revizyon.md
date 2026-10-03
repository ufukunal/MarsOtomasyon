# G-1008 — Belge template ve immutable revizyon

## Amaç
Master document_templates şemasını, section-based designer ve Rev.N yaşam döngüsünü kurmak.

## Önkoşul
PrintManager, K-213…K-218.

## Dokunulacak dosyalar
- document_templates migration/model
- designer Livewire
- revision Actions
- preview renderer
- tests

## Şema / Kod
Kanonik kaynak: model 43, iş kuralı 55.

## Kurallar
- Section-based.
- Serbest drag/drop canvas yok.
- Final revision mutate edilmez.
- Company+template_key tek default active.
- Optimistic lock + audit.
- Bu görev `integrity:templates` kontrolünün sahibidir. Kontrol company+template_key revizyon/default-active invariant'larını ve kayıtlı template definition yapısının kanonik section sözleşmesiyle uyumunu raporlar; token allow-list güvenliği G-1009 kapsamıdır ve otomatik düzeltme yapılmaz.

## Kabul ölçütü
- Rev.1→Rev.2 ayrı kayıt.
- Varsayılan tek.
- Preview render olur.
- Eski revizyon değişmez.
- `integrity:templates` bilerek bozulan revision/default-active/definition invariant'ını mismatch olarak yakalıyor.

## İstem
> Bölüm tabanlı immutable belge template revizyon sistemini uygula.
