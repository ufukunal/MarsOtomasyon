# Planning Backlog

## Immediate — P4 owner decisions
Status: BLOCKED / OWNER INPUT REQUIRED.

Classification is complete:
- docs/plan/01-foundation/p4-readiness-decision-gates.md

Required decisions:
1. exact .NET SDK/runtime baseline;
2. exact ORM/data-access baseline.

Technical recommendations:
- .NET 10 LTS;
- EF Core 10 + Npgsql default persistence/migration baseline, with targeted raw SQL only when explicitly justified.

Do not record these as accepted until owner confirms.

## P4 — Foundation first thin implementation slice
Status: BLOCKED UNTIL THE TWO REQUIRED OWNER DECISIONS CLOSE.

After closure:
- create accepted ADR(s);
- update Foundation contract/state;
- activate the smallest implementation work package;
- then implementation may create the solution/project skeleton and targeted Foundation primitives according to that task's explicit scope.

## Quality — master planning sequence item 10
Target: docs/plan/08-kalite/
Status: PLANNING BACKLOG / NOT STARTED.

Quality remains the next section-8 item that can raise exact planning completion from 9/30.
Operational execution belongs P6.

## Commerce/B2B/Architect/Marketplace
Target: docs/plan/15-e-ticaret-b2b-api/
Status: P7 / NOT IMMEDIATE.

## Full Test Day
Status: DEFERRED BY POLICY.

Exact section-8 planning progress:
- master: 9 / 30 = 30.0%
- P2: 8 / 8 = 100.0%
