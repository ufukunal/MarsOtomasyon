# 2026-10-16 — V65 finance release readiness

## Source changes

- Fixed amount-based extra discount validation on sales order confirmation, document posting and return posting.
- Fixed proration of saved extra discount across partial dispatch, invoice and purchase document conversions.
- Source-only regression tests for proration and document verification. The tests are **not executed** in this change.
- Fast-entry rows: clearing product/VAT to zero no longer creates invalid ghost rows.
- Contact transfer: reversal requires a confirmation popup; already reversed transfers do not offer the button.
- Securities status labels: portfolio, issued, banked and collected localized to Turkish.
- A new isolated PostgreSQL integration test verifies zero net transfer, no cash/bank movement, idempotency, overdraft and reversal.

## Deployment gates — strictly Fresh UAT

**Do not deploy the web container before applying the two period schema migrations and synchronizing the new master permissions.** The existing `ops/uat-fresh-web-update.sh` performs read-only schema and permission checks and blocks a web-only rollout until prerequisites are present.

Two *new* migration scripts:

- `database/migrations/period/0001_12_02_000010_add_document_additional_discount.php`
- `database/migrations/period/0001_12_02_000020_create_contact_transfers.php`

The migration must run **only** in the isolated Fresh UAT period database `mars_uat_demo_2026`, using the appropriate period connection, after a verified backup. The master roles must then be synced using the existing `permissions:sync-company-roles` operation on the isolated Fresh UAT master database. Verify the database targets before running either operation. Do **not** apply these steps to PROD or any unrelated period database.

The release procedure must separately verify complete schema presence and effective role grants, not merely the existence of permission names.

## Acceptance requirements

1. PHP syntax/Blade compilation and Pint/Larastan result accepted in a separately approved static-quality run.
2. Integration tests run only on the designated isolated TEST database/Valkey under explicit execution approval.
3. Validate 20% VAT, multi-rate VAT, percent plus fixed discount, partial delivery/invoice and a partial return against the approved business expectation.
4. Verify no money movement on contact transfers, ledger entries always cancel between source/target, and reversal is atomic.
5. Verify contact transfer role visibility/authority and the overdraft popup on a Fresh UAT browser.
6. Compare document grids, edit flow and securities lists against the approved V65 design.

This document is a **source-readiness checklist**, not evidence of completed test execution or live deployment. No deployment or DB migration was performed while authoring it.
