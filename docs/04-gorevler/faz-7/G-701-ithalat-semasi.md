# G-701 — İthalat veri modeli

## Amaç

Import file, kaynak satır, expense, allocation ve inventory cost adjustment gerçek kaynak tablolarını kurmak.

## Önkoşul

Faz 4 purchase_invoice, product_costs, K-114…K-129.

## Dokunulacak dosyalar

- import_files migration/model
- import_file_lines migration/model
- import_expenses migration/model
- import_expense_allocations migration/model
- inventory_cost_adjustments migration/model
- schema testleri

## Şema / Kod

Kanonik kaynak:

- `docs/01-veri-modeli/40-imports-and-cost-adjustments.md`

## Kurallar

- Period tablolarda company_id yok.
- purchase_invoice_line unique import membership.
- zero-quantity stock movement yok.
- inventory cost adjustment ayrı tablo.
- expense method yalnız purchase_value|quantity|manual.
- expense type K-116 sabit listesinden.
- finalized file immutable.

## Kabul ölçütü

- Gerçek PostgreSQL migration up/down.
- FK/CHECK/unique kuralları çalışıyor.
- Aynı invoice line ikinci import file'a bağlanamıyor.
- Manual/purchase_invoice source type doğrulanıyor.
- inventory_cost_adjustments fiziksel stock movement FK/quantity semantiği taşımıyor.

## İstem

> Faz 7 veri modelini 40-imports-and-cost-adjustments.md'ye göre uygula; fiziksel stok ve maliyet adjustment kaynaklarını ayır.
