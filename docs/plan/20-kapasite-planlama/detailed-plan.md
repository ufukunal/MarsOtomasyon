# Capacity Planning — Detailed Plan

Status: DETAILED PLANNING FROZEN

## V38 source
Finite Capacity exists as a custom/later capability and was deprecated in some historical simplified menu patches. Capability remains portfolio-planned; old renderer is not authoritative.

## Ownership
Capacity Planning owns scheduling/load projections. Production owns actual operation execution; Settings owns calendars where centralized.

## Inputs
Routing operations, released/planned production demand, work center/resource calendars, shifts, setup/run time, availability and alternate resources.

## Outputs
Resource load, available capacity, overload/underload, bottleneck flags, proposed start/end, forward/backward schedules and what-if scenarios. No output equals actual production completion.

## Records
CapacityRun, ResourceCalendarSnapshot, OperationDemand, ScheduledSlot, AlternateResourceChoice, Scenario, Bottleneck/Exception.

## State
Run QUEUED -> RUNNING -> COMPLETED | FAILED | SUPERSEDED.
Scenario DRAFT -> CALCULATED -> ACCEPTED/REJECTED; acceptance produces Production scheduling proposal, not silent execution.

## Constraints
Company/resource scope, non-overlap for finite committed slots where configured, time-zone aware calendars, immutable run snapshot, decimal duration/capacity units.

## Permissions/API/UI
capacity.run/read, capacity.scenario.manage/accept, capacity.calendar.read.
Routes /api/v1/capacity/runs, /loads, /scenarios, /exceptions.

## Acceptance
Calendar/shift edge cases, overload detection, alternate resource, forward/backward schedule determinism, stale source revalidation, no actual-state mutation.

## UNKNOWN
Exact scheduling heuristic/optimizer, priority weighting, overlap rules and OEE relationship are not specified by V38.
