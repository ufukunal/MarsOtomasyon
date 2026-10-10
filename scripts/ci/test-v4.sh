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
    [[ "$MARS_V4_REMOTE_TUNNEL_READY" == "YES" ]] || { echo "BLOCKED: remote TEST tunnel was not verified" >&2; exit 77; }
    exec vendor/bin/pest --testsuite=Integration ;;
  valkey)
    [[ "${MARS_INTEGRATION_TESTS_APPROVED:-}" == "I_APPROVE_LOCAL_TEST_ONLY" && "${MARS_VALKEY_TESTS_APPROVED:-}" == "I_APPROVE_LOCAL_VALKEY_ONLY" ]] || { echo "BLOCKED: Valkey test approval missing" >&2; exit 77; }
    [[ "$MARS_V4_REMOTE_TUNNEL_READY" == "YES" ]] || { echo "BLOCKED: remote TEST tunnel was not verified" >&2; exit 77; }
    exec vendor/bin/pest tests/Integration/ValkeyNamespaceAndAtomicityTest.php ;;
  performance)
    if [[ "${MARS_PERFORMANCE_TESTS_APPROVED:-}" != "I_APPROVE_LOCAL_BENCHMARK" ]]; then
      echo "BLOCKED: isolated performance approval required" >&2
      exit 77
    fi
    exec vendor/bin/pest --testsuite=Performance ;;
  browser)
    if [[ "${MARS_BROWSER_TESTS_APPROVED:-}" != "I_APPROVE_LOCAL_BROWSER" ]]; then
      echo "BLOCKED: local browser test approval missing" >&2
      exit 77
    fi
    exec npm --prefix tests/Browser run test ;;
  *)
    echo "usage: $0 {unit|contracts|feature|integration|valkey|performance|browser}" >&2
    exit 2 ;;
esac
