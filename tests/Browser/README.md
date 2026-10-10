# Mars V4 optional browser journeys

This is a separate test-only Playwright package, **not** an application dependency.
No old browser tests were restored.

Prerequisites: locally provisioned, disposable Laravel app + local dedicated
master and period databases. No production data/accounts. The server must bind
127.0.0.1, not a Tailscale or public interface. The test suite does not start,
migrate, seed or clean databases.

After *separate explicit execution approval*, prepare locally:

1. cd tests/Browser && npm install && npx playwright install chromium
2. Supply MARS_BROWSER_BASE_URL=http://127.0.0.1:8080
3. Supply MARS_TEST_EXECUTION_APPROVED=MARS_V4_EXECUTION_APPROVED
4. Supply MARS_BROWSER_TESTS_APPROVED=I_APPROVE_LOCAL_BROWSER
5. npm test

An optional **local test-only** user ending in @invalid.test may be supplied
via MARS_BROWSER_TEST_EMAIL and MARS_BROWSER_TEST_PASSWORD for authenticated
navigation. Never provide production passwords.

The suite checks real browser guest redirects, login form shape, recovery link,
and local authenticated dashboard/period navigation. It does **not** claim full
authenticated business CRUD journey or broad accessibility coverage.

An optional authenticated read-only smoke sweep covers ten business modules.
It is **skipped** unless the actor above also has an isolated provisioned and
fully authorized company/period. Supply the local integer IDs through
MARS_BROWSER_TEST_COMPANY_ID and MARS_BROWSER_TEST_PERIOD_ID and set
MARS_BROWSER_TEST_PERIOD_READY=I_APPROVE_LOCAL_PERIOD_FIXTURES. The test
selects that company and period through the real Livewire selector and checks
that each module renders without HTTP 4xx/5xx errors. It performs no mutations.

## Additional explicit permission for browser writes

Product creation, contact creation and negative form validation tests are in `specs/isolated-product-contact-crud.spec.ts`. They are skipped unless `MARS_BROWSER_TEST_MUTATIONS_APPROVED=I_APPROVE_LOCAL_UI_WRITES`, all base browser approval gates and local company/period fixture IDs are present. For product creation also provide `MARS_BROWSER_TEST_PRODUCT_UNIT_ID` for a disposable local unit. All successful CRUD cases create records only in a throwaway local test database; they must not be pointed at production or a shared environment. These test files have not been executed.
