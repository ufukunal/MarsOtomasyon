# Test Environment

## Purpose
This document records only verified facts about the MarsOtomasyon test environment. Unknown infrastructure details must remain UNKNOWN until verified.

## Verified facts
- A dedicated test server exists for the pre-accounting / MarsOtomasyon project.
- A local runner is available on that test infrastructure.
- PostgreSQL is available as a Docker container on the test server/infrastructure.
- The test environment is separate from production in project planning.

## Current verified topology
```
GitHub / repository
        |
        v
Local runner on test infrastructure
        |
        +--> test deployment / commands
        |
        +--> PostgreSQL Docker container
```

This diagram does not imply that every future Mars service already exists or is already deployed.

## UNKNOWN — do not invent
The following are not yet verified in repository sources:
- test server hostname
- test server IP
- operating system/version
- runner implementation/provider and exact version
- whether runner executes directly on host or inside a container
- Docker version
- Docker Compose availability/version
- PostgreSQL image/tag/version
- PostgreSQL container name
- database name
- database user
- published/internal PostgreSQL port
- volume path/name
- backup location
- Valkey deployment status on the test server
- Mars API/Web/Worker deployment status
- reverse proxy / tunnel details for test
- DNS hostname for test
- TLS termination details
- monitoring/logging stack

Until verified, these fields must remain UNKNOWN and must not be guessed from other environments.

## Environment separation rules
- Test secrets must be different from production secrets where possible.
- Test DB must not be treated as production truth.
- Production data must not be copied into test without an explicit sanitization/approval policy.
- Test migrations are rehearsed here before production rollout once schema implementation begins.
- Smoke and targeted integration checks may run here during normal development when required.
- Heavy/full suites remain governed by the Full Test Day policy.
- Test failures must not modify production resources.

## Runner responsibilities
The local runner may eventually be used for:
- build
- targeted unit/invariant tests
- migration/contract checks
- deployment to test environment
- small smoke tests
- Full Test Day jobs only when explicitly activated

The runner must not automatically run heavy suites merely because code reaches main.

## PostgreSQL Docker responsibilities
The test PostgreSQL container will be used for:
- schema/migration rehearsal when schema exists
- targeted DB integration checks
- test fixtures
- transaction/concurrency scenarios when explicitly required

Rules:
- persistent volume must be defined before important test data is relied upon
- credentials must not be committed to Git
- PostgreSQL version must be pinned once verified
- backup/restore procedure must be defined before destructive migration rehearsal
- container recreation must not silently destroy needed test data

## Planned test deployment flow
```
main
  -> local runner
  -> build / targeted checks
  -> deploy to test environment
  -> migration rehearsal when applicable
  -> small smoke
  -> evidence/logging
```

Promotion from test to production is a separate controlled step and is not automatic by default.

## Required future verification
Before application deployment starts, verify and record:
1. test server hostname/IP
2. OS/version
3. runner type/version/service account
4. Docker/Compose versions
5. PostgreSQL image/version/container/volume
6. test database/database user naming
7. firewall/network exposure
8. Valkey status
9. test URL/DNS/TLS
10. backup/restore path
11. log/monitoring destination
12. deployment command/service layout

## Source
Verified from explicit project owner statement in the current planning session:
- test server exists
- local runner exists
- PostgreSQL Docker is installed/available on the test server/infrastructure

No additional infrastructure facts should be inferred from that statement.
