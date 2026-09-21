# PLAN-009 Acceptance Criteria

Status: COMPLETED / FROZEN.

- [x] Customer return authorization/RMA identity and lifecycle deterministic.
- [x] Supplier/purchase return lifecycle deterministic.
- [x] Source-linked normal path and controlled source-less exception frozen.
- [x] Customer physical receipt POST is exact STOCK IN point and starts QUARANTINE.
- [x] Supplier physical shipment POST is exact STOCK OUT point.
- [x] QC/disposition states and scrap boundary explicit.
- [x] Physical and financial progress remain separate linked dimensions.
- [x] Customer credit, customer refund, supplier adjustment and supplier refund directions explicit.
- [x] No Invoice allocation/open-item authority introduced.
- [x] Partial return and cumulative source cap deterministic.
- [x] Product/Variant/UOM/lot/serial/source validation explicit.
- [x] Sales Dispatch/Sales Invoice and Goods Receipt/Supplier Invoice source roles explicit.
- [x] Over-return and source-less exception policy explicit.
- [x] Cancellation/reversal dependency rules explicit.
- [x] Return after prior credit/refund dependency handled by compensation.
- [x] Replacement/exchange uses linked normal Sales workflow; no second sales engine.
- [x] Purchase-return valuation boundary follows current moving-average Finance policy.
- [x] Customer-return cost/COGS correction remains Finance-owned and source-linked.
- [x] Source-less inbound cannot silently receive zero valuation.
- [x] FX/revaluation/refund boundary follows PLAN-007.
- [x] Permissions, approval, SoD and company/warehouse scope explicit.
- [x] Reporting semantics frozen.
- [x] Durable idempotency/concurrency risks defined for P3.
- [x] Inventory and Finance remain sole physical/monetary authorities.
- [x] No automatic Customer/Supplier cross-role netting.
- [x] No material Returns/RMA business blocker remains.
- [x] No SQL/application implementation added.
- [x] Heavy tests not run; Full Test Day backlog recorded.

Completion planning metrics:
- Master: 9 / 30 = 30.0%
- P2 Core Commercial: 8 / 8 = 100.0%
