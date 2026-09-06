# 21 — Milestone Durum / Capability Matrisi

Bu belge `16_UYGULAMA_SIRASI_MILESTONE.md` içindeki resmî V4.2 milestone numaralarını mevcut `main` implementasyonu ile reconcile eder. Post-V1 M25–M32 için owner plan `28_PLANLI_GENISLEMELER.md`'dir.

## Durum sözlüğü

- **DONE**: milestone kapsamının ana vertical slice'ı merge edilmiş ve Foundation gate ile korunmuştur.
- **PARTIAL**: sonraki milestone'a ait kullanılabilir foundation/erken capability vardır; milestone exit gate tamamlanmış sayılmaz.
- **PENDING**: resmî milestone için tamamlanmış vertical slice / exit kanıtı yoktur.
- **OPS BLOCKER**: business kodundan bağımsız repo/production operasyon koşulu açıktır.

> PR başlığındaki tarihsel `Mxx` etiketi tek başına resmî milestone numarası değildir. Özellikle PR #64'ün `M11` etiketi, V4.2 resmî `M11 — Çek/Senet` kapsamını temsil etmez; o PR operasyon/entegrasyon altyapısını erkenden getirmiştir.

## V1 matrisi

| Milestone | Resmî kapsam | Durum | Mevcut kanıt | Kalan exit gap |
|---|---|---|---|---|
| M0 | Repository / Laravel / PostgreSQL / CI Foundation | **DONE + OPS BLOCKER** | Foundation workflow, PostgreSQL/Valkey CI, quality/security/browser gates, local self-hosted benchmark workflow | `main` branch protection/required-check enforcement uygulanmış değil; Issue #2 açık tutulur |
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
| M11 | Çek / Senet | **DONE** | PR #72; received/issued cheque/senet, custody/ciro, delivery-time cari effect, bank settlement, reversal, files/UI/RBAC/PostgreSQL acceptance; merge `b3d71e0665f76028a6ccb36b5ef0551415427fd1` | V1 milestone gap yok |
| M12 | Return / RMA Core | **DONE** | PR #68: sales return/RMA lifecycle, stock/account correction, PostgreSQL guards, UI/tests; purchase return M9'da mevcut | Provider-specific return connector'ları M17/M18'e aittir |
| M13 | Report Platform + Commercial Core Reports | **DONE** | PR #69: finance snapshot, aging, stock valuation/movement lineage, filters, CSV, RBAC/tests | Future domain raporları kendi milestone'larında eklenir |
| M14 | Basit Üretim | **DONE** | PR #73; reçete → üretim emri → material issue/fire → mamul receipt → complete, technical file + report; merge `f3b30c059e2294ba2f542ff479cde142725e04b4`; main Foundation run `33287261767` 4/4 | V1 milestone gap yok |
| M15 | Fason | **DONE** | PR #74; physical OUT → subcontract custody quantity/carrying value → fire/partial finished-goods IN → reconcile/complete + files/report; merge `57173a2678c8a44ae38fd7df7c73e062f9caba41`; main Foundation run `33288273051` 4/4 | V1 milestone gap yok |
| M16 | İthalat / Konteyner | **DONE** | PR #75; file/container/package/component/location, finalized GoodsReceipt handoff, landed-cost allocation/posting, reports/lists/simulator; merge `98de2a0c65f0c2cec63e7aebc10660b6eca7cab9`; exact main Foundation run `33292866739` 4/4 | V1 milestone gap yok |
| M17 | E-Ticaret Integration Core + WooCommerce | **DONE** | PR #76; Channel Center/settings, encrypted credentials, WooCommerce connection test, product mapping/media, versioned stock-price desired state + stale guard, webhook/polling idempotency, stock problem/retry, return/invoice/settlement seams; merge `8bb31c70ae9b3953d2cf477bfa88bba1c3b0464a`; exact main Foundation run `33320300545` 4/4 | V1 milestone gap yok; gerçek merchant credential/production doğrulaması provider/account bazlı operasyon kanıtıdır |
| M18 | Verified Marketplace Adapter Pack | **DONE** | PR #77 Trendyol contract adapter; PR #80 HB/AMZ/N11/PTT/IDF/ALG pack; PR #83 malformed fixture + Problem Center hardening; PR #84 n11 `stockCode` inbound identity; `MarketplaceCapabilityContract` provider media/operation/smoke boundary'sini fail-closed kilitler; `MarketplaceOrderPollCursor` + PostgreSQL tests restart-safe page/token/window watermark akışını ve Amazon Orders `2026-01-01` `orderId`/`paginationToken` contractını korur | V1 kod milestone gap yok; gerçek merchant credential, whitelist ve SIT/production çağrı kanıtı provider/account bazlı operasyon doğrulamasıdır ve bu kanıt olmadan status `verified_marketplace` yapılmaz |
| M19 | B2B / Bayi Sistemi | **DONE** | PR #86 + #87: internal `web` guard'dan ayrı B2B auth/session, Account'a pre-bound immutable ULID `B2BUser`, lifecycle/password reset/auth-version revoke/rate-limit, typed role/permission + account-policy ceiling, readonly cari portalı, catalog/search/account product visibility, stock ve satış fiyatı−Cari İskontosu, cart + mevcut `SalesOrder` reuse, PostgreSQL advisory-lock idempotency, risk/exposure policy, history/invoice/statement, immutable-ULID address management ve external B2B audit actor metadata; `B2BAuthenticationTest`, `B2BPortalExitGateTest`, `B2BCompletionGapTest` | V1 milestone gap yok |
| M20 | Communication / System Integrations / API | **DONE** | PR #90; hashed bearer credentials, typed permissions, per-token rate limiting, write idempotency/replay/drift guard, versioned `/api/v1` + OpenAPI, scanner enrollment/auth/job lifecycle, integration kill-switch; merge `43244e9b6e33975ae67a11195ccc5eef0cded074`; exact post-merge Foundation run `33694583085` 4/4 | V1 kod milestone gap yok; A-08/A-09/A-10/A-11 gerçek production provider seçim/credential kanıtı ilgili deployment slice'ının operasyon gate'idir |
| M21 | Product Image Operations | **DONE** | PR #91; private media foundation üzerine tek ana görsel + galeri sırası, site/kanal destination set kimlikleri, aynı FileAsset'i yeniden kullanan copy/move, tahribatsız crop/rotate/flip/resize reçetesi, provider validation metadata, global file quarantine/release, V16.3 resources UI + `products.manage` authorization, PostgreSQL `jsonb`/partial-unique/check invariantları; `ProductImageOperationsTest` + `M21ProductImageHttpExitGateTest` | V1 milestone gap yok; binary image mutation zorunlu değildir, edit reçetesi orijinal private dosyayı değiştirmeden metadata olarak saklanır |
| M22 | Product Installation PDF Builder | **DONE** | PR #92; `ProductInstallationDocumentService`; steps/warnings/tools/parts/images taslağı; immutable private PDF + SHA-256 + source fingerprint + idempotent publish; exact-main Foundation run `33760236708` success | V1 milestone gap yok |
| M23 | Security / Backup / Operational Hardening / Production Candidate | **DONE** | PR #93 merged as `be99ca9b4bda082069c66c9d4c4ed2dbd12f8a94`; security/backup/recovery/operational/report/query-plan/deployment hardening; exact-main Foundation run `33937385421` success | Business milestone gap yok; `main` branch protection enforcement M0 OPS BLOCKER / Issue #2 olarak ayrıca açık |
| M24 | Migration / Go-Live | **DONE** | PR #100 clean integration merged as `3a57d25295047e80c091ba9bdb43424bcd137f56`; stable legacy source identity, fingerprint drift guard, staged payload hash, dry-run→live, reconciliation/cutover gates; exact-main Foundation run `33995672807` success | V1 milestone gap yok |

## Post-V1 planlı genişlemeler

Owner plan: `28_PLANLI_GENISLEMELER.md`.

| Milestone | Resmî kapsam | Durum | Mevcut kanıt | Kalan exit gap |
|---|---|---|---|---|
| M25 | Product Family / Variant | **DONE** | PR #107 merged as `3a9697eb23c5a2e762304104c9339812f07c8d18`; exact-main Foundation run `33999013819` success | Post-V1 milestone gap yok |
| M26 | Barkod / Termal Etiket | **DONE** | PR #105 aggregate final integration içinde merge edildi; final exact-main `22e914ca321c7b3a3c4843a0865b8ee106a7da58`; Foundation run `34032970676` success | Post-V1 milestone gap yok |
| M27 | Mobil Depo / Scanner | **DONE** | PR #105 aggregate final integration içinde merge edildi; client-operation/idempotency ve mobile warehouse flow; Foundation run `34032970676` success | Post-V1 milestone gap yok |
| M28 | Kargo API Adapterları | **DONE** | PR #105 aggregate final integration içinde merge edildi; shipping provider adapters + canonical dispatch source-address fixture hardening; Foundation run `34032970676` success | Post-V1 milestone gap yok |
| M29 | OCR Belge Okuma | **DONE** | PR #105 aggregate final integration içinde merge edildi; reviewed document extraction pipeline; Foundation run `34032970676` success | Post-V1 milestone gap yok |
| M30 | Hafif CRM | **DONE** | PR #109 merged as `c5b3cedd030015d9a0c7c797ed8a8921d16dcad3`; CRM lifecycle/company-owner scope/RBAC/audit/commercial links/private attachments; exact-main Foundation run `34043752661` success | Post-V1 milestone gap yok |
| M31 | BI Export | **DONE** | PR #111 merged as `11c0b5c5365d131b90b4141afe3451f9ea442b21`; curated datasets/PII policy/scheduled runtime reauthorization/read-only BI workspace; exact-main Foundation run `34053943586` success | Post-V1 milestone gap yok |
| M32 | CAD / 3D Viewer | **DONE** | PR #112 merged as `039a8075502090ce0d1b22b89dbc7a48d8f2b8a9`; provider-agnostic derivative contract, cloud opt-in, local DXF/OBJ read-only render, DWG provider contract, normalized failures, real DXF+OBJ browser fixture coverage; exact-main Foundation run `34060544368` success | Post-V1 milestone gap yok; production cloud provider credential/lisans doğrulaması deployment/provider operasyon kanıtıdır |

## Aktif uygulama sırası

**Yok.** Resmî V1 `M0–M24` business kapsamı ve owner-planlı post-V1 `M25–M32` kapsamı tamamlandı.

Yeni bir business milestone ancak karar/entry-gate süreciyle roadmap'e alınır. Tarihsel stacked branch'ler yeni roadmap authority'si değildir.

M0 için `main` branch protection + required Foundation enforcement ise business roadmap'den ayrı **OPS BLOCKER** olarak Issue #2'de açık kalır.

## Reconciliation kuralı

Bundan sonra milestone kapatılırken aynı değişiklik setinde:

1. bu matriste durum güncellenir,
2. ilgili owner plan / locked decision güncellenir,
3. representative test/CI kanıtı kaydedilir,
4. exact final `main` HEAD Foundation sonucu doğrulanır.

`PARTIAL` bir capability'nin var olduğunu söyler; milestone'un tamamlandığını söylemez. `OPS BLOCKER` ise tamamlanmış business milestone'ını yeniden açmaz; kendi operasyon kabul kriteriyle ayrıca kapanır.
