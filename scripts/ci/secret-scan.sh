#!/usr/bin/env bash
set -euo pipefail

failed=0

tracked_env_files="$(git ls-files '.env' '.env.*' | grep -v -E '^\.env\.example$|^\.env\.production\.example$' || true)"
if [[ -n "${tracked_env_files}" ]]; then
    echo 'Tracked environment files are not allowed:'
    printf '%s\n' "${tracked_env_files}" | sed 's/^/ - /'
    failed=1
fi

tracked_key_files="$(git ls-files | grep -E '(^|/)(id_rsa|id_dsa|id_ecdsa|id_ed25519|[^/]+\.(pem|p12|pfx|key))$' || true)"
if [[ -n "${tracked_key_files}" ]]; then
    echo 'Potential private key material is tracked:'
    printf '%s\n' "${tracked_key_files}" | sed 's/^/ - /'
    failed=1
fi

scan_pattern() {
    local label="$1"
    local pattern="$2"
    local matches

    matches="$(git grep -I -l -E "${pattern}" -- . \
        ':(exclude)scripts/ci/secret-scan.sh' \
        ':(exclude)composer.lock' \
        ':(exclude)package-lock.json' || true)"

    if [[ -n "${matches}" ]]; then
        echo "Potential ${label} found in tracked files:"
        printf '%s\n' "${matches}" | sed 's/^/ - /'
        failed=1
    fi
}

scan_pattern 'private key block' '-----BEGIN ([A-Z0-9 ]+ )?PRIVATE KEY-----'
scan_pattern 'AWS access key' 'AKIA[0-9A-Z]{16}'
scan_pattern 'GitHub token' 'gh[pousr]_[A-Za-z0-9]{36,255}'
scan_pattern 'GitHub fine-grained token' 'github_pat_[A-Za-z0-9_]{20,255}'
scan_pattern 'Slack token' 'xox[baprs]-[A-Za-z0-9-]{10,}'
scan_pattern 'Stripe live secret' 'sk_live_[A-Za-z0-9]{16,}'
scan_pattern 'SendGrid API key' 'SG\.[A-Za-z0-9_-]{16,}\.[A-Za-z0-9_-]{16,}'
scan_pattern 'Google API key' 'AIza[0-9A-Za-z_-]{35}'

if [[ "${failed}" -ne 0 ]]; then
    exit 1
fi

echo 'Basic tracked-secret scan passed.'
