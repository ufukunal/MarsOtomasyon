# Test Environment

## Purpose
This document records only verified facts about the MarsOtomasyon test environment. Unknown infrastructure details must remain UNKNOWN until verified.

## Verified facts
- A dedicated test server exists for the pre-accounting / MarsOtomasyon project.
- A dedicated self-hosted/local runner exists on a separate Runner VM/server.
- The Runner VM and the test server are two different machines.
- PostgreSQL is available as a Docker container on the test server.
- The test environment is separate from production in project planning.

## Current verified topology
```
Private GitHub repository
        |
        v
Runner VM / self-hosted runner (mars-ci)
        |
        | Tailscale / network path
        v
Test server (mars-prod.taila20365.ts.net)
        |
        +--> application test deployment target
        |
        +--> PostgreSQL Docker
```

The Runner VM is NOT the test server. Commands executed by GitHub Actions run on the Runner VM first; any remote test-server operation must then cross the network/Tailscale boundary to the separate test server.

This diagram does not imply that every future Mars service already exists or is already deployed.

## UNKNOWN — do not invent
The following are not yet verified in repository sources:
- operating system/version
- runner service configuration details not yet recorded in this document
- whether runner executes directly on host or inside a container
- Docker Compose availability/version
- database name
- database user
- published/internal PostgreSQL port
- volume path/name
- backup location
- application functional health beyond the verified running container state
- reverse proxy / tunnel details for test
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
- By explicit project-owner decision, TEST-ONLY credentials may be stored in this private repository in a dedicated tracked credentials file.
- This exception applies only to the local MarsOtomasyon test environment; production credentials remain forbidden in Git.
- Credential values must never be echoed in logs, build output, test output, screenshots, or assistant final reports.
- If repository visibility/access changes, test credentials must be rotated immediately and the historical Git exposure must be treated as compromised.
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
1. test server IP
2. test server OS/version
3. Runner VM OS/version, runner type/version/service account
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
- self-hosted/local runner exists on a separate Runner VM/server
- Runner VM and test server are distinct machines
- PostgreSQL Docker is installed/available on the test server
- test server address is mars-prod.taila20365.ts.net
- repository visibility is private

No additional infrastructure facts should be inferred from that statement.


## Test credential storage decision
Project-owner decision:
- Repository is private.
- Human repository access is currently limited to the project owner; connected AI tooling may access it only through the authorized repository connection.
- Plaintext storage of TEST-ONLY server/PostgreSQL credentials in the repository is explicitly permitted by the owner for this environment.
- Production secrets are excluded from this exception.

Dedicated tracked file:
`config/test/test-server.md`

Rules:
- Only TEST environment credentials belong in this file.
- Do not reuse these values for production.
- Do not print the values in logs or final reports.
- Do not copy credentials into multiple documentation files.
- The credential file is the single repository location for these values.
- When credentials change, replace them in the same file and record only that a rotation occurred; do not repeat the old values in documentation.
- If the repository is ever shared, made public, transferred, or additional collaborators are added, rotate the credentials before/at that change.

SSH test-server credentials are configured in the dedicated Markdown credential file and were successfully used for remote authentication. PostgreSQL credentials remain UNKNOWN.


## Machine separation rule
This separation is mandatory in all future plans and diagnostics:

- **Runner VM:** `mars-ci` / self-hosted GitHub Actions execution host.
- **Test server:** `mars-prod.taila20365.ts.net`.
- These are separate servers.
- A successful runner job does not by itself prove SSH/authentication to the test server.
- Test-server facts must come from an explicit remote check against the test server, not from commands executed locally on the Runner VM.
- PostgreSQL Docker belongs to the test server unless later evidence says otherwise.

## Repository visibility
GitHub repository visibility is currently verified as **private**.


## Verified connectivity diagnostic
Read-only verification was executed from the separate self-hosted Runner VM to the separate test server.

### Runner VM
- machine: `mars-ci`
- workflow user: `actions`
- GitHub Actions runner version observed: `2.337.0`
- Tailscale client: available

### Network path
- Tailscale peer: `mars-prod`
- resolved Tailscale IP: `100.88.237.117`
- MagicDNS: PASS
- Tailscale ping: PASS
- TCP/22: PASS

### SSH
- password authentication from Runner VM to test server: PASS
- remote user: `ufuk`
- remote hostname: `mars-prod`

### Remote test server
- kernel: `Linux 6.8.0-139-generic x86_64`
- exact OS distribution/release remains UNKNOWN until separately queried
- `/opt/marsotomasyon`: present

### Docker on test server
- Docker: PASS
- version: `29.8.0`
- `marsotomasyon-web-1`: running
- `marsotomasyon-worker-1`: running
- `marsotomasyon-app-1`: running
- `marsotomasyon-scheduler-1`: running
- `marsotomasyon-postgres-1`: running, healthy
- `marsotomasyon-valkey-1`: running, healthy

### PostgreSQL container
- container: `marsotomasyon-postgres-1`
- image: `postgres:18-bookworm`
- state: `running`
- health: `healthy`

### Valkey container
- container: `marsotomasyon-valkey-1`
- image: `valkey/valkey:8-alpine`
- state observed: running
- health observed: healthy

This verification proves connectivity/authentication and container state only. It does not prove application business health, database integrity, migration correctness, backup/restore, performance, or Full Test Day acceptance.
