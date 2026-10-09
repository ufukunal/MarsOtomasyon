#!/usr/bin/env bash
# Read-only *transport* probe, not an authentication or node-health check.
# Uses the existing, unchanged dedicated test VM's private service endpoints.
set -Eeuo pipefail
host=100.127.235.30
if timeout 8 pg_isready -h "$host" -p 55432 -t 5; then
  echo "POSTGRESQL_TCP_READY_NO_AUTH"
else
  echo "POSTGRESQL_NETWORK_UNREACHABLE" >&2
  exit 11
fi
if timeout 8 bash -c 'exec 3<>/dev/tcp/100.127.235.30/6379'; then
  echo "VALKEY_TCP_OPEN_NO_AUTH"
else
  echo "VALKEY_NETWORK_UNREACHABLE" >&2
  exit 12
fi
echo "SERVICE_NETWORK_REACHABLE_AUTH_AND_REMOTE_METRICS_NOT_VERIFIED"
