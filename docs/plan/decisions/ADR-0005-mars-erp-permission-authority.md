# ADR-0005 — Mars ERP Permission Authority Baseline

Status: Accepted
Date: 2026-09-23
Owners: MarsOtomasyon architecture/security

## Context

P5 Parties first implementation slice requires the frozen `party.create` permission with server-side authorization.

Existing Foundation provides:
- ASP.NET Core Identity for account/credential primitives;
- OpenIddict for OAuth/OIDC protocol and token authority;
- trusted ActorId / CompanyId / optional BranchId execution context;
- ASP.NET Core authorization middleware.

ADR-0003 explicitly keeps ERP permissions and company/branch authorization Mars-owned and states that the identity stack authenticates principals but does not become ERP authorization authority.

The repository did not yet define the persistence/evaluation authority for ERP permission grants.

## Decision

Mars ERP permissions use a Mars-owned authorization abstraction and PostgreSQL-backed effective grants.

### Authority

Authoritative effective permission grant grain for the initial baseline:

- ActorId;
- CompanyId;
- PermissionCode;
- current grant state.

Examples of PermissionCode follow the already frozen convention:

`<module>.<resource>.<action>`

For the first Party slice:
- `party.create`.

PostgreSQL is authoritative.

Identity/OpenIddict token/cookie/user-claim state is not authoritative ERP permission truth.

### Application boundary

Foundation/Application exposes a persistence-neutral permission evaluation contract.

Conceptual shape:

- current trusted execution context;
- required permission code;
- asynchronous allow/deny result.

State-changing application handlers enforce required permissions through this contract even when the transport endpoint also has an authorization policy.

Domain modules own permission names. Foundation owns only evaluation mechanics.

### Infrastructure boundary

Infrastructure implements the evaluator against Mars-owned PostgreSQL permission grants.

The initial implementation may use direct actor + company + permission effective grants.

A role/group administration model is not invented by this ADR. A later accepted role model may feed the same effective authorization contract without changing Party business handlers.

### API boundary

API authorization policies may use the same evaluator to reject unauthorized requests early.

Endpoint policy is defense in depth; application-command permission enforcement remains required.

Authentication alone never satisfies a Party permission.

### Company scope

Permission is evaluated for the trusted `CompanyId` from `IExecutionContext`.

The client cannot supply or override the authoritative company scope.

Branch-specific permission storage is not introduced until a concrete module requires branch-scoped grants.

### Revocation / stale sessions

Authoritative permission evaluation uses current PostgreSQL grant state.

A permission copied into a cookie/token cannot be the sole authorization proof.

Therefore removing/revoking an effective grant must affect subsequent permission evaluations without requiring an OAuth/OIDC token to become the ERP permission source of truth.

Valkey may later cache authorization results only as non-authoritative acceleration with explicit invalidation/TTL policy.

### Grant administration

This ADR freezes evaluation authority, not a permission-management UI/workflow.

No role editor, group model or production grant administration screen is invented for PARTY-IMP-001.

Controlled test/bootstrap data may create grants for verification, but production authorization administration requires its own accepted workflow before operational use.

## Logical persistence contract

Foundation owns a logical **Permission Grant** record.

Required semantics:
- actor identity;
- company scope;
- permission code;
- current active/revoked state;
- grant/revoke audit metadata;
- durable uniqueness preventing duplicate active effective grants for the same actor/company/permission.

Exact physical table/column names are implementation details.

No ERP permission rows are stored as OpenIddict application/scope/token permissions.

## Consequences

Positive:
- preserves ADR-0003 separation between authentication/protocol and ERP authorization;
- current grants are revocable without making token claims authoritative;
- company scope is explicit;
- domain handlers stay provider-independent;
- future role/group administration can be added behind the same evaluator.

Costs:
- one Mars-owned authorization persistence structure and evaluator are required;
- positive TEST authorization requires controlled grant fixture/bootstrap;
- a future administration workflow remains to be planned.

## Alternatives considered

### Treat authenticated user as authorized
Rejected. Contradicts frozen Party permissions and security rules.

### Store ERP permission truth only in OpenIddict/token claims
Rejected. Contradicts ADR-0003 by making protocol/session state ERP authorization authority and complicates revocation.

### Use ASP.NET Identity user claims as the authoritative ERP grant store
Rejected as the baseline authority. It would couple ERP permission persistence to the account/identity store. Identity claims may later be derived transport/session data, but current PostgreSQL Mars grants remain authoritative.

### Introduce roles/groups immediately
Rejected for the first baseline because no frozen role/group administration contract exists. Direct effective grants are the smallest authority needed by the first Party command.

## Affected areas

- Foundation/Application authorization abstraction
- Infrastructure/PostgreSQL persistence
- ASP.NET Core authorization policies/handlers
- Party command authorization
- targeted authorization tests
- future Settings/Security administration

No Finance, Inventory or commercial posting authority changes.

## Revisit conditions

Revisit when:
- accepted role/group/team permission administration is planned;
- branch-scoped grants become required;
- enterprise federation requires externally managed entitlement mapping;
- authorization scale requires a measured cache/projection;
- an external policy engine is explicitly selected.

## Sources

- `docs/plan/decisions/ADR-0003-identity-openiddict-baseline.md`
- `docs/plan/01-foundation/framework-plan.md`
- `docs/plan/03-cariler/permissions.md`
- `docs/plan/03-cariler/p5-first-slice-readiness.md`
- `docs/db/02-module-ownership.md`
- `docs/db/03-entity-catalog.md`
- `src/Mars.Api/Foundation/Authentication/`
- `src/Mars.Api/Program.cs`
- `src/Mars.Infrastructure/Identity/`
