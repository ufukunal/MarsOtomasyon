# Active Tasks

## FW-IMP-005 — API foundation implementation
**Status:** READY — IMPLEMENTATION NOT STARTED

Predecessor:
- FW-IMP-004 — COMPLETED
- build/test/migration evidence: GitHub Actions run `35713295141`

Repository-defined FW-IMP-005 scope includes:
- middleware/pipeline;
- auth integration after provider decision;
- error mapping;
- OpenAPI after tooling decision;
- health/readiness.

Accepted decisions:
1. ASP.NET Core Identity (.NET 10) + OpenIddict 7.7.1 stable — ADR-0003.
2. Microsoft.AspNetCore.OpenApi 10.0.12 — ADR-0004.

FW-IMP-005 implementation may now begin in a separate implementation session.

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
