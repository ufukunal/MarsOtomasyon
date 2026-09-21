# Finance / Treasury Module Plan

Status: PLAN-007 COMPLETED / FROZEN — planning only.

## Purpose

Finance / Treasury owns authoritative monetary truth for:
- Party receivable/payable balances;
- Cash balances;
- Bank book balances;
- Collection, Payment and Refund;
- Treasury transfers and FX conversion;
- Bank statement evidence and reconciliation;
- credit/risk/hold signals;
- financial period control;
- inventory valuation / cost authority.

Physical stock remains Inventory/Warehouse-owned.

## Authoritative ledgers

- Account Ledger: Party-role receivable/payable truth.
- Cash Ledger: physical/company cash-account money truth.
- Bank Ledger: company bank book money truth.
- Inventory Valuation / Cost Ledger: financial carrying-value and cost truth associated with physical inventory events.

Mutable Party/customer/supplier balance fields are forbidden.

## Core frozen rules

- Sales Invoice POST increases CUSTOMER receivable.
- Collection decreases CUSTOMER receivable and increases Cash/Bank.
- Supplier Invoice POST increases SUPPLIER payable.
- Payment decreases SUPPLIER payable and decreases Cash/Bank.
- Collection and Payment are balance-only. Neither creates authoritative Invoice allocation/open-item state.
- Customer and Supplier role balances remain separate even when the same Party has both roles.
- Cross-role netting is never automatic; explicit role-netting transaction is permissioned, reasoned and approved.
- Cash/Bank transfer is one Finance transaction with paired source OUT and target IN entries.
- FX transfer stores source amount/currency, target amount/currency, actual transaction rate and base values.
- Posted financial history is append/reversal, never silent mutation.
- Bank statement import is evidence/staging; it does not change Bank Ledger by itself.
- Reconciliation matches statement evidence to posted Bank Ledger movements; suggestions are never authority.
- Unmatched statement rows remain unmatched and do not change book balance.
- Financial posting period gates are enforced server-side.
- Inventory valuation uses perpetual moving weighted average by company + Product/Variant valuation pool.
- Internal Warehouse transfer does not change inventory carrying value.
- Physical Dispatch removes inventory carrying value at current moving average and freezes a dispatch cost basis.
- Financial COGS remains recognized at Sales Invoice POST from eligible frozen cost basis, preserving the Sales contract.
- Positive inventory count adjustment cannot silently use zero cost.

## V38 classification

KEEP / ADAPT:
- Bakiye Listesi
- Detaylı Cari Ekstre
- Alacak / Tahsilat
- Borç / Ödeme
- Refund / Para İadesi
- Kasalar / Kasa Hareketleri / Kasa Sayımı
- Avanslar
- Banka Hesapları / Banka Hareketleri
- Virman / FX Transfer
- Ekstre İçe Aktar
- Banka Mutabakatı
- Risk / Kredi Limitleri
- Stok Maliyeti

REMOVE AS AUTHORITY:
- V38 Open Items / Açık Kalemler invoice settlement semantics.
- V38 settlement workspace semantics that allocate Collection/Payment to invoices.

Those old screens may inform read-only projections or explicit Party-role netting UX, but cannot create Invoice paid/open authority because frozen Sales B001 is newer and higher priority.

Checks/Promissory Notes shown under V38 Finance are not planned here; they are the next P2 dependency.

## Planning progress at PLAN-007 start

- Master sequence: 6 / 30 = 20.0%
- P2 Core Commercial: 5 / 8 = 62.5%

On successful PLAN-007 completion:
- Master sequence: 7 / 30 = 23.3%
- P2 Core Commercial: 6 / 8 = 75.0%

## Files

- plan.md
- workflows.md
- forms.md
- data-contract.md
- permissions.md
- integrations.md
- reports.md
- acceptance-criteria.md
- full-test-day.md

## Out of scope

- SQL/schema/migrations;
- C#/API/TypeScript implementation;
- statutory general-ledger/chart-of-accounts implementation;
- e-ledger/e-invoice provider implementation;
- Checks/Promissory Notes workflow;
- Returns/RMA workflow;
- production tax filing/legal reporting;
- Full Test Day execution.
