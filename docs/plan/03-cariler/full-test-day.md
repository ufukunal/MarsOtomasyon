# Party / Customer / Supplier Full Test Day Backlog

Do not run these tests during PLAN-003 planning.

| ID | Risk | Scenario | Expected invariant | Required setup |
|---|---|---|---|---|
| PARTY-FTD-001 | duplicate identity | two users create same VKN/TCKN Party concurrently | one deterministic active identity wins; no silent duplicate | PostgreSQL integration + concurrency |
| PARTY-FTD-002 | fuzzy false-positive | similar legal names but distinct tax identities | warning only; no auto merge | duplicate candidate fixtures |
| PARTY-FTD-003 | dual role | same Party activated as CUSTOMER and SUPPLIER | one Party identity, two role states, no duplicate master | Party + role implementation |
| PARTY-FTD-004 | balance authority | user edits Party while Finance balance exists | balance unchanged; Party has no mutable balance authority | Finance ledger integration |
| PARTY-FTD-005 | snapshot persistence | Party legal name/address/tax ID changes after posted Invoice | historical Invoice snapshot unchanged | Sales Invoice + Party mutation |
| PARTY-FTD-006 | inactive Party | deactivate Party then create new Quote/PO | new counterparty selection rejected according to role eligibility; history readable | Sales/Purchasing integration |
| PARTY-FTD-007 | role-only deactivate | disable CUSTOMER while SUPPLIER stays active | Sales selection blocked, supplier usage remains eligible | dual-role Party |
| PARTY-FTD-008 | reactivate duplicate | inactive Party reactivated after conflicting identity created | deterministic collision blocks unsafe reactivation | identity uniqueness implementation |
| PARTY-FTD-009 | merge history | merge duplicate Party with posted Sales history | source becomes MERGED; snapshots/history preserved; survivor used for future lookup | posted Sales docs |
| PARTY-FTD-010 | merge finance | merge Parties each having Finance history | no balance rewrite/netting/ledger deletion | Finance ledger + merge lineage |
| PARTY-FTD-011 | concurrent merge | same source merged to two survivors concurrently | one deterministic merge lineage; other conflicts | concurrency harness |
| PARTY-FTD-012 | stale edit | two users edit Party identity | stale writer gets conflict; no silent overwrite | optimistic concurrency implementation |
| PARTY-FTD-013 | address history | billing address changed after posted documents | current master changes; old snapshots remain | Sales document snapshot |
| PARTY-FTD-014 | address deactivate | deactivate default shipping address | new selection cannot use inactive address; history remains | address lifecycle |
| PARTY-FTD-015 | tax identity structure | invalid VKN/TCKN length/type | structural validation rejects invalid identity for applicable scheme | validation fixtures |
| PARTY-FTD-016 | e-document readiness | structurally valid Party lacks required recipient legal/address data | e-document workflow blocks send readiness without corrupting Party | e-document integration |
| PARTY-FTD-017 | cross-company isolation | company A actor attempts Party B read/use/update | denied; no cross-company reference | two-company users/data |
| PARTY-FTD-018 | sensitive identity | actor lacks full tax-ID permission | UI/export masks/omits full value; authorized workflow still respects server policy | permission matrix |
| PARTY-FTD-019 | external mapping duplicate | same provider/account external ID ingested twice | one mapping/logical Party relation; no duplicate Party effect | integration mapping |
| PARTY-FTD-020 | merge retry | PartyMerged outbox event retried | projections update idempotently | outbox consumer |
| PARTY-FTD-021 | inactive contact | selected contact becomes inactive after draft creation | posted/frozen document uses its own accepted snapshot; no silent replacement | Sales draft/post flow |
| PARTY-FTD-022 | Party Code concurrency | concurrent Party creation requests next code | codes remain unique under numbering policy | Settings/numbering integration |
| PARTY-FTD-023 | dual-role Finance view | Party has receivable and payable activity | projections remain separately sourced; no implicit netting | Finance ledger |
| PARTY-FTD-024 | merged search | search old Party code/name after merge | result visibly redirects to survivor while source history remains accessible | search/read projection |
| PARTY-FTD-025 | export authorization | export Party list with sensitive fields | export obeys company and tax-identity permissions | export implementation |
| PARTY-FTD-026 | browser/API E2E | create → role → address → deactivate/reactivate → merge | states/actions and source truth remain consistent | deployed test env |
| PARTY-FTD-027 | performance | high-volume Party search/duplicate candidates | bounded paging/search; no unbounded load | representative dataset |
| PARTY-FTD-028 | security/privacy | logs/audit around tax/contact data | no uncontrolled sensitive-value leakage | security review/harness |

## Evidence requirements

When eventually run, record:
- exact test ID/name;
- tested commit/HEAD;
- environment;
- setup/data;
- observed result;
- PASS/FAIL;
- logs/artifacts;
- defect/blocker if failed.

Mock-only results do not prove provider, e-document or production behavior.
