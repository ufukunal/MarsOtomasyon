# G-703 — Purchase invoice satırlarını ithalata bağlama

## Amaç

Posted purchase_invoice satırlarını K-120/K-127'ye göre import file'a tam satır bazında bağlamak.

## Önkoşul

G-701, Faz 4 purchase_invoice.

## Dokunulacak dosyalar

- import source picker
- ImportFileLine attach/detach Action
- source validation
- source membership tests

## Şema / Kod

Bağlanan snapshot:

- product
- base_quantity
- purchase_value_base
- purchase_unit_cost_base

Kaynak purchase_invoice frozen kur/maliyet değerlerinden türetilir.

## Kurallar

- Source document posted purchase_invoice.
- Import edilen fiziksel ürün kaynağı yalnız K-257 `line_kind=stock` satırıdır; service satırı `import_file_lines` içine ürün kaynağı olarak bağlanamaz.
- Satır tam bağlanır; miktar split yok.
- Aynı line yalnız bir import file.
- Bir file birden fazla invoice/supplier alabilir.
- Finalized file'a attach/detach yok.

## Kabul ölçütü

- Multi-invoice/multi-supplier çalışıyor.
- Duplicate line membership reddediliyor.
- `line_kind=service` satır import ürün kaynağı olarak reddediliyor.
- Quantity partial attach yapılamıyor.
- Frozen base purchase value doğru.
- Finalized membership immutable.

## İstem

> K-120/K-127 import source membership'i satır bazında tam ve unique uygula.
