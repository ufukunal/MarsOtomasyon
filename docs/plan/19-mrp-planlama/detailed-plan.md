# MRP / Planning — Detailed Plan

Status: DETAILED PLANNING FROZEN

## V38 source
MRP / Planlama exists as a custom/later screen; some historical V38 patches mark it deprecated. Treat the business capability as planned, not the old route implementation.

## Ownership
MRP owns planning calculations/proposals. Sales owns demand documents, Inventory owns stock/reservation, Purchasing owns purchase commitments, Production owns production orders, Product/Production own BOM.

## Inputs
Confirmed/plannable demand, forecast if later accepted, on-hand eligible stock, reservations, open supply, BOM revisions, lead times, safety stock, lot-sizing parameters, planning horizon and calendars.

## Outputs
Pegged demand/supply rows, net requirements, planned purchase proposals, planned production proposals, transfer suggestions, expedite/defer/cancel messages. Outputs are proposals only until owning module accepts them.

## Algorithm contract
Gross requirement -> scheduled/available supply -> safety requirement -> net requirement -> lot-size/lead-time scheduling -> pegged proposal. Every run stores input-as-of/watermark and parameter snapshot.

## States
Run: QUEUED -> RUNNING -> COMPLETED | FAILED | SUPERSEDED.
Proposal: PROPOSED -> REVIEWED -> ACCEPTED_TO_OWNER | REJECTED | EXPIRED.

## Data
PlanningRun, DemandPeg, SupplyPeg, NetRequirement, Proposal, ParameterSnapshot, ExceptionMessage. Decimal quantities, exact Product/Variant/UOM/company.

## Permissions/API/UI
mrp.run, mrp.read, mrp.proposal.review/accept, mrp.parameter.manage.
Routes /api/v1/mrp/runs, /requirements, /proposals, /exceptions.

## Concurrency
Run uses consistent source watermark; acceptance revalidates current owner state to avoid stale proposal execution.

## Acceptance
Multi-level BOM fixture, reservation/on-hand/open-supply netting, pegging, stale proposal rejection, no stock mutation, deterministic rerun with same snapshot.

## UNKNOWN
Forecast ownership, lot-sizing algorithms beyond baseline, safety-time conventions and automatic proposal acceptance are not specified by V38 and require explicit source/configuration.
