# FW-IMP-004 — Audit / Idempotency / Outbox Foundations

Status: COMPLETED
Date: 2026-09-22

## Scope implemented

FW-IMP-004 implemented only Foundation technical primitives for:
- audit;
- durable idempotency;
- transactional outbox;
- minimum outbox Worker processing;
- EF Core mappings and the first committed Foundation migration;
- targeted Foundation verification.

No ERP domain workflow or domain physical schema was implemented.

## Application contracts

Persistence-neutral contracts were added under `Mars.Application.Foundation`.

### Audit

Implemented:
- `AuditEntry`
- `IAuditWriter`

The first audit contract records:
- ActorId
- CompanyId
- optional BranchId
- CorrelationId
- Module
- Action
- optional EntityType
- optional EntityPublicId
- optional Reason
- OccurredAt

The baseline intentionally does not serialize arbitrary before/after entity state. This keeps the audit primitive bounded and avoids turning audit into a secret/PII dump or alternate business ledger.

### Idempotency

Implemented:
- `IdempotencyOperation`
- `IdempotencyOperationStatus`
- `IIdempotencyStore`

The baseline supports:
- logical scope;
- operation key;
- optional request fingerprint;
- InProgress / Succeeded / Failed status;
- optional result code;
- creation/completion timestamps.

It does not persist arbitrary business response payloads. Business-specific idempotency result semantics remain module-owned.

### Outbox

Implemented:
- `OutboxMessage`
- `OutboxWorkItem`
- `OutboxDispatchResult`
- `IOutboxWriter`
- `IOutboxWorkStore`
- `IOutboxDispatcher`

The contract carries:
- EventId
- EventType
- Module
- optional AggregatePublicId
- PayloadSchemaVersion
- Payload
- CreatedAt
- AvailableAt
- processing attempt/claim data for Worker execution.

No domain event payload type was invented by Foundation.

## Infrastructure persistence

Persistence implementation is owned by:
- `src/Mars.Infrastructure/Persistence/Foundation/`

Implemented records/mappings/stores:
- `AuditEventRecord`
- `IdempotencyOperationRecord`
- `OutboxMessageRecord`
- EF Core configurations for all three
- `EfAuditWriter`
- `EfIdempotencyStore`
- `EfOutboxStore`

`MarsDbContext` now maps exactly these three Foundation structures and no ERP domain entity.

## Physical Foundation structures

Schema:
- `foundation`

### foundation.audit_events

Purpose:
- operational/security audit evidence;
- not Inventory/Finance/commercial ledger authority.

Key fields:
- bigint identity `id`
- UUID `actor_id`
- UUID `company_id`
- optional UUID `branch_id`
- `correlation_id`
- `module`
- `action`
- optional `entity_type`
- optional UUID `entity_public_id`
- optional `reason`
- `occurred_at`

Indexes:
- entity/public identity + occurred time
- actor + occurred time
- correlation id

No arbitrary full-entity before/after JSON payload is stored by this baseline.

### foundation.idempotency_operations

Purpose:
- durable PostgreSQL logical-operation identity and outcome state.

Key fields:
- bigint identity `id`
- `scope`
- `operation_key`
- optional `request_fingerprint`
- `status`
- optional `result_code`
- `created_at`
- optional `completed_at`

Durable uniqueness:
- unique (`scope`, `operation_key`)

Constraint:
- completed timestamp is allowed only for Succeeded / Failed state.

Valkey is not involved in correctness.

### foundation.outbox_messages

Purpose:
- durable asynchronous event evidence and delivery work state.

Key fields:
- bigint identity `id`
- unique UUID `event_id`
- `event_type`
- `module`
- optional `aggregate_public_id`
- positive `payload_schema_version`
- JSONB `payload`
- `state`
- `created_at`
- `available_at`
- optional `processed_at`
- non-negative `attempt_count`
- optional bounded `last_error`
- optional `claim_token`
- optional `claimed_until`

Indexes:
- unique event id
- state + available time + internal id delivery queue access pattern

## Transaction boundary

`EfAuditWriter.Append`, `EfIdempotencyStore.Add` and `EfOutboxStore.Enqueue` attach Foundation records to the supplied `MarsDbContext` and do not independently commit.

This permits a future application command to stage:
- business state mutation;
- required audit evidence;
- durable idempotency state;
- outbox message

and commit them through the same PostgreSQL transaction boundary.

No distributed transaction was introduced.

Outbox claiming/delivery state changes occur after the durable outbox record exists.

## Outbox Worker baseline

Implemented:
- `src/Mars.Worker/Foundation/Outbox/OutboxBatchProcessor.cs`

Properties:
- bounded batch size;
- finite claim lease;
- CancellationToken propagation;
- explicit Success / Retry / terminal Failed result paths;
- claim-token ownership check before state transition;
- graceful cancellation compatibility;
- no scheduling framework;
- no external provider integration.

`EfOutboxStore` uses conditional PostgreSQL updates through EF Core for durable claim ownership and state transition. No second raw-SQL persistence architecture was introduced.

Heavy concurrency proof is deferred to Full Test Day.

## Migration

First committed Foundation migration:
- `src/Mars.Infrastructure/Persistence/Migrations/Foundation/20260922095311_FwImp004FoundationPrimitives.cs`
- generated Designer file
- `MarsDbContextModelSnapshot.cs`

Migration ownership:
- Foundation

Affected logical contracts:
- Audit Event
- Idempotency Operation
- Outbox Message

The migration was generated by EF Core tooling from the committed model; it was not manually authored as parallel DDL.

Migration creates only:
- `foundation.audit_events`
- `foundation.idempotency_operations`
- `foundation.outbox_messages`

It does not create Party, Product, Inventory, Sales, Purchasing, Warehouse, Finance, Returns, Quality or other domain-module structures.

No migration was applied to a real test or production PostgreSQL database in FW-IMP-004.

Backfill:
- none; these are new Foundation structures.

Destructive change:
- none in the forward migration.

Production lock/data-volume behavior:
- not claimed by runner-local generation/model verification; deployment/rehearsal remains later work.

## Targeted verification

Final tested source commit:
- `06109051f530dff60774dc43d369986b343f4158`

Final successful workflow:
- Foundation Build
- GitHub Actions run `35713295141`

Environment:
- self-hosted runner: `mars-ci`
- .NET SDK: `10.0.401`
- runtime: `10.0.12`

Final results:
- tool restore: PASS
- solution restore: PASS
- Release build: PASS
- warnings: 0
- errors: 0
- targeted Foundation tests: PASS — 19 / 19
- committed migration scope inspection: PASS
- EF model-drift check: PASS — no pending model changes
- project-reference verification: PASS

Targeted checks include:
- model contains only the three FW-IMP-004 Foundation entities;
- idempotency scope/key unique constraint intent;
- outbox event id uniqueness;
- outbox payload PostgreSQL JSONB mapping;
- audit required scope validation;
- idempotency required identity validation;
- outbox payload-schema-version validation;
- migration-specific connection configuration requirement;
- Worker Success / Retry / terminal Failed paths;
- Worker cancellation handling.

These are targeted unit/model/metadata checks. They do not prove heavy PostgreSQL concurrency behavior.

## Failed verification history

### Run 35712678842

Passed:
- restore;
- build;
- 19 targeted tests.

Failed:
- migration inspection scope check.

Cause:
- the first CI regex searched generated EF metadata too broadly and matched `ProductVersion`, producing a false positive for prohibited Product schema.

Correction:
- scope check was narrowed to domain table-creation names.

### Run 35713103839

Passed before failure:
- restore;
- build;
- 19 targeted tests;
- committed migration existence/scope check;
- EF model-drift check.

Failed:
- optional generated SQL script command.

### Run 35713216823

Again confirmed:
- no model drift.

The optional SQL script command still exited non-zero in the runner without adding stronger evidence than the already successful generated migration/model checks.

Correction:
- the optional script step was removed.
- committed migration existence/scope plus EF `has-pending-model-changes` remains the normal fast migration-contract gate.

The final run `35713295141` passed all retained gates.

## Boundaries preserved

Not introduced:
- ERP domain entities/tables;
- business posting rules;
- authentication/identity provider;
- OpenAPI tooling;
- structured logging/metrics backend;
- file-storage backend;
- scheduling library;
- external provider adapters;
- UI;
- deployment;
- production secret-store choice;
- production ingress/reverse-proxy choice.

No credentials or secret values were added to source/evidence.

## Planning metric

Exact master section-8 planning completion remains:
- 9 / 30 = 30.0%.

P2 remains:
- 8 / 8 = 100.0%.

FW-IMP-004 is implementation progress and does not increment section-8 planning completion.

## Next package and blocker

Repository-defined next package:
- `FW-IMP-005 — API foundation`

Before full FW-IMP-005 implementation, these two technology decisions have reached their relevant step and must be explicitly resolved:
1. exact authentication/identity provider;
2. exact OpenAPI tooling.

They must not be guessed.

Other deferred Foundation technology gates remain deferred until their own relevant implementation steps.
