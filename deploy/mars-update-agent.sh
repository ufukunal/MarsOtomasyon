#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

BASE_DIR="${MARS_UPDATE_BASE_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
ENV_FILE="${MARS_UPDATE_ENV_FILE:-${BASE_DIR}/.env.production}"
RELEASES_DIR="${MARS_UPDATE_RELEASES_DIR:-${BASE_DIR}/.mars-releases}"
STATE_DIR="${MARS_UPDATE_STATE_DIR:-${BASE_DIR}/.mars-update-state}"
HEALTH_URL="${MARS_UPDATE_HEALTH_URL:-http://127.0.0.1:8080/login}"
COMPOSE_FILES_RAW="${MARS_UPDATE_COMPOSE_FILES:-docker-compose.production.yml}"
LOCK_FILE="${MARS_UPDATE_LOCK_FILE:-/tmp/mars-update-agent.lock}"

[[ -f "$ENV_FILE" ]] || { echo "Update env file not found: $ENV_FILE" >&2; exit 2; }
[[ "$RELEASES_DIR" = /* && "$RELEASES_DIR" != "/" ]] || { echo "Release directory must be a safe absolute path." >&2; exit 2; }
[[ "$STATE_DIR" = /* && "$STATE_DIR" != "/" ]] || { echo "State directory must be a safe absolute path." >&2; exit 2; }
command -v docker >/dev/null || { echo "docker is required." >&2; exit 2; }
command -v python3 >/dev/null || { echo "python3 is required." >&2; exit 2; }
command -v sha256sum >/dev/null || { echo "sha256sum is required." >&2; exit 2; }
command -v curl >/dev/null || { echo "curl is required." >&2; exit 2; }
command -v flock >/dev/null || { echo "flock is required." >&2; exit 2; }

mkdir -p "$RELEASES_DIR" "$STATE_DIR"
exec 9>"$LOCK_FILE"
if ! flock -n 9; then
    echo "Another Mars update agent is already running." >&2
    exit 3
fi

IFS=':' read -r -a COMPOSE_FILES <<< "$COMPOSE_FILES_RAW"
DC=(docker compose --project-directory "$BASE_DIR" --env-file "$ENV_FILE")
for relative in "${COMPOSE_FILES[@]}"; do
    [[ "$relative" != /* && "$relative" != *".."* ]] || { echo "Unsafe compose path: $relative" >&2; exit 2; }
    [[ -f "$BASE_DIR/$relative" ]] || { echo "Compose file not found: $relative" >&2; exit 2; }
    DC+=(-f "$BASE_DIR/$relative")
done

dc() { "${DC[@]}" "$@"; }
artisan() { dc exec -T app php artisan "$@"; }
json_field() {
    local key="$1"
    python3 -c 'import json,sys; data=json.load(sys.stdin); value=data[sys.argv[1]]; print(value)' "$key"
}
health_check() {
    local attempt
    for attempt in $(seq 1 30); do
        if curl --fail --silent --show-error --max-time 5 "$HEALTH_URL" >/dev/null; then
            return 0
        fi
        sleep 2
    done
    return 1
}

snapshot_images() {
    local run_id="$1" run_state="$STATE_DIR/run-$run_id" service cid image_id image_ref rollback_ref
    mkdir -p "$run_state"
    : > "$run_state/images.tsv"
    for service in app worker scheduler web; do
        cid="$(dc ps -q "$service")"
        [[ -n "$cid" ]] || { echo "Service container missing: $service" >&2; return 1; }
        image_id="$(docker inspect --format '{{.Image}}' "$cid")"
        image_ref="$(docker inspect --format '{{.Config.Image}}' "$cid")"
        rollback_ref="marsotomasyon-rollback-${service}:run-${run_id}"
        docker tag "$image_id" "$rollback_ref"
        printf '%s\t%s\t%s\n' "$service" "$image_ref" "$rollback_ref" >> "$run_state/images.tsv"
    done
}

restore_images() {
    local run_id="$1" run_state="$STATE_DIR/run-$run_id" service image_ref rollback_ref
    [[ -s "$run_state/images.tsv" ]] || { echo "Rollback image ledger missing for run $run_id." >&2; return 1; }
    while IFS=$'\t' read -r service image_ref rollback_ref; do
        [[ "$service" =~ ^(app|worker|scheduler|web)$ ]] || return 1
        docker image inspect "$rollback_ref" >/dev/null
        docker tag "$rollback_ref" "$image_ref"
    done < "$run_state/images.tsv"
}

candidate_dc_init() {
    local release_dir="$1" relative
    CANDIDATE_DC=(docker compose --project-directory "$release_dir" --env-file "$ENV_FILE")
    for relative in "${COMPOSE_FILES[@]}"; do
        [[ -f "$release_dir/$relative" ]] || { echo "Candidate compose file missing: $relative" >&2; return 1; }
        CANDIDATE_DC+=(-f "$release_dir/$relative")
    done
}

rollback_run() {
    local run_id="$1" backup_id="$2"
    echo "Rollback starting for run #$run_id" >&2
    artisan mars:update:agent-rollback-start "$run_id" >/dev/null || true
    artisan mars:recovery-mode on >/dev/null || true
    restore_images "$run_id"
    dc up -d --no-build --force-recreate app web
    dc stop worker scheduler >/dev/null || true
    artisan mars:restore "$backup_id" --no-safety
    dc up -d --no-build --force-recreate app worker scheduler web
    health_check || { echo "Rollback health check failed." >&2; return 1; }
    artisan mars:update:agent-rollback-reconcile "$run_id" "$backup_id" >/dev/null
    echo "Run #$run_id rolled back successfully."
}

fail_without_live_mutation() {
    local run_id="$1" code="$2" message="$3"
    artisan mars:recovery-mode off >/dev/null 2>&1 || true
    artisan mars:update:agent-fail "$run_id" "$code" "$message" >/dev/null 2>&1 || true
    echo "$message" >&2
    exit 1
}

apply_run() {
    local run_id="$1" artifact_json package_path release_path package_sha host_release host_package backup_id=""
    local run_state="$STATE_DIR/run-$run_id"
    mkdir -p "$run_state"

    artifact_json="$(artisan mars:update:agent-artifact "$run_id")"
    package_path="$(json_field package_path <<< "$artifact_json")"
    release_path="$(json_field release_path <<< "$artifact_json")"
    package_sha="$(json_field package_sha256 <<< "$artifact_json")"

    [[ "$package_path" == */runs/"$run_id"/package.zip ]] || fail_without_live_mutation "$run_id" artifact_path_invalid "Staged package path failed host validation."
    [[ "$release_path" == */runs/"$run_id"/release ]] || fail_without_live_mutation "$run_id" artifact_path_invalid "Staged release path failed host validation."
    [[ "$package_sha" =~ ^[a-f0-9]{64}$ ]] || fail_without_live_mutation "$run_id" artifact_hash_invalid "Staged package hash failed host validation."

    host_release="$RELEASES_DIR/run-$run_id"
    host_package="$run_state/package.zip"
    rm -rf -- "$host_release"
    mkdir -p "$host_release"
    rm -f -- "$host_package"

    dc cp "app:${package_path}" "$host_package"
    if [[ "$(sha256sum "$host_package" | awk '{print $1}')" != "$package_sha" ]]; then
        fail_without_live_mutation "$run_id" host_hash_mismatch "Host-side package SHA-256 verification failed."
    fi
    dc cp "app:${release_path}/." "$host_release/"

    if find "$host_release" -type l -print -quit | grep -q .; then
        fail_without_live_mutation "$run_id" host_symlink_rejected "Candidate release unexpectedly contains a symbolic link."
    fi
    for required in artisan composer.json Dockerfile.production docker-compose.production.yml; do
        [[ -f "$host_release/$required" ]] || fail_without_live_mutation "$run_id" release_layout_invalid "Candidate release is missing $required."
    done

    ln -sfn "$ENV_FILE" "$host_release/.env.production"
    snapshot_images "$run_id" || fail_without_live_mutation "$run_id" image_snapshot_failed "Existing runtime images could not be snapshotted."
    candidate_dc_init "$host_release" || fail_without_live_mutation "$run_id" candidate_compose_invalid "Candidate compose configuration is incomplete."

    if ! "${CANDIDATE_DC[@]}" build app worker scheduler web; then
        fail_without_live_mutation "$run_id" candidate_build_failed "Candidate container build failed before live mutation."
    fi

    if ! backup_id="$(artisan mars:update:agent-backup "$run_id")"; then
        fail_without_live_mutation "$run_id" backup_failed "Mandatory pre-update backup failed."
    fi
    printf '%s\n' "$backup_id" > "$run_state/backup-id"

    if ! artisan mars:update:agent-maintenance "$run_id" >/dev/null; then
        fail_without_live_mutation "$run_id" preflight_failed "Update preflight failed before database mutation."
    fi

    artisan mars:update:agent-state "$run_id" migrating >/dev/null
    if ! "${CANDIDATE_DC[@]}" run --rm --no-deps app php artisan migrate --force; then
        if ! rollback_run "$run_id" "$backup_id"; then
            echo "Rollback failed after migration failure; recovery mode must remain active." >&2
            exit 2
        fi
        exit 1
    fi

    artisan mars:update:agent-state "$run_id" activating >/dev/null
    if ! "${CANDIDATE_DC[@]}" up -d --no-deps --force-recreate app worker scheduler web; then
        rollback_run "$run_id" "$backup_id" || exit 2
        exit 1
    fi

    artisan mars:update:agent-state "$run_id" health_checking >/dev/null
    if ! health_check; then
        rollback_run "$run_id" "$backup_id" || exit 2
        exit 1
    fi

    if ! artisan mars:update:agent-complete "$run_id" >/dev/null; then
        rollback_run "$run_id" "$backup_id" || exit 2
        exit 1
    fi

    printf '%s\n' "$host_release" > "$BASE_DIR/.mars-active-release"
    echo "Run #$run_id completed successfully."
}

manual_rollback() {
    local run_id="$1" backup_id
    backup_id="$(artisan mars:update:agent-backup-id "$run_id")"
    [[ -n "$backup_id" ]] || { echo "Rollback backup id missing." >&2; exit 2; }
    rollback_run "$run_id" "$backup_id"
}

claim_json="$(artisan mars:update:agent-claim)"
action="$(json_field action <<< "$claim_json")"
if [[ "$action" == "none" ]]; then
    echo "No queued Mars update work."
    exit 0
fi
run_id="$(json_field run_id <<< "$claim_json")"
[[ "$run_id" =~ ^[1-9][0-9]*$ ]] || { echo "Invalid claimed run id." >&2; exit 2; }

case "$action" in
    apply) apply_run "$run_id" ;;
    rollback) manual_rollback "$run_id" ;;
    *) echo "Unknown agent action: $action" >&2; exit 2 ;;
esac
