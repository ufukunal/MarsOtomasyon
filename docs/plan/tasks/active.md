# Active Tasks

## FW-IMP-007 — Docker/test deployment baseline
**Status:** READY — IMPLEMENTATION NOT STARTED

Predecessor:
- FW-IMP-006 — COMPLETED
- canonical evidence: `docs/plan/01-foundation/fw-imp-006-implementation.md`
- tested implementation commit: `1264981655329099a086c7048c0890caf04dc9f2`
- successful workflow run: `35729371845`

Repository-defined scope:
- application images;
- Docker Compose test baseline;
- migration/startup policy;
- deploy to separate test server;
- readiness;
- small smoke evidence.

Mandatory preflight before mutation:
- verify test Docker version;
- verify test Docker Compose version;
- verify test deployment/service layout;
- verify test URL/DNS/TLS facts needed for the chosen path;
- read test credentials only from canonical source and never expose values.

No current owner-decision blocker is identified for beginning the test-deployment baseline.

Do not silently select:
- production secret store;
- production reverse proxy/tunnel;
- structured logging/metrics stack;
- Desktop shell technology;
- Mobile shell technology.

Do not describe test deployment choices as accepted production architecture.

Exact planning progress:
- master: 9 / 30 = 30.0%
- P2: 8 / 8 = 100.0%

Heavy tests remain Full Test Day only.
