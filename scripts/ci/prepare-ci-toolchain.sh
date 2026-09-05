#!/usr/bin/env bash
set -euo pipefail

: "${RUNNER_TEMP:?RUNNER_TEMP is required}"
: "${GITHUB_PATH:?GITHUB_PATH is required}"
: "${GITHUB_ENV:?GITHUB_ENV is required}"

COMPOSER_VERSION="${COMPOSER_VERSION:-2.10.3}"

php -r 'exit(version_compare(PHP_VERSION, "8.5.0", ">=") ? 0 : 1);' || {
    echo "::error::PHP 8.5+ is required on the self-hosted runner."
    exit 1
}

for extension in intl pdo_pgsql redis sockets zip gd; do
    php -r "exit(extension_loaded('${extension}') ? 0 : 1);" || {
        echo "::error::Required PHP extension '${extension}' is not loaded."
        exit 1
    }
done

composer_dir="$(mktemp -d "${RUNNER_TEMP%/}/mars-composer.XXXXXX")"
composer_home="${RUNNER_TEMP%/}/mars-composer-home-${GITHUB_RUN_ID:-local}-${GITHUB_JOB:-job}-${GITHUB_RUN_ATTEMPT:-1}"
installer="${composer_dir}/composer-setup.php"

mkdir -p "$composer_home"

expected_signature="$(curl --fail --silent --show-error --location --retry 3 https://composer.github.io/installer.sig)"
curl --fail --silent --show-error --location --retry 3 https://getcomposer.org/installer --output "$installer"
actual_signature="$(INSTALLER="$installer" php -r 'echo hash_file("sha384", getenv("INSTALLER"));')"

if [[ "$expected_signature" != "$actual_signature" ]]; then
    echo "::error::Composer installer signature verification failed."
    rm -f "$installer"
    exit 1
fi

php "$installer" \
    --version="$COMPOSER_VERSION" \
    --install-dir="$composer_dir" \
    --filename=composer \
    --quiet
rm -f "$installer"

{
    echo "$composer_dir"
} >> "$GITHUB_PATH"

{
    echo "COMPOSER_HOME=$composer_home"
} >> "$GITHUB_ENV"

"$composer_dir/composer" --version
