# Sales Permissions and Scope

Status: planning-level permission contract. Authentication provider and storage are Foundation decision gates.

## 1. Principles

- Authorization is server-side.
- UI button visibility is UX only.
- Every state-changing command checks actor, company scope and required permission.
- Branch/warehouse scope is checked where the action operates on that scope.
- Historical read permission does not imply post/reverse permission.
- Approval and creator/approver separation are applied only when an approved policy requires them; thresholds are not invented.

## 2. Proposed permission namespace

### Quote
- sales.quote.read
- sales.quote.create
- sales.quote.edit_draft
- sales.quote.revise
- sales.quote.submit_approval
- sales.quote.approve
- sales.quote.send_customer
- sales.quote.convert_order
- sales.quote.cancel
- sales.quote.export

### Sales Order
- sales.order.read
- sales.order.create
- sales.order.edit_draft
- sales.order.submit_approval
- sales.order.approve
- sales.order.confirm
- sales.order.hold
- sales.order.release_hold
- sales.order.cancel_remaining
- sales.order.close
- sales.order.export

### Reservation initiation from Sales
Inventory/Warehouse may own the final permission name. Sales planning requires an action permission equivalent to:
- inventory.reservation.create
- inventory.reservation.release

The Sales UI may expose these actions only if actor also has the Inventory permission and source order is eligible.

### Dispatch
Warehouse/Shipping should own operational permission names. Required capabilities:
- sales.dispatch.read
- sales.dispatch.create
- sales.dispatch.edit_draft
- sales.dispatch.pick
- sales.dispatch.pack
- sales.dispatch.post
- sales.dispatch.handoff
- sales.dispatch.reverse
- sales.dispatch.export

Final module namespace can be normalized with Warehouse planning; do not duplicate equivalent permissions in two modules.

### Sales Invoice
- sales.invoice.read
- sales.invoice.create
- sales.invoice.edit_draft
- sales.invoice.post
- sales.invoice.reverse
- sales.invoice.send_edocument
- sales.invoice.export

### Proforma
- sales.proforma.read
- sales.proforma.create_from_source
- sales.proforma.send
- sales.proforma.export

### Returns linkage
Full Returns permissions belong to Returns/RMA.
Sales needs:
- sales.return.read_link
- permission to initiate return only if Returns/RMA plan explicitly adopts it.

### Collection
Finance owns:
- finance.collection.read
- finance.collection.create
- finance.collection.post
- finance.collection.reverse

Sales Invoice's Tahsilat action is a deep-link/start-context action and never grants Finance permission.

## 3. Role capabilities

Role names are descriptive personas, not hard-coded security roles.

### Sales user
Typical need:
- read/create/edit/revise quote;
- create/edit order;
- view reservation/shipping/invoice state;
- initiate downstream actions only with additional permission.

Cannot automatically:
- post invoice;
- post/reverse dispatch;
- post collection;
- approve own transaction if future SoD forbids it.

### Sales manager
Potential:
- approval capability according to SALES-B007;
- hold/release/cancel remaining;
- review exception reports.

Exact monetary/discount thresholds are UNKNOWN.

### Warehouse operator
- view eligible order source;
- create/edit picking/dispatch draft;
- pick/pack;
- no finance posting authority by default.

### Warehouse manager
- dispatch posting/reversal may require this permission depending future Warehouse policy.

### Finance/Accounting
- invoice post/reverse;
- collection post/reverse;
- e-document send;
- read source Sales documents.

### Administrator
Administrative capability is not a shortcut around audit or posted-history invariants.

## 4. Scope rules

Company:
- every read/write constrained to allowed company.

Branch:
- Quote/Order/Invoice branch access follows future branch ownership/numbering rules.
- actor cannot switch branch in client to bypass server scope.

Warehouse:
- Reservation/Dispatch must validate actor access to selected warehouse.
- source order belonging to company A cannot dispatch from company B warehouse.

Customer:
- access to customer data remains Parties permission/scoping concern.

## 5. High-risk commands

Require explicit distinct permission:
- Quote approval if adopted.
- Order approval/confirmation if adopted.
- Dispatch POST.
- Dispatch reverse.
- Invoice POST.
- Invoice reverse.
- Financial credit/refund.
- Collection POST/reverse.

Confirmation UX is appropriate for Post/Reverse/destructive cancel operations.

## 6. Audit expectations

For privileged transitions record:
- actor
- effective company/branch/warehouse context
- command/action
- document public reference
- prior/new state
- reason where required
- timestamp
- correlation id
- source/target references.

## 7. Permission blockers

SALES-B007:
- mandatory approval and SoD policy are not yet selected.

Warehouse planning:
- final ownership/namespace for reservation/dispatch permissions must avoid duplicate parallel permission systems.

Finance planning:
- collection and refund permission details remain Finance-owned.
