# Fast self-hosted PostgreSQL CI

This setup is designed for a dedicated disposable CI host around 8 vCPU / 12 GiB RAM.

## Architecture

- 3 GitHub Actions runner processes on one physical host
- all runners use the labels `self-hosted`, `linux`, `x64`, `mars-ci`
- native PostgreSQL 18 over the local Unix socket
- native Valkey/Redis on localhost
- one isolated PostgreSQL database per workflow run/shard
- Redis DB indexes split 4-per-shard
- persistent Composer download cache
- non-browser Pest suite split into 3 deterministic size-balanced shards
- current hosted Foundation CI remains unchanged until this path is measured and accepted

A single self-hosted runner process executes only one GitHub Actions job at a time. Three runner installations are therefore required to use the server's CPUs concurrently.

## 1. Bootstrap a disposable Ubuntu CI host

Clone the repository, then run:

```bash
sudo I_UNDERSTAND_THIS_IS_A_DISPOSABLE_CI_HOST=1 \
  bash scripts/ci/bootstrap-self-hosted-ubuntu.sh
```

The script installs PHP 8.5, PostgreSQL 18, Valkey/Redis, Composer, creates the `marsci` OS/PostgreSQL role, and applies CI-only PostgreSQL tuning.

**Do not run this script on production.** It intentionally disables PostgreSQL durability (`fsync=off`, `synchronous_commit=off`, `full_page_writes=off`) because every CI database is disposable.

## 2. Register three GitHub runner instances

Open:

`Repository Settings -> Actions -> Runners -> New self-hosted runner`

Use the GitHub-provided download/configuration commands. Repeat them in these directories:

```text
/opt/actions-runner-1
/opt/actions-runner-2
/opt/actions-runner-3
```

Configure unique runner names, for example:

```text
mars-ci-1
mars-ci-2
mars-ci-3
```

Add the same custom label to all three:

```text
mars-ci
```

Configure/install the runner services as the `marsci` OS user. Do not store or commit the short-lived GitHub runner registration token.

After registration, GitHub should show three idle runners with the `mars-ci` label.

## 3. Verify the host

As `marsci`:

```bash
cd /path/to/MarsOtomasyon
bash scripts/ci/verify-self-hosted-runner.sh
```

Expected final line:

```text
Self-hosted PostgreSQL runner prerequisites: OK
```

## 4. Run the fast path

In GitHub Actions, manually run:

```text
PostgreSQL Fast Self-Hosted
```

It starts three jobs concurrently:

```text
postgres-shard-1
postgres-shard-2
postgres-shard-3
```

Each shard gets its own PostgreSQL database. The aggregate check is:

```text
postgres-tests-self-hosted
```

## 5. Promotion criteria

Do not replace the required hosted `postgres-tests` check until the self-hosted path has completed cleanly multiple times. Once verified, the Foundation workflow can use the three self-hosted shards and retain an aggregate check named exactly `postgres-tests`, preserving the repository's existing merge gate.

## Resource target

For an 8 vCPU / 12 GiB machine, three runner processes are intentional. Four concurrent DB-heavy shards generally create more CPU scheduling, memory, and PostgreSQL contention than the extra parallelism saves.
