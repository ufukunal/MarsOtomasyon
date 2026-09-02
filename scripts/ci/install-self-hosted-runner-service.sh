#!/usr/bin/env bash
set -Eeuo pipefail

usage() {
    cat <<'USAGE'
Usage:
  install-self-hosted-runner-service.sh [RUNNER_DIR] [SERVICE_USER]

Defaults:
  RUNNER_DIR   $RUNNER_DIR or $HOME/actions-runner
  SERVICE_USER $RUNNER_SERVICE_USER, $SUDO_USER, or the current user

The runner must already be registered with GitHub (.runner must exist).
This script never downloads a runner and never handles registration tokens.
USAGE
}

fail() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

if [[ "${1:-}" == "-h" || "${1:-}" == "--help" ]]; then
    usage
    exit 0
fi

[[ "$(uname -s)" == "Linux" ]] || fail "This helper supports Linux/systemd runners only."
command -v systemctl >/dev/null 2>&1 || fail "systemctl was not found."
command -v sudo >/dev/null 2>&1 || fail "sudo was not found."

runner_dir="${1:-${RUNNER_DIR:-$HOME/actions-runner}}"
service_user="${2:-${RUNNER_SERVICE_USER:-${SUDO_USER:-$(id -un)}}}"

[[ -n "$runner_dir" ]] || fail "Runner directory is empty."
[[ -d "$runner_dir" ]] || fail "Runner directory does not exist: $runner_dir"
runner_dir="$(cd "$runner_dir" && pwd -P)"

[[ -n "$service_user" ]] || fail "Service user is empty."
id "$service_user" >/dev/null 2>&1 || fail "Service user does not exist: $service_user"
[[ "$service_user" != "root" ]] || fail "Refusing to run the GitHub Actions runner as root. Use a dedicated non-root account."

cd "$runner_dir"
[[ -f .runner ]] || fail "Runner is not registered: $runner_dir/.runner is missing. Run GitHub's config.sh registration first."
[[ -x ./svc.sh ]] || fail "svc.sh is missing or not executable in $runner_dir."
[[ -x ./runsvc.sh || -f ./bin/runsvc.sh ]] || fail "runsvc.sh payload is missing; runner installation may be incomplete."

printf 'Runner directory : %s\n' "$runner_dir"
printf 'Service user    : %s\n' "$service_user"

if [[ ! -f .service ]]; then
    printf 'Installing GitHub Actions runner systemd service...\n'
    sudo ./svc.sh install "$service_user"
else
    printf 'Existing runner service metadata found; install step is already complete.\n'
fi

[[ -s .service ]] || fail "Runner service metadata (.service) was not created."
service_name="$(tr -d '\r\n' < .service)"
[[ -n "$service_name" ]] || fail "Runner service name is empty."

# GitHub recommends preventing needrestart from restarting the runner in the
# middle of a workflow job on Debian-family systems.
if [[ -d /etc/needrestart/conf.d ]]; then
    printf '%s\n' '$nrconf{override_rc}{qr(^actions\.runner\..+\.service$)} = 0;' \
        | sudo tee /etc/needrestart/conf.d/actions_runner_services.conf >/dev/null
fi

sudo systemctl daemon-reload
sudo systemctl enable "$service_name" >/dev/null
sudo systemctl start "$service_name"

sudo systemctl is-enabled --quiet "$service_name" \
    || fail "Service is not enabled for boot: $service_name"
sudo systemctl is-active --quiet "$service_name" \
    || fail "Service is not active: $service_name"

printf '\nRunner service is installed, enabled, and active.\n'
printf 'Service name: %s\n' "$service_name"
printf 'Verify with : sudo systemctl --no-pager status %q\n' "$service_name"
printf 'Logs       : sudo journalctl -u %q -n 100 --no-pager\n' "$service_name"
