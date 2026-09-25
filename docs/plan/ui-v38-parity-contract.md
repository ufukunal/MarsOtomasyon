# MarsOtomasyon — V38 UI Parity Contract

Status: OWNER-LOCKED / IMPLEMENTATION AUTHORITY

Canonical reference:
`docs/reference/ui/marsotomasyon_ui_v38_cari_bakiye_sadelestirildi.html`

## 1. Owner intent
The production MarsOtomasyon UI must reproduce the canonical V38 product surface with high visual and interaction fidelity. The technical implementation is new; the visible product is not subject to discretionary redesign.

## 2. What must stay recognizable
- application shell proportions and hierarchy;
- white left sidebar, branding, grouped navigation and menu search;
- top workspace bar, global-search position, work tabs and contextual actions;
- menu labels/grouping/order unless a frozen module plan explicitly supersedes a concept;
- page title/header/action composition;
- list/detail/form/document information architecture;
- grid density, visible columns, filter/search placement and row actions;
- tabs and major section ordering;
- compact ERP control density;
- V38 color/surface/border/radius language;
- keyboard-efficient behavior and the V38 user journey.

## 3. Allowed deviations
Visible changes require a newer accepted domain/business decision, server-authoritative security scope, accessibility, responsive/mobile constraints, or a documented technical impossibility. Developer preference, modernization or simplification is not a valid reason.

The production source must not copy V38's single-file patch architecture, monkey patches, global mutable state, localStorage business persistence or inline business authority.

## 4. Implementation truth
Backend/API completion is not UI completion. An implemented module is not visually complete until its V38 surface is mapped and reviewed for visual parity, information-architecture parity, workflow parity and documented deviations. Future V38 menu items may stay visible as disabled/planned surfaces but must not be presented as implemented.

## 5. Current catalog baseline
- 17 main navigation groups
- 178 menu items
- 260 screen definitions

## 6. Acceptance
Normal module acceptance includes V38 parity for changed UI surfaces. Release hardening requires a complete V38 route-to-production-route matrix with KEEP/ADAPT/MERGE/REMOVE/BLOCKED classification and evidence for every non-KEEP decision.
