# 21 — Milestone Durum / Capability Matrisi

Bu belge `16_UYGULAMA_SIRASI_MILESTONE.md` içindeki resmî V4.2 milestone numaralarını mevcut `main` implementasyonu ile reconcile eder.

## Durum sözlüğü

- **DONE**: milestone kapsamının ana vertical slice'ı merge edilmiş ve Foundation gate ile korunmuştur.
- **PARTIAL**: sonraki milestone'a ait kullanılabilir foundation/erken capability vardır; milestone exit gate tamamlanmış sayılmaz.
- **PENDING**: resmî milestone için tamamlanmış vertical slice / exit kanıtı yoktur.
- **OPS BLOCKER**: business kodundan bağımsız repo/production operasyon koşulu açıktır.

> PR başlığındaki tarihsel `Mxx` etiketi tek başına resmî milestone numarası değildir. Özellikle PR #64'ün `M11` etiketi, V4.2 resmî `M11 — Çek/Senet` kapsamını temsil etmez; o PR operasyon/entegrasyon altyapısını erkenden getirmiştir.

## V1 matrisi

| Milestone | Resmî kapsam | Durum | Mevcut kanıt | Kalan exit gap |
|---|---|---|---|---|
| M0 | Repository / Laravel / PostgreSQL / CI Foundation | **DONE + OPS BLOCKER** | Foundation workflow, PostgreSQL/Valkey CI, quality/security/browser gates, local self-hosted benchmark workflow | `main` branch protection/required-check enforcement uygulanmış değil; açık operasyon issue'su tutulur |
| M1 | Core / Company / Users / Settings / UI Shell | **DONE** | M1 exit/hardening PR'ları #3–#5 ve mevcut Core/Company altyapısı | V1 milestone gap yok |
| M2 | Cari Core | **DONE** | PR #6–#13: Account, CRUD, profile, B2B policy metadata, ledger, statement, exit audit | V1 milestone gap yok |
| M3 | Ürün / Katalog | **DONE** | PR #14–#18: SKU identity, CRUD, masters, supplier/files, PostgreSQL search | V1 milestone gap yok |
| M4 | Stok / Depo / Cost Foundation | **DONE** | PR #19–#26: stock ledger, effect authority, availability, reversal, reservation, transfer, count, exit gate | V1 milestone gap yok |
| M5 | Teklifler / Tax Calculation Contract | **DONE** | PR #28–#33: deterministic calculator, quote CRUD/revisions/approval/PDF/exit gate | V1 milestone gap yok |
| M6 | Satış Siparişleri | **DONE** | PR #34–#41: CRUD, search, reservation, progress/reversal, KDV-zero, exit gate | V1 milestone gap yok |
| M7 | İrsaliye / Sevkiyat | **DONE** | PR #44–#49: dispatch CRUD, quantity contract, stock OUT, finalize/reversal, exit gate | Gerçek kargo API M28 scope'udur |
| M8 | Satış Faturaları | **DONE** | PR #50–#57 + #71: invoice modes, tax, capacity, account/stock effects, PDF/e-document seam, reconciliation hardening | Production e-document provider M20/provider gate'ine bağlıdır |
| M9 | Satınalma | **DONE** | PR #58–#62 + #66 hardening: PO, Goods Receipt, quality reclass, Supplier Invoice, Purchase Return, landed-cost revaluation | V1 milestone gap yok |
| M10 | Tahsilat / Ödeme / Kasa / Banka / Treasury | **DONE** | PR #65: immutable treasury ledger, collection/payment, POS, expense, transfer, cash count, statement import/reconciliation | Cross-currency A-07 kapatılmadıkça same-currency sınırı geçerli |
| M11 | Çek / Senet | **PENDING** | K-025/K-026 business contract ve treasury payment-method seam'i mevcut | Instrument custody/lifecycle, ciro, settlement/reversal, files/UI/RBAC/DB invariants/tests tamamlanmalı |
| M12 | Return / RMA Core | **DONE** | PR #68: sales return/RMA lifecycle, stock/account correction, PostgreSQL guards, UI/tests; purchase return M9'da mevcut | Provider-specific return connector'ları M17/M18'e aittir |
| M13 | Report Platform + Commercial Core Reports | **DONE** | PR #69: finance snapshot, aging, stock valuation/movement lineage, filters, CSV, RBAC/tests | Future domain raporları kendi milestone'larında eklenir |
| M14 | Basit Üretim | **PENDING** | K-019 contract ve mevcut stock/cost authority yeniden kullanılabilir | Reçete → üretim emri → material issue → mamul receipt → complete vertical slice + raporlar |
| M15 | Fason | **PENDING** | K-020/K-048 custody contract mevcut | Sent material/subcontract custody/finished goods/fire/remaining/complete vertical slice + raporlar |
| M16 | İthalat / Konteyner | **PENDING** | M9 landed-cost primitives yeniden kullanılabilir | A-17 kapanışı + import file/shipment/container/mapping/handoff/cost analysis/reports |
| M17 | E-Ticaret Integration Core + WooCommerce | **PARTIAL** | PR #64/#66: encrypted connection vault, webhook inbox/outbox, retry, WooCommerce/Trendyol inbound order mapping | Resmî M17 Channel Center, mapping, desired-state stock/price, return/invoice/settlement contracts ve WooCommerce exit gate tamamlanmalı |
| M18 | Verified Marketplace Adapter Pack | **PARTIAL** | Trendyol için erken inbound-order capability #64/#66 içinde var | TY tam capability exit gate + HB/AMZ/N11/PTT/IDF/ALG adapter alt-milestone'ları gerçek contract fixture ile tamamlanmalı |
| M19 | B2B / Bayi Sistemi | **PARTIAL** | M2.5 Account B2B access-policy metadata mevcut | Ayrı B2B auth context, B2BUser, session/reset/rate-limit, catalog/cart/order/history/risk/invoice/statement tamamlanmalı |
| M20 | Communication / System Integrations / API | **PARTIAL** | PR #64: notification templates/delivery + async operations foundation; provider-neutral e-document seam M8'de var | `/api/v1`, production provider adapters, template/version/test UX ve ilgili A-08/A-09/A-10/A-11 gates tamamlanmalı |
| M21 | Product Image Operations | **PARTIAL** | M3.4 private product media/file foundation mevcut | destination sets, main/gallery/order, copy/move/edit/crop/resize/provider metadata/quarantine lifecycle tamamlanmalı |
| M22 | Product Installation PDF Builder | **PENDING** | Versioned/private PDF/file primitives mevcut fakat domain-specific installation builder exit kanıtı yok | steps/warnings/tools/parts/images/A4 preview/versioned output vertical slice |
| M23 | Security / Backup / Operational Hardening / Production Candidate | **PARTIAL** | PR #64/#66: security events/IP rules, health/worker heartbeat, encrypted backup/restore implementation; current Foundation security gate | A-03/A-14/A-15; restore drill, recovery barrier, full auth/isolation review, performance/query-plan hardening ve `main` protection verification |
| M24 | Migration / Go-Live | **PENDING** | Migration/ledger/idempotency primitives hazır | A-16 + production identity policy; migration rehearsal/reconciliation/cutover/full enabled-channel regression/go-live gate |

## Bir sonraki uygulama sırası

1. **M11 Çek/Senet** — Commercial Functional Gate'in tek açık domain milestone'u.
2. **M14 Basit Üretim**.
3. **M15 Fason**.
4. **M16 İthalat/Konteyner** — cost-posting slice öncesi A-17 kapatılır.
5. **M17 E-Ticaret Core + WooCommerce**.
6. **M18 adapter pack** — yalnız gerçek provider contract/fixture doğrulanan adapterlar.
7. **M19 B2B**.
8. **M20 Communication / API**.
9. **M21 Product Image Operations**.
10. **M22 Installation PDF Builder**.
11. **M23 Production Candidate hardening**.
12. **M24 Migration / Go-Live**.

## Reconciliation kuralı

Bundan sonra milestone kapatılırken aynı değişiklik setinde:

1. bu matriste durum güncellenir,
2. ilgili owner plan / locked decision güncellenir,
3. representative test/CI kanıtı kaydedilir,
4. exact final `main` HEAD Foundation sonucu doğrulanır.

`PARTIAL` bir capability'nin var olduğunu söyler; milestone'un tamamlandığını söylemez.