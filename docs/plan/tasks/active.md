# Active Tasks

## PLAN-002 — Sales workflow contract
**Status:** BLOCKED — OWNER DECISIONS REQUIRED

Target: docs/plan/05-satis/

### Completed planning work
- module purpose/scope
- Quote/Order/Reservation/Dispatch/Invoice/Collection/Return linkage
- state machines
- effect matrix
- quantity/partial contract
- source/target links
- historical snapshot needs
- V38 Sales UI mapping
- permissions
- integrations
- reporting contract
- Full Test Day backlog

### Blocking decisions
- SALES-B001 Collection allocation model
- SALES-B002 Direct Sales Invoice stock behavior
- SALES-B003 Quote partial conversion
- SALES-B004 Reservation trigger
- SALES-B005 Cost/COGS recognition
- SALES-B006 Tax/discount/rounding/FX
- SALES-B007 Approval policy
- SALES-B008 Confirmed-order amendment

### Restrictions
- no SQL schema
- no application code
- no PLAN-003 until blockers resolved and PLAN-002 frozen
- no branch / PR / force push
- no heavy tests
