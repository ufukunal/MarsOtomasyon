# MarsOtomasyon dedicated test infrastructure — operations

## Actual topology
- `mars-ci`: four permanent GitHub self-hosted runners, R1/R2/R3/R4.
- `mars-prod-1`: Ubuntu 24.04 (`ufukmarsprod`), Tailnet `100.127.235.30`, Docker PostgreSQL 16 + Valkey 8.
- PostgreSQL on private `55432`; Valkey on private `6379`.
- PostgreSQL volume `mars-test-services_postgres_data`; Valkey volume `mars-test-services_valkey_data`. **Never run `docker compose down -v`**.
- Dedicated master test databases `MarsProject_Master_Test_R1`, `MarsProject_Master_Test_R2`, `MarsProject_Master_Test_R3` and `MarsProject_Coverage_Test`. Tests clean their own database contents; database server processes do not get recreated.
- All services are test services; no customer/production database is in scope.

## Verified baseline
GitHub main Quality run [37815414588](https://github.com/ufukunal/MarsOtomasyon/actions/runs/37815414588) is GREEN: R1 56 tests; R2 58; R3 274; R4 21 stock coverage tests, PCOV 92.5%, plus Pint/Larastan/build.

## Monitoring — task 8
`.github/workflows/ops-health.yml` schedules a GitHub Actions run each hour at minute 17 UTC, with optional manual dispatch; R3 on `mars-ci`. `scripts/ci/health-test-infra.sh` checks authenticated PostgreSQL and Valkey service requests, remote identity, Docker active and enabled, TCP bindings, root disk usage (<85%) and available RAM (>=1 GiB). Failures make the job red. GitHub account notification rules control actual delivery; no third-party alert endpoint is configured.

## Durability — task 5
`ops/test-infra/test-service-durability.sh` is a privileged maintenance-window-only tool. Its guarded checks restart ONLY the two isolated Docker containers, verify their healthy status, compare an isolated PostgreSQL durability row and Valkey AOF key before/after restart, verify Docker restart policies and named volumes and delete probes. No scheduled or automatic container restarts are permitted during tests. A full server reboot test is a distinct operational step and requires planned downtime; not interchangeable with container restart.

## Storage — task 7
Read-only VM check [37818092042](https://github.com/ufukunal/MarsOtomasyon/actions/runs/37818092042) confirms /dev/sda 100 GB, /dev/sda3 ~98 GB, root LV ~49 GB and VG free ~49 GB. `ops/test-infra/extend-root-lvm.sh` validates root identity, Tailnet IP, mount source and ext4 before online LV expansion. After executing via authorized admin change, require visible before/after `df -hT /` and `vgs` evidence before marking done. A staged script is not proof of a completed disk expansion.

## Repeated quality — task 6
`.github/workflows/ops-repeated-quality.yml` invokes the real `quality.yml` three times through sequential `workflow_call` dependencies; each full round runs R1, R2, R3, R4, including the actual `--min=90` line-coverage gate and concurrency test in R2. A failed pass still allows the next round to run; the overall workflow remains FAILED.

## CI — task 9
Quality is reusable with `workflow_call`. Disabled `cancel-in-progress` avoids cancelling costly test runs when new commits arrive. Unused old `MARS_PG_PORT` per-runner variables removed. Global `XDEBUG_MODE=off` keeps noncoverage checks fast; R4 uses validated PCOV. Default GitHub token permission remains `contents: read`.

## Branch cleanup/documentation — tasks 10, 11
Remove diagnostic branches only after completing main Quality and checking they contain no unmerged work or active PRs. Keep GitHub run links and this runbook. Never force-remove a branch unexpectedly.

## Failure response
Inspect the exact failing job and runner label. Determine whether the failure is Tailnet reachability, service health, credentials, isolated test data, source regression, capacity, or PCOV. Do not conceal failures. Do not delete volumes, rotate passwords or touch customer databases. Preserve failed logs and roll back only code/config changes when needed.

## Excluded tasks 1–4
Do **not** change Tailscale Funnel, temporary administrator secret cleanup, PostgreSQL access roles, or automated database backups in this change set.

## Fully sequential quality execution

The single source of truth is `.github/workflows/quality.yml`:
`prepare-host -> foundation (R1) -> domain (R2) -> manual-audit (R3) -> quality (R4)`.
Each stage waits for the prior job to finish; no R1–R4 jobs run concurrently
within a single quality invocation. Later stages use `if: !cancelled()`
to execute despite an earlier test failure, ensuring a complete diagnosis;
GitHub still marks the invocation FAILED if any stage fails.

The three-round repeated suite is sequential as well:
`pass-1 -> pass-2 -> pass-3`. Each pass contains the same four-runner
serial chain and maintains the actual stock coverage gate `--min=90`.
All rounds run unless explicitly cancelled, even if a previous round fails,
so intermittent failures can be identified. A failure in any round makes
the overall repeated run FAILED.

Sequential execution increases wall time compared with running the four
runners in parallel. Infrastructure health monitoring is a separate
scheduled check and may run independently of quality verification.

## Completed maintenance — 2026-10-08

The authorized, guarded test-only maintenance workflow [#37847090664](https://github.com/ufukunal/MarsOtomasyon/actions/runs/37847090664) completed successfully. Runtime output showed:

- Root LV `/dev/ubuntu-vg/ubuntu-lv`: increased online from <49 GiB to <98 GiB with `lvextend --resizefs`, retaining ext4 root filesystem and test volumes.
- Root filesystem `df -hT /`: **97G total, 8.0G used, 84G available (9%)**, previously 48G total at 18% used.
- Volume group `ubuntu-vg`: **0 free extents** after expansion (the planned ~49 GiB was assigned to root).
- PostgreSQL `mars-test-postgres` restart and test-only persisted row: **PASS**.
- Valkey `mars-test-valkey` restart and persisted AOF test key: **PASS**.
- Both Docker volume existence and `unless-stopped` restart policies: **PASS**.
- Authenticated PostgreSQL/Valkey health, Docker, disk and memory monitoring: **PASS**.
- The one-time privileged workflow was deleted from the active maintenance branch immediately after successful execution.

A **whole-VM reboot/autostart verification has not been performed**. It remains a separate planned service interruption in [issue #1](https://github.com/ufukunal/MarsOtomasyon/issues/1). Do not interpret container-level restart success as whole-node reboot evidence.

New full R1–R4 quality verification should be performed after maintenance before considering this operational change closed.

## Access-independent health diagnosis — 2026-10-09

A scheduled health check [#37892715713](https://github.com/ufukunal/MarsOtomasyon/actions/runs/37892715713) failed with a **Tailscale SSH additional verification request** from the R3 runner, before authorized database credentials or host metrics could be collected. No passwords, access policies, IPs or user accounts were changed.

Read-only R1–R4 checks [#37902748550](https://github.com/ufukunal/MarsOtomasyon/actions/runs/37902748550) confirmed: on **all four runners**, Tailscale was running, `pg_isready` accepted TCP on PostgreSQL port 55432, and TCP connections reached Valkey port 6379; non-interactive SSH to the VM failed on all four. **TCP reachability is not proof of authenticated database or Docker health.**

The scheduled `ops-health.yml` workflow now has two separately visible jobs:
1. **Network transport**, using `scripts/ci/test-service-network.sh`, checks only unauthenticated PostgreSQL readiness and Valkey TCP reachability. No remote SSH is required; it never claims DB authentication or persistence health.
2. **Full authenticated and host health**, retaining the existing SSH-secured credentials retrieval, PostgreSQL and Valkey authenticated health, `fsync`/AOF, Docker state, root disk, and memory checks. This job **must stay red** while SSH requires additional verification. A healthy network job must not mask an unverified full-health job.

Both jobs were exercised on [#37903009401](https://github.com/ufukunal/MarsOtomasyon/actions/runs/37903009401): network PASS, authenticated/host health FAILED on SSH verification. Separately, offline unit, Bash, Pint, Larastan and frontend build checks passed in [#37903125609](https://github.com/ufukunal/MarsOtomasyon/actions/runs/37903125609).

Whole-node reboot and its autostart checks remain blocked until existing SSH access becomes available via the previously authorized mechanism. Do not bypass SSH verification, alter passwords or change addresses. R1–R4 feature and real PCOV coverage tests also depend on that existing SSH credential retrieval; do not claim a fresh full quality PASS until they truly run.

## SSH-independent CI test credentials — 2026-10-09

The four R1–R4 application test jobs retrieve their existing PostgreSQL/Valkey test-only credentials solely via **repository GitHub Actions Secrets** `MARS_PG_PASSWORD` and `MARS_VALKEY_PASSWORD`. Job-specific verification steps inject these secrets into `scripts/ci/remote-test-services.sh`, which validates 64-character hexadecimal formats, masks the secrets, performs authenticated PostgreSQL `SELECT current_database()` and Valkey `AUTH + PING` through the pre-existing private Tailnet service ports, and exports the DB/Redis environment to later steps through the GitHub job-scoped environment file. **No SSH call or SSH authentication check is permitted in this application CI helper.** No existing VM password, IP, SSH policy, Tailscale network, database role, or password value needs to change.

The GitHub Secrets must contain the **same existing test-only passwords** already configured on the VM. GitHub encrypts these entries and never permits their values to be read through the API. If either is missing or malformed, CI explicitly fails with `CI_SECRET_MISSING_OR_INVALID` rather than degrading to unauthenticated or disabled tests. Do not paste secrets into chat, source code, or run logs.

The scheduled monitoring workflow now reports three distinct checks: 1) private TCP transport; 2) authenticated DB/Valkey and Postgres fsync/Valkey AOF **without SSH**; 3) Docker/disk/RAM remote VM health via existing authorized SSH **only**. A host-health SSH failure must not mask success or failure of database-service tests, nor will it be ignored. A whole-node reboot still requires a separately authorized maintenance window.

### Credential provisioning status and non-disruptive next step

The 2026-10-09 **staging** secret contract check [#37904801910](https://github.com/ufukunal/MarsOtomasyon/actions/runs/37904801910) confirmed that both repository Actions secrets `MARS_PG_PASSWORD` and `MARS_VALKEY_PASSWORD` are **missing or not valid 64-hex values**. The SSH-free script and workflow structure passed static checks; no completed authenticated CI test is claimed at this stage. To unblock, an authorized operator must populate these two GitHub Actions **repository Secrets** using the existing test-only values from the test VM's `/home/ufuk/.config/mars-test-infra/secrets.env`. Adding encrypted CI copies **does not rotate or modify** the database passwords, user accounts, IP addresses, Tailscale, or SSH. The values must not be copied into repository files, public logs, or chat. Once configured, rerun the full 3-pass sequential workflow and authenticated health, then confirm real 90% PCOV coverage. If secrets are absent, tests must remain RED.
