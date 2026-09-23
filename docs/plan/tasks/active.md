# Active Tasks

## P5 — Parties first vertical slice — Create Party Core Identity
**Status:** BLOCKED — SCOPE DEFINED / IMPLEMENTATION NOT STARTED

Canonical readiness analysis:
- `docs/plan/03-cariler/p5-first-slice-readiness.md`

Work-package ID:
- NOT ASSIGNED.

Selected slice:
- create one company-scoped PERSON/ORGANIZATION Party before optional role activation;
- first slice owns Party only;
- Party Role, Tax Identity, Contact, Communication Point, Address, External Mapping and Merge Lineage are deferred.

Candidate runtime contract after unblock:
- DB: one Party authoritative structure with company-scoped Party Code uniqueness and optimistic version;
- API: authenticated `POST /api/v1/parties` with server-side `party.create`;
- UI: `/parties/new` identity-only Mars.UI form;
- audit: required;
- idempotency: required candidate for retry-safe create;
- outbox: optional PartyCreated event is deferred without an accepted consumer.

Blocking decisions:
- PARTY-BLK-001 — define Mars-owned permission authority/evaluation for `party.create`.
- PARTY-BLK-002 — define initial Party Code assignment authority.
- PARTY-BLK-003 — freeze fuzzy duplicate-warning contract or explicitly approve first-slice deferral.
- PARTY-BLK-004 — decide physical Company reference strategy because no Company table exists.

Forbidden until unblock:
- Party domain implementation;
- Party EF mapping/migration;
- Party API endpoint;
- Party Web business screen;
- dedicated implementation work-package ID.

Exact planning progress:
- master: 9 / 30 = 30.0%
- P2: 8 / 8 = 100.0%

Heavy tests remain Full Test Day only.
