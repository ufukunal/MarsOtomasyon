# Party / Customer / Supplier Integration Contract

Status: FROZEN planning contract. No provider adapter is implemented here.

## 1. General boundary

Party master changes commit locally to PostgreSQL authority.

Reliable downstream notifications follow:
Party transaction → outbox → Worker → consumer/adapter.

External provider success is not the condition for local Party master commit unless a consuming legal workflow explicitly requires verified data before its own action.

## 2. Candidate internal/outbox events

- PartyCreated
- PartyIdentityChanged
- PartyRoleActivated
- PartyRoleDeactivated
- PartyAddressChanged
- PartyContactChanged
- PartyTaxIdentityChanged
- PartyDeactivated
- PartyReactivated
- PartyMerged

Payload principle:
- minimum stable public identity + company + event version;
- sensitive PII is not broadcast unnecessarily;
- consumers reload authorized data when appropriate.

## 3. External identity mappings

External systems/channels may map their counterparty identifier to Mars Party.

Rules:
- provider/external ID is mapping, not canonical Party identity;
- mapping scope includes provider/account/company context where required;
- duplicate callback/import cannot create multiple mappings/Parties silently;
- unknown external counterparty may enter a staged mapping/review flow rather than bypass duplicate controls.

Exact marketplace/e-commerce behavior belongs later Commerce planning.

## 4. Sales integration

Sales consumes:
- Party identity;
- active CUSTOMER role;
- eligible current billing/shipping/contact data.

Sales freezes its own historical snapshot.

Party changes:
- may update future lookup/read models;
- never mutate posted Sales snapshots.

## 5. Purchasing integration

Future Purchasing consumes:
- Party identity;
- active SUPPLIER role;
- eligible supplier contact/address/default references.

PLAN-003 does not define PO/receipt/invoice/payment workflow.

## 6. Finance integration

Finance consumes Party/company identity as counterparty reference.

Finance owns:
- account ledger;
- balances;
- payments/collections;
- risk/credit/hold;
- settlement/netting.

Parties may consume read-only projections/events.

A Party merge event must not cause blind ledger rewrite or balance netting. Finance later defines how merged lineage is presented/reconciled while preserving ledger history.

## 7. e-Document / tax identity boundary

For Turkish e-document use:
- VKN/TCKN and legal name/name-surname/address data are sourced from Party live master before document snapshot.
- consuming Invoice/e-document workflow validates legally required fields.
- local structural validity does not prove e-Fatura registration/enrollment.
- provider/GİB lookup result, when implemented, is integration metadata/projection with timestamp/source.

Current official rules must be verified at implementation/send time.

## 8. Merge propagation

PartyMerged event:
- identifies source and survivor;
- updates search/projection/navigation;
- does not rewrite historical document snapshots;
- consumers must preserve original source lineage where history requires it.

Retries are idempotent; repeated merge-event consumption cannot duplicate business effects.

## 9. Communications boundary

Contacts may later feed Email/SMS/WhatsApp/Push orchestration.

PLAN-003 does not define:
- consent;
- opt-in/opt-out;
- channel preference;
- provider routing.

These require Communications/privacy source contracts.

## 10. Error/reconciliation

Observe:
- external mapping conflict;
- verification provider unavailable;
- duplicate external identity;
- stale Party update;
- event delivery retry/error.

Failures are visible/retryable where applicable and cannot create a second authoritative Party master.
