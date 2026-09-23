# Active Tasks

## PARTY-IMP-006 — Party Master Completion Tranche
**Status:** READY FOR IMPLEMENTATION

Readiness:
- `docs/plan/03-cariler/p5-party-master-completion-readiness.md`

Owner direction:
- broad Party implementation tranche instead of one package per small capability.

Includes:
- Party read/list/detail and legal/display edit;
- Contact/Communication;
- Address lifecycle;
- existing TR Tax Identity read/masking/lifecycle;
- External Mapping;
- explicit Merge/lineage;
- required persistence/API/UI/permission/audit/idempotency/concurrency/tests.

Deferred:
- fuzzy duplicate algorithm/candidate generation;
- Reactivation duplicate/legal-identity gate;
- provider/GIB and non-TR tax;
- Communications consent/preferences;
- Sales/Purchasing/Finance behavior;
- production deployment;
- Full Test Day.

Predecessor PARTY-IMP-005 evidence:
- Build `35922536747` — SUCCESS
- Test Deploy `35922536768` — SUCCESS
- tested SHA `4ab2a30bfffdf64c9e5b7634708ad4cdacdde33a`

Exact planning progress:
- master: 9 / 30 = 30.0%
- P2: 8 / 8 = 100.0%

Heavy tests remain Full Test Day only.
