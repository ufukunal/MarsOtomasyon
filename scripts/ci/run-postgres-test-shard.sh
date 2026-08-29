#!/usr/bin/env bash
set -euo pipefail

SHARD_INDEX="${SHARD_INDEX:?SHARD_INDEX is required}"
SHARD_TOTAL="${SHARD_TOTAL:-3}"

if ! [[ "$SHARD_INDEX" =~ ^[0-9]+$ ]] || ! [[ "$SHARD_TOTAL" =~ ^[0-9]+$ ]]; then
    echo "SHARD_INDEX and SHARD_TOTAL must be integers" >&2
    exit 2
fi

if (( SHARD_INDEX < 1 || SHARD_INDEX > SHARD_TOTAL )); then
    echo "SHARD_INDEX must be between 1 and SHARD_TOTAL" >&2
    exit 2
fi

mapfile -t TEST_FILES < <(
    find tests/Unit tests/Feature tests/Integration tests/Architecture \
        -type f -name '*Test.php' -print \
        | LC_ALL=C sort
)

if (( ${#TEST_FILES[@]} == 0 )); then
    echo "No non-browser test files found" >&2
    exit 3
fi

# Greedy size-balanced assignment. Each runner computes the same plan, so
# no coordination service is required. File size is a stable proxy for test
# runtime and balances large integration files better than modulo sharding.
mapfile -t SELECTED_FILES < <(
    python3 - "$SHARD_INDEX" "$SHARD_TOTAL" "${TEST_FILES[@]}" <<'PY'
import os
import sys

shard_index = int(sys.argv[1]) - 1
shard_total = int(sys.argv[2])
files = sys.argv[3:]

weighted = sorted(
    ((os.path.getsize(path), path) for path in files),
    key=lambda item: (-item[0], item[1]),
)

loads = [0] * shard_total
assignments = [[] for _ in range(shard_total)]

for size, path in weighted:
    target = min(range(shard_total), key=lambda idx: (loads[idx], idx))
    assignments[target].append(path)
    loads[target] += size

for path in sorted(assignments[shard_index]):
    print(path)
PY
)

if (( ${#SELECTED_FILES[@]} == 0 )); then
    echo "Shard ${SHARD_INDEX}/${SHARD_TOTAL} received no tests" >&2
    exit 4
fi

echo "Shard ${SHARD_INDEX}/${SHARD_TOTAL}: ${#SELECTED_FILES[@]} files"
printf '  %s\n' "${SELECTED_FILES[@]}"

exec vendor/bin/pest "${SELECTED_FILES[@]}" --colors=always
