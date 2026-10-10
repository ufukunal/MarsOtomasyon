#!/usr/bin/env bash
set -Eeuo pipefail
if [[ "${MARS_TEST_EXECUTION_APPROVED:-}" != "MARS_V4_EXECUTION_APPROVED" ]]; then
  echo "BLOCKED: explicit approval required" >&2
  exit 77
fi
mode="${1:-}"
case "$mode" in
  unit) exec vendor/bin/pest --testsuite=Unit ;;
  contracts) exec vendor/bin/pest --testsuite=Contracts ;;
  feature) exec vendor/bin/pest --testsuite=Feature ;;
  integration)
    if [[ "${MARS_INTEGRATION_TESTS_APPROVED:-}" != "I_APPROVE_LOCAL_TEST_ONLY" ]]; then
      echo "BLOCKED: isolated local integration approval required" >&2
      exit 77
    fi
    exec vendor/bin/pest --testsuite=Integration ;;
  performance)
    if [[ "${MARS_PERFORMANCE_TESTS_APPROVED:-}" != "I_APPROVE_LOCAL_BENCHMARK" ]]; then
      echo "BLOCKED: isolated performance approval required" >&2
      exit 77
    fi
    exec vendor/bin/pest --testsuite=Performance ;;
  *)
    echo "usage: $0 {unit|contracts|feature|integration|performance}" >&2
    exit 2 ;;
esac
