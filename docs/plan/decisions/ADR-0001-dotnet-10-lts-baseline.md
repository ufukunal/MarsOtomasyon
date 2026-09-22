# ADR-0001 — .NET 10 LTS Runtime and SDK Baseline

Status: Accepted
Date: 2026-09-22
Owners: Project Owner

## Context

MarsOtomasyon P4 Foundation implementation requires an exact .NET SDK/runtime baseline before creating the first solution/project skeleton.

The repository already fixes:
- .NET / ASP.NET Core / C# as backend technology;
- Linux + Docker as deployment baseline;
- ASP.NET Core API and .NET Worker runtime roles.

The exact SDK/runtime version was intentionally left as a Foundation decision gate.

## Decision

MarsOtomasyon will use **.NET 10 LTS** as the P4 Foundation runtime and SDK baseline.

Foundation implementation must target the .NET 10 generation unless this ADR is later superseded.

## Consequences

Positive:
- project target framework and SDK pinning become reproducible;
- ASP.NET Core and Worker projects can use one supported runtime baseline;
- package compatibility can be evaluated against a single major version;
- CI/build/container decisions can align to one runtime generation.

Negative:
- packages used by the project must support .NET 10;
- a later runtime upgrade requires explicit compatibility work and, if material, an ADR update/supersession.

## Alternatives considered

- .NET 11 pre-GA/RC: rejected for the initial baseline because the project requires a stable long-lived foundation rather than a pre-GA baseline.
- .NET 8 / .NET 9: not selected because they imply a shorter remaining support horizon relative to the accepted new-project baseline.

## Affected areas

- Foundation
- Mars.Api
- Mars.Worker
- Mars.Application
- Mars.Domain
- Mars.Infrastructure
- Mars.Contracts
- test projects
- CI/build
- Docker images
- deployment compatibility

## Revisit conditions

Revisit when:
- .NET 10 support approaches end of life;
- a required platform/package cannot support the accepted baseline;
- a later .NET LTS version provides a justified upgrade path and compatibility work is planned.

## Sources

Repository:
- docs/plan/master-project-plan.md
- docs/plan/01-foundation/framework-plan.md
- docs/plan/01-foundation/p4-readiness-decision-gates.md
- docs/plan/project-state.yaml
- docs/plan/active-task.yaml

Owner decision:
- current explicit project-owner instruction dated 2026-09-22 approving .NET 10 LTS.
