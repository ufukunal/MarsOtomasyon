# ADR-0003 — ASP.NET Core Identity + OpenIddict Authentication/Identity Baseline

Status: Accepted
Date: 2026-09-22
Owners: Project Owner

## Context

FW-IMP-005 API foundation requires a concrete authentication/identity baseline before authentication integration can be implemented.

MarsOtomasyon already fixes these boundaries:
- ASP.NET Core / .NET 10 backend;
- PostgreSQL authoritative persistence through EF Core 10 + Npgsql;
- server-side authorization;
- immutable ActorId / CompanyId / optional BranchId / CorrelationId execution context;
- Web, Desktop and Mobile must remain able to share a common client core while using platform-appropriate session/token handling;
- ERP permissions, company scope and branch scope remain Mars-owned application/domain authorization concerns.

A durable authentication decision must support current implementation while minimizing coupling if identity infrastructure changes later.

## Decision

MarsOtomasyon will use:

- **ASP.NET Core Identity on .NET 10** for user/account, credential and passkey-capable identity primitives;
- **OpenIddict 7.7.1 stable** for OAuth 2.0 / OpenID Connect protocol and token authority;
- PostgreSQL through the accepted EF Core 10 + Npgsql baseline for durable identity/protocol persistence.

Authorization ownership remains outside the identity provider:
- Mars ERP permission rules remain Mars-owned;
- CompanyId and BranchId authorization remain Mars-owned;
- the identity stack authenticates the principal and protocol session/token state but does not become ERP authorization authority.

Client/session direction:
- Web should prefer secure HttpOnly cookie/session semantics where appropriate;
- Desktop/Mobile use standard OAuth 2.0 / OpenID Connect flows;
- no Desktop or Mobile shell technology is selected by this ADR;
- browser localStorage is not the baseline location for bearer/refresh tokens.

Security/configuration direction:
- production secrets remain outside repository source control;
- signing/encryption credentials and provider secrets are configuration/secret concerns, not hard-coded application constants;
- no Keycloak or Microsoft Entra service is added by this decision.

Identity schema, package installation, middleware configuration and migrations belong to FW-IMP-005 implementation and are not performed by this decision record.

## Consequences

Positive:
- stays within the accepted .NET 10 modular-monolith technology family;
- uses standard OAuth 2.0 / OpenID Connect protocol boundaries for non-Web clients;
- preserves PostgreSQL authority and the accepted EF Core/Npgsql persistence architecture;
- avoids coupling ERP permissions/company/branch rules to a vendor identity product;
- allows future provider replacement behind standard protocol and Mars-owned authorization boundaries;
- supports future passkey/MFA/external-provider evolution without changing ERP authorization ownership.

Negative:
- Mars owns operation and secure configuration of the Identity/OpenIddict stack;
- token/signing-key lifecycle, revocation, client registration and protocol hardening require explicit implementation/testing;
- identity schema becomes part of the Mars PostgreSQL migration surface;
- replacing the provider later still requires account/protocol migration planning even though domain authorization remains decoupled.

Infrastructure-change resilience:
- application/domain code must not depend directly on OpenIddict persistence entities;
- Mars authorization code must consume authenticated identity/context abstractions rather than provider-specific token objects;
- protocol-facing clients should rely on standard OAuth/OIDC semantics rather than OpenIddict-specific behavior where avoidable;
- future replacement with Keycloak, Entra or another standards-compliant provider should be handled by superseding this ADR and adapting the authentication boundary, not rewriting ERP permission rules.

## Alternatives considered

- ASP.NET Core Identity token mode without a full protocol server: not selected because it is not intended to be a full-featured OAuth/OIDC identity/token server baseline.
- Keycloak: valid self-hosted alternative with mature identity-server capabilities, but introduces a separate Java/service operational topology not currently required.
- Microsoft Entra: valid managed-cloud alternative, but introduces tenant/cloud/vendor dependency not currently accepted.
- Custom token/authentication server: rejected because it would recreate security-sensitive protocol functionality already provided by established components.

## Affected areas

- Foundation
- Mars.Api
- Mars.Infrastructure
- authentication/session/token persistence
- Web authentication/session integration
- future Desktop/Mobile authentication
- security testing
- configuration/secrets
- deployment/runtime configuration

## Revisit conditions

Revisit when:
- OpenIddict or ASP.NET Core Identity no longer supports the accepted .NET baseline;
- identity must be operated independently from the modular monolith for organizational, scale or compliance reasons;
- enterprise federation or managed-cloud identity becomes a hard requirement;
- licensing/support/security constraints materially change;
- an infrastructure migration demonstrates lower total risk/cost with another standards-compliant provider.

Any replacement should preserve:
- standard OAuth/OIDC client boundary where possible;
- Mars-owned ERP authorization semantics;
- Actor/Company/Branch context contract;
- PostgreSQL/business-data authority boundaries unless separately superseded.

## Sources

Repository:
- docs/plan/master-project-plan.md
- docs/plan/01-foundation/framework-plan.md
- docs/plan/01-foundation/p4-readiness-decision-gates.md
- docs/plan/project-state.yaml
- docs/plan/active-task.yaml
- docs/plan/decisions/ADR-0001-dotnet-10-lts-baseline.md
- docs/plan/decisions/ADR-0002-ef-core-npgsql-baseline.md

Official external:
- https://documentation.openiddict.com/integrations/aspnet-core
- https://www.nuget.org/packages/OpenIddict.AspNetCore/7.7.1
- https://learn.microsoft.com/aspnet/core/security/authentication/identity

Owner decision:
- explicit project-owner approval in the 2026-09-22 FW-IMP-005 decision-closing instruction.
