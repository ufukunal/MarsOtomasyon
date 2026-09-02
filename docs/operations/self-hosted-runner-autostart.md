# Self-hosted GitHub Actions runner: automatic startup

MarsOtomasyon's self-hosted workflows target the built-in runner labels:

```yaml
runs-on: [self-hosted, linux, x64]
```

No custom label is required for the existing `self-hosted-benchmark.yml` workflow.

## Goal

Run an already-registered Linux GitHub Actions runner as a `systemd` service so it starts automatically after a reboot.

The registration token and GitHub credentials must never be committed to the repository. This runbook assumes the runner was already registered successfully and its installation directory contains `.runner`, `svc.sh`, and the runner binaries.

## Recommended layout

Use a stable local path such as `/opt/actions-runner` and a dedicated non-root service account. The runner account needs only the operating-system permissions required by the jobs it executes.

## Install / enable the service

From a checkout of this repository on the runner machine:

```bash
bash scripts/ci/install-self-hosted-runner-service.sh /opt/actions-runner "$(id -un)"
```

If the runner lives elsewhere, pass its actual installation directory as the first argument. The script is idempotent for an already-installed service: it reads the service name from the runner's `.service` file, enables it for boot, starts it, and verifies both `enabled` and `active` states.

The script intentionally does **not**:

- download or upgrade the runner,
- run `config.sh`,
- request or persist a registration token,
- create GitHub secrets,
- run the service as `root`.

## Manual equivalent

GitHub's runner package includes `svc.sh` after registration. The core manual commands are:

```bash
cd /opt/actions-runner
sudo ./svc.sh install "$(id -un)"
sudo ./svc.sh start
sudo ./svc.sh status
```

`svc.sh install` creates and enables the generated `actions.runner.*.service` unit. The repository helper additionally verifies boot enablement and active state.

## Verification

Read the generated service name and check it directly:

```bash
cd /opt/actions-runner
SERVICE_NAME="$(cat .service)"
sudo systemctl is-enabled "$SERVICE_NAME"
sudo systemctl is-active "$SERVICE_NAME"
sudo systemctl --no-pager status "$SERVICE_NAME"
```

Expected first two outputs:

```text
enabled
active
```

Then trigger `.github/workflows/self-hosted-benchmark.yml` with `workflow_dispatch`. Its job selector is `[self-hosted, linux, x64]`, so a registered Linux x64 runner should be eligible without a custom `mars-ci` label.

## Reboot drill

A service is not considered operationally verified until a reboot test has been performed:

```bash
sudo reboot
```

After the machine returns:

```bash
cd /opt/actions-runner
SERVICE_NAME="$(cat .service)"
sudo systemctl is-enabled "$SERVICE_NAME"
sudo systemctl is-active "$SERVICE_NAME"
sudo journalctl -u "$SERVICE_NAME" -n 100 --no-pager
```

Run the benchmark workflow again after the reboot. Keep this as validation evidence rather than assuming service installation alone proves reboot recovery.

## Debian / Ubuntu `needrestart`

GitHub recommends excluding `actions.runner.*.service` from automatic `needrestart` restarts so package maintenance does not restart a runner in the middle of a job. The helper writes this configuration when `/etc/needrestart/conf.d` exists:

```text
/etc/needrestart/conf.d/actions_runner_services.conf
```

## Failure checks

If the runner is registered in GitHub but jobs remain queued:

1. Confirm `systemctl is-active "$(cat .service)"` returns `active`.
2. Inspect `journalctl -u "$(cat .service)" -n 100 --no-pager`.
3. Confirm the host architecture is x64 and the runner advertises `self-hosted`, `linux`, and `x64`.
4. Confirm no interactive `./run.sh` process is being used as the long-running production path.
5. Confirm the service account can access Docker/PostgreSQL/other tools required by the workflow; service startup and job prerequisites are separate concerns.

Do not mark the self-hosted validation debt closed until at least one post-reboot workflow run completes successfully.
