# Active Tasks

## P4 — Resolve required Foundation technology choices
**Status:** BLOCKED ON OWNER DECISIONS — NO CODE

Gate classification is complete:
`docs/plan/01-foundation/p4-readiness-decision-gates.md`

Only two choices block the first P4 implementation slice:

1. Exact .NET SDK/runtime baseline
   - technical recommendation: .NET 10 LTS
   - owner acceptance required.

2. Exact ORM/data-access baseline
   - technical recommendation: EF Core 10 + Npgsql as default persistence/migration stack;
   - targeted raw SQL remains an explicit escape hatch, not a competing default.
   - owner acceptance required.

All other listed technology gates are classified as deferrable or not required for the first P4 slice.

Do not start src/, tests/, SQL/migrations, dependencies or deployment until both owner choices are explicit.

Exact planning progress remains:
- master: 9 / 30 = 30.0%
- P2: 8 / 8 = 100.0%
