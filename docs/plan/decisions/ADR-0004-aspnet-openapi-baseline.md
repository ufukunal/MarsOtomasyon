# ADR-0004 — Microsoft.AspNetCore.OpenApi 10.0.12 Baseline

Status: Accepted
Date: 2026-09-22
Owners: Project Owner

## Context

FW-IMP-005 API foundation requires a concrete OpenAPI generation baseline.

MarsOtomasyon already fixes:
- ASP.NET Core / .NET 10;
- /api/v1 routing baseline;
- explicit request/response contracts;
- deterministic error semantics;
- future API contract testing and client integration.

The OpenAPI decision should provide machine-readable API contracts while minimizing dependency surface and avoiding unnecessary coupling to documentation UI or client-generation technology.

## Decision

MarsOtomasyon will use **Microsoft.AspNetCore.OpenApi 10.0.12** as the OpenAPI document-generation baseline.

The accepted scope is:
- OpenAPI 3.1 document generation;
- runtime document generation where appropriate;
- build-time document generation where appropriate;
- document/schema/operation transformer/customization APIs where required by Mars contracts.

This decision does **not** select:
- Swagger UI or another API documentation UI;
- client-generation tooling;
- Swashbuckle;
- API gateway products.

Swashbuckle or another OpenAPI layer may be added or replace this baseline only when a concrete requirement justifies an explicit follow-up/superseding decision.

## Consequences

Positive:
- uses the Microsoft-maintained OpenAPI surface aligned directly with the accepted .NET 10 baseline;
- supports machine-readable OpenAPI contracts without adding a broader third-party Swagger stack;
- keeps UI/client-generator decisions independent;
- reduces dependency surface and makes future .NET upgrades easier to evaluate;
- transformer APIs allow Mars-specific contract customization without redefining API semantics.

Negative:
- features that exist only in Swashbuckle-specific filters/UI ecosystems are not automatically available;
- future advanced documentation/UI requirements may justify an additional tool;
- build/runtime document generation must be tested against Mars versioning/error/DTO conventions.

Infrastructure-change resilience:
- API contracts must remain expressed through ASP.NET Core endpoint/DTO metadata and Mars-owned conventions rather than tool-specific annotations where avoidable;
- OpenAPI documents are generated artifacts, not the authority for domain behavior;
- client applications should not be coupled to a specific generator implementation;
- changing OpenAPI tooling later should be possible without changing route/business semantics or public DTO ownership.

## Alternatives considered

- Swashbuckle.AspNetCore 10.x: mature and extensible with Swagger UI/filter ecosystem, but currently adds more surface than Mars requirements need.
- NSwag or other OpenAPI/client-generation suites: useful when client-generation or specialized document tooling becomes a concrete requirement, but not required for the current API foundation.
- no OpenAPI tooling: rejected because the Foundation contract requires generated machine-readable API documentation.

## Affected areas

- Mars.Api
- API contract documentation
- build/CI contract generation
- future API contract tests
- future client integration
- developer tooling

## Revisit conditions

Revisit when:
- built-in Microsoft OpenAPI capabilities cannot express a required Mars API contract accurately;
- a committed client-generation workflow requires capabilities not provided by the accepted baseline;
- documentation UI becomes an explicit product/operations requirement;
- .NET/OpenAPI package evolution makes another supported tool materially safer or simpler.

Any replacement should preserve:
- /api/v1 contract semantics;
- explicit Mars request/response DTO ownership;
- deterministic error model;
- generated-document portability independent of UI/client-generator choice.

## Sources

Repository:
- docs/plan/master-project-plan.md
- docs/plan/01-foundation/framework-plan.md
- docs/plan/01-foundation/p4-readiness-decision-gates.md
- docs/plan/project-state.yaml
- docs/plan/active-task.yaml
- docs/plan/decisions/ADR-0001-dotnet-10-lts-baseline.md

Official external:
- https://www.nuget.org/packages/Microsoft.AspNetCore.OpenApi/10.0.12
- https://learn.microsoft.com/aspnet/core/fundamentals/openapi/aspnetcore-openapi

Owner decision:
- explicit project-owner approval in the 2026-09-22 FW-IMP-005 decision-closing instruction.
