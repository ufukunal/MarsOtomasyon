# Dedicated MarsOtomasyon test services (mars-prod-1)

Target host: `ufukmarsprod` / `100.127.235.30`, Ubuntu 24.04.4.
This host serves PostgreSQL and Valkey only. The four self-hosted GitHub
runners remain on `mars-ci`. No customer/production database is used.

## Provisioning

The privileged installer is `ops/test-infra/install.sh`; run it only on
the verified test VM with authorized administrator privileges. It installs
Ubuntu Docker Engine, Compose V2 and PostgreSQL client, and starts the
`compose.yaml` stack. It does not install GitHub runners.

A single 32-byte random password per service is stored on the VM at
`/home/ufuk/.config/mars-test-infra/secrets.env` (mode 0600).
Credentials are never committed to GitHub. The self-hosted runners obtain
test-only credentials through authenticated Tailscale SSH, and GitHub masks
them before loading the per-job environment.

Docker publishes PostgreSQL `55432` and Valkey `6379` *only* on
`100.127.235.30`. Both use Docker named volumes and `restart: unless-stopped`.
Database names are `MarsProject_Master_Test_R1`, `R2`, `R3`, and
`MarsProject_Coverage_Test` (R4). They are dedicated test databases;
they are not disposable PostgreSQL server processes.

## CI migration gates

The staged `.github/workflows/quality.yml` uses
`scripts/ci/remote-test-services.sh` in each job instead of
`scripts/ci/postgres.sh start/stop`. Do **not merge** that workflow
until the installation has completed, all four DB connections and Valkey
AUTH/PING have passed, and R1/R2/R3/R4 have been tested.

Do not use `docker compose down -v` on this VM: it destroys persistent
test data. Versioned backups and monitoring should be configured before
the environment stores any irreplaceable data. Docker images can be
updated in a controlled maintenance window, not on every workflow run.
