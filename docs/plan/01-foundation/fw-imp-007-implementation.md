# FW-IMP-007 — Docker / TEST Deployment Baseline

Status: COMPLETED
Date: 2026-09-23

## Scope implemented

FW-IMP-007 implemented only the repository-defined Foundation TEST deployment baseline:

- production-capable Docker image definitions for Mars.Api;
- a dedicated EF Core migration image;
- reproducible Mars.Web production-asset build and a minimal TEST-only static/proxy host;
- Docker Compose topology for the Foundation API/Web deployment;
- preservation of the existing TEST PostgreSQL and Valkey topology rather than recreating either service;
- separate migration and runtime PostgreSQL credentials/authority;
- remote TEST preflight, deployment, readiness and smoke automation;
- readiness hardening so pending EF migrations make readiness unhealthy;
- reproducible GitHub Actions evidence from the separate self-hosted runner to the separate TEST server.

No ERP domain feature or business rule was introduced.

## Repository / session state

Repository:
- `ufukunal/MarsOtomasyon`

Branch:
- `main`

Package baseline predecessor HEAD:
- `d4a37dd688d177fdc19b38f19f832ab89b23fae3`

Completion-session observed starting HEAD:
- `631752ca09793213c15f3ed375406b0e2c263ae1`

Final tested implementation commit:
- `2821c98bbd04b81dcbb10e99ddb0a4974ca7a517`

Successful workflow:
- `Foundation Test Deploy`
- run: `35865600657`
- job: `107196252913`
- result: PASS

## Active roles

Primary:
- system-devops-specialist — Docker images, Compose topology, remote TEST deployment, health/readiness and operational evidence;
- software-architect — modular-monolith/service boundaries and TEST-vs-production separation.

Reviewers:
- security-specialist — secret handling, SSH/password masking, container privileges, published ports and TEST-only boundaries;
- database-architect — migration/runtime role separation, PostgreSQL authority and schema-startup safety;
- software-test-engineer — targeted CI/deployment evidence and failed-run history;
- software-developer — API/container compatibility and the minimum readiness adjustment.

## Verified TEST preflight facts

Verified by the successful remote preflight in run `35865600657`:

- runner and TEST server are separate machines;
- runner: existing self-hosted `mars-ci`;
- remote hostname: `mars-prod`;
- TEST DNS name: `mars-prod.taila20365.ts.net`;
- remote OS: Ubuntu 24.04.5 LTS;
- remote kernel: `6.8.0-139-generic`;
- Docker: `29.8.0`;
- Docker Compose: `5.5.1`;
- PostgreSQL container: `marsotomasyon-postgres-1`;
- PostgreSQL image: `postgres:18-bookworm`;
- PostgreSQL status: running / healthy;
- Valkey container: `marsotomasyon-valkey-1`;
- Valkey image: `valkey/valkey:8-alpine`;
- Valkey status: running / healthy;
- existing legacy application containers remained present and were not replaced;
- existing PostgreSQL/Valkey Compose project: `marsotomasyon`;
- existing PostgreSQL/Valkey Compose workdir: `/opt/marsotomasyon`;
- existing Compose files: `/opt/marsotomasyon/docker-compose.production.yml` and `/opt/marsotomasyon/docker-compose.tailscale.yml`;
- existing shared data network: `marsotomasyon_backend`;
- `/opt/marsotomasyon` exists and is readable/writable by the verified remote user;
- TEST Foundation port `5080` was free before deployment;
- port 80 had no listener response;
- HTTPS 443 returned 302 on the pre-existing TEST route;
- canonical SSH credential source was available and values were masked;
- remote PostgreSQL role file existed with mode `600`;
- migration/admin role network login: PASS;
- application runtime role network login: PASS;
- application runtime role is not superuser / createdb / createrole / replication;
- before deployment, EF migration history and Foundation/Identity schemas were absent.

The existing `production` filenames belong to the pre-existing test-server topology and are not treated by this task as MarsOtomasyon production architecture.

## Docker images

### API

File:
- `deploy/docker/api.Dockerfile`

Build/runtime:
- build: `mcr.microsoft.com/dotnet/sdk:10.0.401-noble`;
- runtime: `mcr.microsoft.com/dotnet/aspnet:10.0.12-noble`;
- multi-stage publish;
- deterministic `/app` working directory;
- non-root runtime via `$APP_UID`;
- port 8080 only;
- diagnostics disabled;
- Data Protection key directory is persisted through a Compose volume.

### Migrator

File:
- `deploy/docker/migrator.Dockerfile`

Properties:
- `.NET SDK 10.0.401-noble`;
- pinned `dotnet-ef 10.0.12`;
- dedicated NuGet package path readable by the non-root runtime user;
- non-root execution;
- migration authority is supplied only at deployment time.

The migrator is not an application runtime service.

### Web

File:
- `deploy/docker/web.Dockerfile`

Build:
- Node `24.21.0-bookworm-slim`;
- committed npm lockfile;
- `npm ci`;
- existing Vite production build.

Runtime:
- Node `24.21.0-bookworm-slim`;
- non-root `node` user;
- static `dist` assets;
- TEST-only minimal Node HTTP server/proxy from `deploy/test/web-server.mjs`;
- one published TEST port, normally `5080`.

The Node TEST host is not an accepted production reverse-proxy/ingress technology decision.

## TEST Compose topology

File:
- `deploy/test/compose.yml`

Compose project:
- `mars-foundation`

Services:
- `api`;
- `web`.

Not created by this Compose file:
- PostgreSQL;
- Valkey;
- legacy pre-existing application services.

The Foundation services attach to the already verified external Docker network:
- `marsotomasyon_backend`.

This preserves the existing PostgreSQL/Valkey Compose ownership and avoids destructive recreation or volume replacement.

A dedicated Data Protection volume is owned only by the new Foundation Compose project.

## Worker deployment

No new Mars.Worker container was deployed.

Reason:
- current `src/Mars.Worker/Mars.Worker.csproj` is a class-library project and is not an executable Worker host;
- the repository currently contains the bounded/cancellation-aware outbox processor primitive but no runnable Worker composition root;
- inventing a Worker host in FW-IMP-007 would exceed the smallest-correct deployment scope.

The existing legacy `marsotomasyon-worker-1` container belongs to the pre-existing test topology and was not treated as the new Mars.Worker implementation.

## Migration / startup policy

The TEST deployment policy is explicit:

1. inspect committed migration `Up()` operations in CI for destructive operations before remote deployment;
2. verify EF model drift before deployment;
3. remotely verify PostgreSQL topology and credential separation;
4. build immutable release-tagged API/migrator/Web images;
5. run migrations as a one-shot migrator container before API/Web startup;
6. use the migration/admin PostgreSQL role only for migration;
7. grant the restricted runtime role only the required runtime schema/table/sequence privileges after migration;
8. start API/Web only after migration succeeds;
9. fail deployment immediately if migration fails;
10. readiness is unhealthy when the database is unreachable or committed migrations are pending.

Migration credential:
- TEST migration/admin role (`mars_master`).

Runtime credential:
- restricted TEST application role (`mars_app`).

The runtime role does not receive schema-admin authority.

Rollback/recovery boundary:
- this task performs no production migration;
- committed migrations were screened as non-destructive for the current Foundation structures;
- destructive migration rehearsal and backup/restore drill remain outside normal development and belong to controlled future work / Full Test Day.

## Readiness implementation

Modified:
- `src/Mars.Api/Foundation/Health/PostgreSqlReadinessHealthCheck.cs`

Readiness now requires:
- PostgreSQL reachable;
- no pending committed EF migrations.

Liveness remains process/pipeline liveness only.

This prevents an API with an incompatible or stale schema from reporting ready.

## Configuration and secret boundary

TEST SSH:
- source remains `config/test/test-server.md`;
- workflow extracts values without printing them as report data;
- GitHub masking is applied before use.

TEST PostgreSQL:
- runtime/migration role credentials live on the TEST server in a mode-`600` role file;
- values are injected as environment connection strings only at deployment/runtime;
- values are not committed into Dockerfiles, Compose, documentation, image layers or this report.

A one-time TEST PostgreSQL role rotation was required after an earlier failed-run diagnostic path established that prior TEST role material must be treated as exposed. The repaired rotation:
- updates the PostgreSQL roles over container stdin;
- verifies SCRAM-authenticated network login for both migration and runtime roles;
- persists only the rotated TEST values in the protected remote role file;
- never prints the values.

Production secret storage remains unresolved and was not selected.

## Remote TEST deployment evidence

Successful run:
- `35865600657`

Tested commit:
- `2821c98bbd04b81dcbb10e99ddb0a4974ca7a517`

Remote deployment results:
- API image build: PASS;
- migrator image build: PASS;
- Web image build: PASS;
- TEST migration: PASS;
- runtime grants: PASS;
- Compose config validation: PASS;
- API container start: PASS;
- Web container start: PASS;
- deployment health gate: PASS;
- EF migration history count after deployment: `2`;
- deploy result: PASS.

Deployed new TEST containers:
- `mars-foundation-api-1`;
- `mars-foundation-web-1`.

The release is stored under the remote per-commit Foundation release layout and the current symlink is updated only after deploy success.

## Health / readiness / smoke evidence

Remote deployment-local checks:
- Web `/`: 200;
- Web `/components`: 200;
- protected `/api/v1/foundation/context`: 401 without authentication;
- `/openapi/v1.json`: 200;
- `/health/live`: 200;
- `/health/ready`: 200;
- OpenAPI contains the protected Foundation proof route.

Runner-to-TEST smoke:
- `/`: 200;
- `/components`: 200;
- `/health/live`: 200;
- `/health/ready`: 200;
- `/api/v1/foundation/context`: 401;
- `/openapi/v1.json`: 200;
- final `SMOKE_RESULT=PASS`.

These results are actual TEST-server evidence, not runner-local substitutes.

## Build / test evidence

Final successful workflow also verified:
- frontend targeted tests: 10 / 10 PASS;
- Vite `8.3.0` production build: PASS;
- FW-IMP-006 static architecture checks: PASS;
- .NET Release build: PASS;
- build errors: 0;
- Foundation targeted tests: 26 / 26 PASS;
- committed migration safety scan: PASS for 2 migrations;
- EF pending-model-change check: PASS.

## Failed verification history

Failed history is intentionally preserved.

### Run 35732656996
Failure:
- remote TEST preflight SSH authentication failed.

Correction:
- use a controlled SSH_ASKPASS flow with masked canonical TEST credentials.

### Run 35732782912
Failure:
- remote preflight queried `__EFMigrationsHistory` in a SQL expression that still referenced the relation when it did not yet exist.

Correction:
- split migration-history existence and count checks.

### Run 35735168267
Passed before failure:
- role rotation;
- remote preflight.

Failure:
- API image build used nonexistent `mcr.microsoft.com/dotnet/sdk:10.0.12-noble`.

Correction:
- use released SDK tag `10.0.401-noble`.

### Run 35735210135
Failure:
- same unreleased SDK tag remained in the affected image path.

Correction:
- align image definitions to the released SDK tag.

### Run 35737005865
Passed before failure:
- API image build.

Failure:
- migrator still referenced nonexistent SDK tag `10.0.12-noble`.

Correction:
- align migrator to `10.0.401-noble`.

### Run 35737013944
Passed before failure:
- API/migrator/Web image builds;
- remote preflight.

Failure:
- migrator execution could not access its non-root tool/package runtime correctly.

Correction:
- make the installed EF tool and required package files readable to the non-root user.

### Run 35737502039
Passed before failure:
- all image builds.

Failure:
- EF Design assembly under the NuGet package cache was not readable by the non-root migrator user.

Correction:
- move/pin NuGet package cache to the dedicated readable `/packages` path.

### Run 35737808000
Passed before failure:
- all image builds;
- preflight.

Failure:
- network SCRAM authentication failed for the TEST migration role.

Correction:
- require explicit network-auth verification and investigate role-password application.

### Runs 35738227992 and 35738236567
Failure:
- existing rotation marker exposed that stored TEST role credentials still did not pass network authentication.

Correction:
- add repair behavior and make network login part of rotation acceptance.

### Run 35738539516
Failure:
- diagnostic SQL intended to inspect PostgreSQL auth policy was not reaching `psql` because container stdin was not attached.

Correction:
- pass diagnostic SQL with `docker exec -i`.

### Run 35738662737
Passed before failure:
- frontend verification;
- .NET build/tests;
- migration/model checks.

Failure:
- role repair still failed network authentication.

Root cause:
- the same missing `docker exec -i` defect existed in `apply_passwords()`; the heredoc containing `ALTER ROLE` never reached `psql`, so the role file could diverge from actual PostgreSQL role passwords.

Final correction:
- commit `2821c98bbd04b81dcbb10e99ddb0a4974ca7a517` adds stdin attachment to the actual password-application path.

### Final run 35865600657
- role repair: PASS;
- migration/admin network login: PASS;
- runtime network login: PASS;
- remote preflight: PASS;
- image builds: PASS;
- migration: PASS;
- Compose startup: PASS;
- health/readiness: PASS;
- runner-to-TEST smoke: PASS.

## Security boundaries

Preserved:
- non-root API, Web and migrator execution;
- read-only runtime filesystems for API/Web;
- `no-new-privileges`;
- Linux capabilities dropped for API/Web;
- no production credential in source;
- no credential value in this report;
- no bearer/localStorage browser token baseline;
- protected API remains 401 without authentication;
- runtime PostgreSQL role remains non-superuser/non-role-admin;
- schema migration uses a separate migration credential;
- only the TEST Web port is published by the new Compose project;
- existing PostgreSQL/Valkey published-port/volume ownership is not modified.

## TEST vs production boundary

FW-IMP-007 selected only a TEST deployment mechanism.

Not selected:
- production secret store;
- production reverse proxy;
- production tunnel;
- production DNS/ingress architecture;
- production TLS termination architecture;
- production monitoring/logging vendor or stack;
- production deployment promotion;
- Desktop shell;
- Mobile shell.

The TEST-only Node static/proxy server and port 5080 do not freeze production ingress architecture.

## Infrastructure-change resilience

Preserved:
- application images consume configuration through environment injection;
- PostgreSQL remains authoritative;
- Valkey remains non-authoritative and is not required for this Foundation API readiness proof;
- existing DB/cache Compose ownership is external to the new Foundation Compose project;
- runtime and migration credentials remain separable;
- Web static assets are standard Vite output and are not coupled to the TEST-only host;
- authentication/authorization remains behind the accepted Mars-owned boundaries;
- production ingress and secret-store technologies can be selected later without rewriting ERP domain semantics.

## Decisions preserved

- ADR-0001 — .NET 10 LTS;
- ADR-0002 — EF Core 10 + Npgsql;
- ADR-0003 — ASP.NET Core Identity + OpenIddict 7.7.1;
- ADR-0004 — Microsoft.AspNetCore.OpenApi 10.0.12;
- PostgreSQL authoritative;
- Valkey non-authoritative;
- main-only Git workflow;
- no ERP domain behavior in Foundation deployment;
- heavy suites remain Full Test Day only.

## UNKNOWN / deferred

Still not selected or proven by FW-IMP-007:
- production secret store;
- production reverse proxy/tunnel;
- production DNS/ingress;
- production TLS termination;
- structured logging/metrics backend;
- production deployment;
- backup/restore destructive drill;
- Desktop shell technology;
- Mobile shell technology.

The pre-existing HTTPS 443 route returned 302 during TEST preflight, but this task does not claim ownership of that route or treat it as the Foundation TEST ingress.

## Full Test Day pending

Still deferred:
- full browser E2E;
- Desktop/Mobile E2E;
- full permission/authentication matrix;
- broad concurrency/load;
- backup/restore destructive drill;
- performance;
- broad security regression;
- provider sandbox/regression;
- cross-platform regression.

## Planning metric

Exact planning completion remains unchanged:
- Master: 9 / 30 = 30.0%;
- P2: 8 / 8 = 100.0%.

Foundation implementation completion does not increment section-8 planning completion.

## Next package

Repository tracking names the next package:
- `FW-IMP-008 — thin vertical framework proof`.

However, the repository currently contains no exact implementation scope for FW-IMP-008 beyond that name. Therefore implementation must not start by inventing ERP/domain behavior.

The next session must first define/verify a deliberately minimal, non-domain thin vertical proof scope and acceptance criteria from the accepted Foundation contracts before mutation.
