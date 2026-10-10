# MarsProject — Newly authored V4 test suite

This suite was written from scratch against the current main production source. No historic V1/V2/V3 test files were copied, restored or cherry-picked.

## Scope

- Unit behavior: money, VAT/discount documents, production costing, purchase/sales due dates, inventory available balance, import guard, bank statement parsing, templates, CSV formula injection, printers, data masking, upload validation, search normalization, report values, channel hashing, tenant cache keys, authorization guard, periods, and other core rules.
- Contracts: every production PHP symbol across all action modules, Livewire screens, models, jobs, commands, controllers, support classes, DTOs and enums is represented in source-manifest.json; all expected routes have authorization/throttling/signed URL controls; master/period connection definitions and migration inventory.
- Feature: unauthenticated access boundaries on module routes and unsigned asset links.
- Integration: gated, READ-ONLY PostgreSQL schema verification for module tables and migrations in two *separately prepared* local databases. No migrations or destructive operations are executed by the integration suite.

## Safety and execution

The code has been authored but **not run**. Never run unit, feature or integration tests without separate user execution approval.

The default phpunit.xml pins databases and Redis to 127.0.0.1 with dedicated names; this is NOT permission to connect to production or to create/drop databases. The integration suite is skipped unless environment variable MARS_INTEGRATION_TESTS_APPROVED equals I_APPROVE_LOCAL_TEST_ONLY. Explicit approval does not create any databases automatically.

After separate approval, in a disposable isolated environment:

\`\`\`bash
composer install --no-interaction
vendor/bin/pest --testsuite=Unit,Contracts,Feature
MARS_INTEGRATION_TESTS_APPROVED=I_APPROVE_LOCAL_TEST_ONLY vendor/bin/pest --testsuite=Integration
\`\`\`

Do not enable GitHub Actions auto-execution before approval. Existing quality.yml runs only static analysis and frontend build, not Pest.

## Actual coverage limitations

Inventory/autoload, route middleware and schema tests are **contracts**, not proof that every operation behaves correctly. Each of 181 business actions requires positive/negative transactional tests, authorization, idempotency, concurrency and rollback validations using isolated PostgreSQL and Valkey fixtures. Channel integrations further require signed webhook fixtures and external API adapters. Livewire UI flows require browser tests. Performance, backup/restore and disaster recovery need separate isolated hosts. No coverage percentage is claimed because tests have not been executed.

Maintain source-manifest.json whenever production classes are added or renamed; SourceInventoryTest fails closed when it is out of date.
