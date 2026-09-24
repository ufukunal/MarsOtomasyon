# CRM-lite — Detailed Plan

Status: DETAILED PLANNING FROZEN

## V38 product surface
CRM-lite list: Fırsat, Cari/Lead, Aşama, Potansiyel, Olasılık, Sorumlu, Son Aktivite, Sonraki, Durum.
Detail tabs: Genel, Aktiviteler, Teklifler, Notlar, Timeline.

## Ownership
CRM owns lead/opportunity/activity pipeline. Party owns customer identity after conversion. Sales owns Quote/Order. Communications owns delivery evidence. Finance owns account balance.

## Records
Lead, LeadContactSnapshot, Opportunity, OpportunityStageHistory, Activity, Task/NextAction, Note, QuoteLink, Campaign/SourceRef, LostReason, CompetitorRef.

## Workflow
Lead NEW -> QUALIFYING -> QUALIFIED -> CONVERTED | DISQUALIFIED.
Opportunity OPEN stages configurable -> WON | LOST | ABANDONED.
Creating Quote links to Sales; CRM never owns commercial document truth.

## Effects
DOC informational/workflow. No RES/STOCK/ACCOUNT/CASH/COST.

## Rules
Potential/Probability are forecast metadata only, not ledger. Lead-to-Party conversion is explicit and duplicate-safe. Activities retain actor/time history; sensitive notes permissioned.

## Permissions/API/UI
crm.lead.*, crm.opportunity.*, crm.activity.*, crm.note.*, crm.convert_party, crm.quote.create_link.
Routes /api/v1/crm/leads, /opportunities, /activities.

## Acceptance
Lead conversion duplicate guard, stage history, Quote link, company/team visibility, stale update and permission tests.

## UNKNOWN
Stage catalog, probability defaults, campaign model and automatic scoring are configurable/not fixed by V38.
