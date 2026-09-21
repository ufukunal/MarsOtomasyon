# Warehouse Permissions and Scope

Status: FROZEN planning-level authorization contract.

## 1. Principles

- authorization is server-side;
- company/Warehouse scope is explicit;
- operational work permission does not imply inventory-posting permission;
- overrides, count adjustment, scrap and transit loss resolution are privileged;
- no permission permits silent negative stock or lot/serial mismatch.

## 2. Permission namespace

Warehouse master:
- warehouse.read
- warehouse.manage
- warehouse.deactivate

Location:
- warehouse.location.read
- warehouse.location.manage
- warehouse.location.deactivate

Receiving / disposition / put-away:
- warehouse.receiving.read
- warehouse.putaway.execute
- warehouse.disposition.read
- warehouse.disposition.release
- warehouse.disposition.change

Picking / packing / loading:
- warehouse.pick.read
- warehouse.pick.execute
- warehouse.pick.strategy_override
- warehouse.pack.execute
- warehouse.stage.execute
- warehouse.load.execute

Sales Dispatch physical POST remains Sales-owned permission plus required Warehouse scope; Warehouse work permission alone cannot post Sales stock-out unless the actor also holds the accepted Dispatch POST permission.

Transfer:
- warehouse.transfer.read
- warehouse.transfer.create
- warehouse.transfer.issue
- warehouse.transfer.receive
- warehouse.transfer.reconcile
- warehouse.transfer.loss_adjust
- warehouse.transfer.reverse

Count:
- warehouse.count.read
- warehouse.count.create
- warehouse.count.execute
- warehouse.count.review
- warehouse.count.approve
- warehouse.count.post
- warehouse.count.reverse

Damage / disposal:
- warehouse.damage.record
- warehouse.scrap.request
- warehouse.scrap.approve
- warehouse.scrap.post

Trace:
- warehouse.trace.read

Scan/offline:
- no broad bypass permission; the synchronized business action requires the same permission as online execution.

## 3. Negative stock

There is no `warehouse.negative_stock_override` in PLAN-006.

If a command would create negative authoritative stock, reject it.

Administrative access does not bypass this invariant.

## 4. FEFO/FIFO override

Requires:
- `warehouse.pick.strategy_override`;
- mandatory reason;
- same company/Warehouse scope;
- eligible AVAILABLE/unexpired stock.

Override changes allocation choice only.
It cannot make blocked/expired stock eligible.

## 5. Put-away

`warehouse.putaway.execute`:
- can post accepted internal Location move;
- cannot change disposition except through separate disposition permission/action;
- cannot create/destroy stock.

Capacity or Location-policy validation cannot be bypassed by ordinary put-away permission.

## 6. Transfer

ISSUE:
- source Warehouse scope required.

RECEIVE:
- target Warehouse scope required.

If actor spans both, one actor may perform both steps.

Loss adjustment:
- distinct high-risk permission;
- mandatory reason;
- approval required;
- creator/reconciler cannot approve own loss adjustment where SoD applies.

## 7. Count SoD

Counter:
- records observation.

Reviewer:
- reviews discrepancy/recount.

Approver:
- approves non-zero adjustment.

Minimum rule:
- actor who performed/accepted the physical count cannot approve own non-zero COUNT_ADJUSTMENT.

A privileged admin cannot bypass audit/reason.

## 8. Scrap / disposal

Request/post requires:
- eligible non-available source quantity;
- reason;
- audit.

Approval:
- distinct `warehouse.scrap.approve`;
- requester cannot approve own scrap when approval is required.

No permission converts scrap into a direct Product/Location stock edit.

## 9. Deactivation

Warehouse/Location deactivation:
- requires manage/deactivate permission;
- all blocking stock/work/reservation/transit conditions must be zero/resolved.

Permission cannot force deactivation while blockers remain.

## 10. Finance/valuation

Warehouse permissions never grant:
- inventory revaluation;
- cost layer edit;
- COGS posting;
- supplier/customer ledger posting.

Positive count/scrap financial valuation follows Finance policy.

## 11. Audit

High-risk actions record:
- actor;
- company/Warehouse;
- action;
- source/work/document;
- Product/lot/serial;
- quantity;
- source/target Location/status;
- default vs override choice;
- reason;
- approval;
- timestamp/correlation/device.

Audit complements but never replaces Inventory Ledger.
