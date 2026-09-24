# Carrier Performance — Detailed Plan

Status: DETAILED PLANNING FROZEN

## V38 product surface
List: Taşıyıcı, Shipment, OTD %, Hasar %, Kayıp %, Ortalama Süre, Maliyet, Score, Trend.
Detail tabs: Genel, Shipment, SLA, Claims, Cost, Timeline.

## Ownership
Module owns carrier reference linkage, shipment-service evidence, claims and performance projections. Sales/Warehouse own Dispatch/handoff. Finance owns carrier cost/accounting. Provider integration owns external event ingestion mechanics.

## Records
CarrierRef, ShipmentServiceLink, TrackingEvidence, DeliveryMilestone, SlaSnapshot, Damage/LossClaim, CostEvidenceLink, MetricSnapshot.

## Workflow
Shipment evidence CREATED -> HANDED_OVER -> IN_TRANSIT -> DELIVERED | EXCEPTION | LOST/RETURNED as external evidence; it must not post Inventory by itself.
Claim OPEN -> SUBMITTED -> ACCEPTED/REJECTED -> SETTLED/CLOSED.

## KPI
OTD requires promised-vs-actual definition; transit duration exact timestamps/timezone; damage/loss rates exact denominator; cost metric source authority; score formula versioned.

## Permissions/API/UI
carrier.read/manage, carrier.shipment.read, carrier.claim.manage, carrier.performance.read.
Routes /api/v1/carriers, /shipments, /claims, /performance.

## Acceptance
Dispatch linkage, duplicate provider events, KPI denominator/date rules, no stock mutation, claim lifecycle and company scope.

## UNKNOWN
Carrier providers, SLA formula/score weights and settlement integration are implementation-time gates.
