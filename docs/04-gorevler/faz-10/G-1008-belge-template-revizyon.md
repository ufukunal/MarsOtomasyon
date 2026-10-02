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

## Kabul ölçütü
- Rev.1→Rev.2 ayrı kayıt.
- Varsayılan tek.
- Preview render olur.
- Eski revizyon değişmez.

## İstem
> Bölüm tabanlı immutable belge template revizyon sistemini uygula.
