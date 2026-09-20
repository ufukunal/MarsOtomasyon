# MarsOtomasyon Planning Standard

## Purpose
Every module plan must be detailed enough that a new AI session can implement it without inventing business rules.

A folder existing is not a plan. A plan is accepted only when the required sections below are populated with source-backed decisions or explicitly marked UNKNOWN/BLOCKED.

## Mandatory module files
Each module should converge to this structure where relevant:

```
<module>/
├── README.md
├── plan.md
├── workflows.md
├── forms.md
├── data-contract.md
├── permissions.md
├── integrations.md
├── reports.md
├── acceptance-criteria.md
└── full-test-day.md
```

Not every module needs every file. Unneeded files must be omitted deliberately, not replaced with empty placeholders.

## Mandatory module contract

### 1. Purpose
- Business purpose
- Primary users
- In-scope processes
- Out-of-scope processes

### 2. Sources
List exact repository paths and accepted decisions used by the plan.

### 3. Actors and permissions
For each actor:
- read
- create
- edit draft
- approve
- post
- cancel
- reverse
- export
- administrate

### 4. State model
For each transactional object:
- states
- allowed transitions
- forbidden transitions
- transition owner
- side effects
- terminal states

### 5. Workflow contract
For every form/action answer:
- What is it for?
- What does it read?
- What does it create/change?
- DOC effect?
- RES effect?
- STOCK effect?
- ACCOUNT effect?
- CASH/BANK effect?
- COST effect?
- Source document?
- Target document?
- Partial operation?
- Cancel/reversal behavior?
- Approval?
- Audit?
- Outbox/integration?

### 6. Quantity contract
Where applicable:
- ordered
- reserved
- received
- shipped
- invoiced
- returned
- remaining
- over/under tolerance
- UOM conversion

### 7. Financial contract
Where applicable:
- recognition point
- debit/credit direction
- currency
- exchange rate
- tax
- discount
- rounding
- settlement
- reversal

### 8. Historical snapshot contract
Specify which values are frozen at posting/document time:
- customer/vendor identity
- address
- tax identity
- product code/name
- UOM
- price
- tax rate
- exchange rate
- other legally/operationally relevant values

### 9. Data contract
Before SQL:
- entities
- relationships
- ownership
- company/branch/warehouse scope
- unique rules
- concurrency rules
- ledger/projection/snapshot classification

### 10. UI contract
- list screen
- detail screen
- form sections
- primary action
- keyboard behavior
- F2/lookup usage
- mobile behavior
- loading/empty/error
- partial-operation visibility

### 11. Integration contract
For every external side effect:
- trigger
- outbox event
- provider adapter
- idempotency
- retry
- reconciliation
- error center
- webhook/security where needed

### 12. Reporting contract
Each KPI/report requires:
- formula
- grain
- filters
- source
- excluded statuses
- currency/time rules

### 13. Edge cases
At minimum consider:
- duplicate
- concurrency
- partial processing
- stale state
- invalid transition
- cancellation
- reversal
- inactive master
- rounding
- cross-company access
- provider timeout

### 14. Acceptance criteria
Use verifiable statements. Avoid vague text such as "works correctly".

### 15. Full Test Day backlog
Heavy scenarios are recorded here but not run during normal development.

## Planning order
For transactional modules:

```
Business workflow
→ Form/effect matrix
→ State machines
→ Domain model
→ Logical DB model
→ PostgreSQL schema/migrations
→ API contracts
→ UI contracts
→ implementation
```

SQL-first design is forbidden when business behavior is not yet understood.

## UNKNOWN/BLOCKED
Unknown business rules must appear explicitly:

```
UNKNOWN:
- ...

BLOCKED:
- decision required from ...
```

The AI must not fill these with convention or general ERP knowledge and then treat them as project decisions.
