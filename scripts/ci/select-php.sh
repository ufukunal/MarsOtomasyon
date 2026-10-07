#!/usr/bin/env bash
set -Eeuo pipefail

mkdir -p "$RUNNER_TEMP/mars-bin"
PHP_BIN=""

for candidate in /usr/bin/php8.5 /usr/bin/php8.4 /usr/local/bin/php /usr/bin/php; do
    [[ -x "$candidate" ]] || continue

    if ! "$candidate" -r 'exit(version_compare(PHP_VERSION, "8.4.0", ">=") ? 0 : 1);' >/dev/null 2>&1; then
        continue
    fi

    if ! modules="$("$candidate" -m 2>/dev/null)"; then
        echo "Skipping broken PHP candidate: $candidate" >&2
        continue
    fi

    grep -qi '^bcmath$' <<<"$modules" || continue
    grep -qi '^pdo_pgsql$' <<<"$modules" || continue

    PHP_BIN="$candidate"
    break
done

if [[ -z "$PHP_BIN" ]]; then
    echo "No working PHP >= 8.4 binary with bcmath and pdo_pgsql found." >&2
    exit 1
fi

ln -sf "$PHP_BIN" "$RUNNER_TEMP/mars-bin/php"
echo "$RUNNER_TEMP/mars-bin" >> "$GITHUB_PATH"

"$PHP_BIN" -v
PATH="$RUNNER_TEMP/mars-bin:$PATH" composer --version
