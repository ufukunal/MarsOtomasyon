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
`.github/workflows/ops-repeated-quality.yml` invokes the real `quality.yml` three times through sequential `workflow_call` dependencies; each full round runs R1, R2, R3, R4, including the actual `--min=90` line-coverage gate and concurrency test in R2. A failed run blocks next pass.

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
