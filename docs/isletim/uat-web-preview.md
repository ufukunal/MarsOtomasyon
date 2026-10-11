# MarsProject UAT web preview (operator run)

This runbook is **UAT only**, not production go-live.
All preflight operations are read-only. The application is never pointed at
business PROD databases or the default Valkey ACL. A failed preflight
does not start any persistent application service.

## Prerequisites on the selected Docker host

1. Docker Engine and Docker Compose accessible to the authorized operator.
2. Git checkout of the specific release commit (no local modifications).
3. Protected /etc/mars/uat.env containing an isolated UAT APP_KEY,
   mars_test PostgreSQL and Valkey credentials. The test Master and
   Period databases must already exist, have correct migrations, and
   the test role must have no CREATEDB, SUPERUSER or PROD database CONNECT.
4. PostgreSQL/Valkey host must be reachable **from containers**; container
   DNS and routing may differ from the VM host.
5. User must have authenticated SSH access to the host for a local tunnel.

## Start

Run within the checkout, passing the reviewed full 40-character commit SHA:

```bash
MARS_UAT_DEPLOY_APPROVED=I_APPROVE_ISOLATED_UAT_WEB_ONLY \
  bash ops/uat-preview-start.sh <reviewed-full-git-sha>
```

The script checks the protected configuration, builds only the application
image, runs the repository's PostgreSQL/Valkey **read-only** isolation
preflight, then starts only `web`. It tests `/up` and `/giris`.
If either page fails it stops the UAT web service. No queue/scheduler,
migration, provisioning, system ACL change or production traffic is started.

## Open in a desktop browser

Use an SSH local port forward to the selected host:
local TCP **18080** to host **127.0.0.1:18080**.
With a working tunnel the operator visits
`http://127.0.0.1:18080/giris` on their own desktop.

Public HTTPS exposure is **not** configured by this script.
The URL is available only when the host web health checks have passed.

## Known limitations

The normal first-company wizard calls a privileged CREATE DATABASE flow;
a restricted mars_test user cannot finish it without a separate
controlled provisioning mechanism. A login page is not evidence that
company creation, financial flows, PDF generation, backup/restore or
all user actions work. Complete those before real-user acceptance.
