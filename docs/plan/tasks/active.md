# Active Tasks

## FW-IMP-005 — API foundation technology decision-gate resolution
**Status:** BLOCKED ON OWNER DECISIONS — IMPLEMENTATION NOT STARTED

Predecessor:
- FW-IMP-004 — COMPLETED
- build/test/migration evidence: GitHub Actions run `35713295141`

Repository-defined FW-IMP-005 scope includes:
- middleware/pipeline;
- auth integration after provider decision;
- error mapping;
- OpenAPI after tooling decision;
- health/readiness.

Required decisions before full FW-IMP-005 implementation:
1. exact authentication/identity provider;
2. exact OpenAPI tooling.

This task is only to resolve those two gates and record accepted decisions.
Do not implement FW-IMP-005 in the same decision-closing session unless the owner explicitly requests combining the tasks.

Do not expand into:
- logging/metrics provider selection;
- file storage selection;
- scheduling library;
- Desktop/Mobile shell technology;
- production secret store;
- reverse proxy/tunnel;
- domain module schema/business rules;
- UI;
- deployment.

Exact planning progress:
- master: 9 / 30 = 30.0%
- P2: 8 / 8 = 100.0%

Heavy tests remain Full Test Day only.
