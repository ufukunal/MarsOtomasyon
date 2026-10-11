#!/usr/bin/env bash
# Safe PROD host readiness probe: no installs, DB queries, migrations or deployment.
set -Eeuo pipefail
if [[ -z "${MARS_V4_SSH_KNOWN_HOSTS:-}" ]]; then
  echo "BLOCKED: missing pinned SSH host key secret (MARS_V4_SSH_KNOWN_HOSTS)" >&2
  exit 77
fi
if [[ -z "${MARS_V4_SSH_PRIVATE_KEY:-}" && -z "${MARS_V4_SSH_PASSWORD:-}" ]]; then
  echo "BLOCKED: no authorized SSH key/password secret for the PROD VM" >&2
  exit 77
fi
tmp="$(mktemp -d)"
chmod 700 "$tmp"
trap 'rm -rf "$tmp"' EXIT
printf '%s\n' "$MARS_V4_SSH_KNOWN_HOSTS" > "$tmp/known_hosts"
chmod 600 "$tmp/known_hosts"
ssh_opts=(-p 10000 -o StrictHostKeyChecking=yes
    -o UserKnownHostsFile="$tmp/known_hosts" -o ConnectTimeout=8
    -o ConnectionAttempts=1 -o LogLevel=ERROR)
if [[ -n "${MARS_V4_SSH_PRIVATE_KEY:-}" ]]; then
  printf '%s\n' "$MARS_V4_SSH_PRIVATE_KEY" > "$tmp/id_key"
  chmod 600 "$tmp/id_key"
  ssh_opts+=(-i "$tmp/id_key" -o IdentitiesOnly=yes -o BatchMode=yes)
else
  # Never interpolate a password into executable shell source.
  printf '%s' "$MARS_V4_SSH_PASSWORD" > "$tmp/ssh_password"
  chmod 600 "$tmp/ssh_password"
  printf '#!/bin/sh\ncat "$MARS_ASKPASS_PASSWORD_FILE"\n' > "$tmp/askpass"
  chmod 700 "$tmp/askpass"
  export MARS_ASKPASS_PASSWORD_FILE="$tmp/ssh_password"
  export SSH_ASKPASS="$tmp/askpass" SSH_ASKPASS_REQUIRE=force DISPLAY=:0
  ssh_opts+=(-o BatchMode=no -o PreferredAuthentications=password
    -o PubkeyAuthentication=no -o NumberOfPasswordPrompts=1)
fi
echo "Checking authenticated PROD host access only; no changes will be made."
timeout 30 ssh "${ssh_opts[@]}" ufuk@mars-prod-1.taila20365.ts.net 'bash -s' <<'REMOTE'
set -Eeuo pipefail
echo "REMOTE_USER=$(id -un)"
echo "REMOTE_HOSTNAME=$(hostname)"
if command -v docker >/dev/null 2>&1 && docker info >/dev/null 2>&1; then
  echo "REMOTE_DOCKER=AVAILABLE_TO_ACCOUNT"
else
  echo "REMOTE_DOCKER=NOT_ACCESSIBLE_TO_ACCOUNT"
fi
if [[ -r /etc/mars/uat.env ]]; then
  echo "UAT_CONFIG=READABLE"
elif [[ -e /etc/mars/uat.env ]]; then
  echo "UAT_CONFIG=EXISTS_BUT_NOT_READABLE"
else
  echo "UAT_CONFIG=NOT_PROVISIONED"
fi
REMOTE
