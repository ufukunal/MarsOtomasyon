# FW-IMP-006 — Mars.Web + Mars.UI Foundation

Status: COMPLETED
Date: 2026-09-22

## Scope implemented

FW-IMP-006 implemented only the repository-defined web/UI Foundation slice:

- Vite + TypeScript frontend project under `src/Mars.Web`;
- framework-independent semantic HTML/CSS/TypeScript application shell;
- Mars-owned client router boundary;
- Mars-owned `/api/v1` API transport boundary;
- semantic Mars.UI design tokens;
- reusable Button, Field, Dialog, Tabs, Lookup and Grid primitives;
- basic responsive/print shell styling;
- browserless DOM-level component/API/router tests;
- static architecture checks;
- Node/npm/Vite verification integrated into the existing Foundation Build workflow.

No ERP business screen, domain workflow or business rule was implemented.

## Mars.Web structure

Created:
- `src/Mars.Web/package.json`
- `src/Mars.Web/package-lock.json`
- `src/Mars.Web/tsconfig.json`
- `src/Mars.Web/vite.config.ts`
- `src/Mars.Web/index.html`
- `src/Mars.Web/src/main.ts`
- `src/Mars.Web/src/app.ts`
- `src/Mars.Web/src/api-client.ts`
- `src/Mars.Web/src/vite-env.d.ts`
- `src/Mars.Web/src/ui/tokens.css`
- `src/Mars.Web/src/ui/base.css`
- `src/Mars.Web/src/ui/components.ts`
- `src/Mars.Web/tests/foundation.test.ts`
- `src/Mars.Web/scripts/verify-foundation.mjs`

No frontend framework was introduced.

## Shell and router

The minimum Mars.Web shell contains:
- application shell;
- Foundation-only navigation;
- sticky workspace header;
- route outlet;
- no invented ERP navigation, dashboard, KPI or module business content.

The Mars-owned router:
- registers simple future route definitions;
- uses History API navigation;
- handles browser back/forward through `popstate`;
- intercepts only same-origin links explicitly marked for Mars routing;
- contains no permission/business authorization logic.

Client route handling is not treated as a security boundary.

## API client

`ApiClient` provides:
- default `/api/v1` base path;
- configurable base URL;
- standard browser `fetch`;
- `credentials: "same-origin"` for the accepted web cookie/session boundary;
- JSON response parsing;
- deterministic non-success `ApiClientError`;
- correlation-id propagation from `X-Correlation-ID`;
- `AbortSignal` propagation;
- generic transport only, with no domain DTO ownership.

Not introduced:
- bearer token injection;
- localStorage/sessionStorage token persistence;
- client-authoritative company/branch scope;
- OAuth login/account UI.

Server-side authentication/authorization remains authoritative.

## Mars.UI design tokens

The initial token set covers:
- typography;
- spacing;
- control/surface radius;
- semantic page/surface/border/text colors;
- interactive blue;
- danger/success/warning states;
- visible focus color/ring;
- control and grid density;
- shell sizing.

The hard-edge control baseline follows the accepted V38 product character without copying the V38 override stack.

No Sales/Party/Product/etc. semantic tokens were introduced.

## Components

### Mars.Button
Implemented:
- primary;
- secondary;
- danger;
- quiet;
- disabled;
- loading/busy;
- native button semantics;
- preserved accessible action name while loading.

### Mars.Field
Implemented:
- native label/input association;
- required marker;
- help text;
- error text;
- `aria-invalid`;
- `aria-describedby`;
- readonly;
- disabled;
- input type adapter.

No domain validation rule exists in the primitive.

### Mars.Dialog
Implemented:
- accessible dialog role/name;
- modal semantics;
- focus entry;
- focus trap;
- Escape close policy;
- focus return;
- primary/secondary action slots;
- background containment through `inert`;
- safe re-parenting to `document.body` so containment does not inert the dialog itself.

Business confirmation policy remains module-owned.

### Mars.Tabs
Implemented:
- `tablist`, `tab`, `tabpanel` semantics;
- deterministic active tab;
- ArrowLeft/ArrowRight;
- Home/End;
- focus movement;
- no authorization semantics.

### Mars.Lookup
Implemented:
- generic async search contract;
- query/page/pageSize/`AbortSignal` request boundary;
- selected identity/display callbacks;
- F2 focus behavior;
- ArrowUp/ArrowDown keyboard navigation;
- Enter selection;
- Escape close;
- result listbox/options;
- optional "Daha fazla" hook for a future paged source.

No Customer/Product/Warehouse behavior was hard-coded.

### Mars.Grid
Implemented:
- semantic table/caption/header/row/cell structure;
- column definitions;
- text-only safe cell rendering;
- loading/empty/error state presentation;
- keyboard row navigation with ArrowUp/ArrowDown/Home/End;
- Enter activation hook;
- optional row activation callback;
- numeric/end alignment contract.

Not introduced:
- filtering;
- grouping;
- export;
- server-paging implementation;
- inline business edits;
- domain actions;
- virtualization without measured need.

## Accessibility boundary

Targeted baseline includes:
- semantic native controls;
- visible focus;
- labels and described-by relationships;
- disabled/readonly state;
- dialog focus lifecycle;
- modal background containment;
- tab semantics and keyboard navigation;
- lookup keyboard operation;
- grid row keyboard navigation.

The targeted browserless tests do not constitute a WCAG conformance claim.

## Security boundary

Preserved:
- secure web session boundary relies on server cookie/session semantics;
- no localStorage/sessionStorage token baseline;
- no frontend secret;
- no production credential;
- no unsafe `innerHTML` use;
- generic component rendering uses DOM nodes/text content;
- client route/UI state is not authorization;
- client company/branch values are not authoritative.

## V38 continuity

KEEP:
- dense ERP-oriented layout;
- white surfaces;
- controlled blue interactive accent;
- clear grid lines;
- work-tab interaction pattern;
- keyboard-efficient operation;
- professional document density.

ADAPT:
- sidebar/workspace shell converted to explicit Mars.Web structure;
- shared control styles converted to semantic Mars.UI tokens;
- buttons/forms/tabs/grids converted from CSS-class conventions into reusable TypeScript component contracts;
- modal behavior converted into explicit focus/containment lifecycle.

REJECT:
- single-file production architecture;
- chained render monkey patches;
- version-by-version style override layers;
- repeated global handlers;
- giant global state;
- arbitrary DOM attributes as business authority;
- localStorage business persistence;
- inline ERP business rules.

Production source does not import or copy the V38 reference file.

## Packages and exact versions

Node CI baseline:
- Node.js `24.21.0` LTS;
- npm `11.19.0`.

Dev dependencies:
- `vite 8.3.0`;
- `typescript 7.0.2`;
- `tsx 4.23.15`;
- `happy-dom 20.14.5`.

There are no runtime frontend package dependencies.

The npm dependency graph is committed in lockfileVersion 3 `package-lock.json`.

## CI

`.github/workflows/foundation-build.yml` now:
- sets up Node 24.21.0;
- verifies Node/npm;
- runs `npm ci --prefix src/Mars.Web`;
- runs `npm run verify --prefix src/Mars.Web`;
- then retains the existing .NET restore/build/tests/migration/API/project-reference gates.

Frontend verification includes:
- TypeScript typecheck;
- 10 targeted Node/happy-dom tests;
- Vite production build;
- static architecture checks.

Static architecture checks reject:
- React;
- Vue;
- Angular;
- Bootstrap;
- Tailwind;
- jQuery;
- localStorage/sessionStorage production usage;
- V38 reference import/copy;
- unsafe `innerHTML`.

## Targeted verification

Implementation starting HEAD:
- `7e225060fa45af9835f72c54fdd007fbdd529cac`

Final tested implementation commit:
- `1264981655329099a086c7048c0890caf04dc9f2`

Final successful workflow:
- Foundation Build;
- GitHub Actions run `35729371845`;
- run number 33.

Environment:
- self-hosted Linux runner;
- Node.js `24.21.0`;
- npm `11.19.0`;
- .NET SDK/runtime remain on accepted .NET 10 baseline.

Final results:
- `npm ci`: PASS;
- TypeScript check: PASS;
- targeted frontend tests: PASS — 10 / 10;
- Vite production build: PASS;
- Vite version: 8.3.0;
- generated assets: PASS;
- FW-IMP-006 static architecture checks: PASS;
- .NET Release build: PASS — 0 warnings, 0 errors;
- existing targeted Foundation tests: PASS — 26 / 26;
- Identity/OpenIddict migration scope/model-drift verification: PASS;
- API smoke/OpenAPI verification: PASS;
- project-reference verification: PASS.

This is targeted Foundation evidence, not real browser E2E.

## Failed verification history

### Run 35728739677
Frontend TypeScript check failed:
- optional `AbortSignal` was explicitly passed as `undefined` under `exactOptionalPropertyTypes`;
- TypeScript had no side-effect CSS module declaration.

Correction:
- build `RequestInit` conditionally when a signal exists;
- add `vite/client` reference declaration.

### Run 35728880322
TypeScript passed, but test bootstrap failed because Node 24 exposes a readonly `navigator` global and the happy-dom harness attempted to overwrite it.

Correction:
- install only required DOM globals with explicit configurable properties;
- do not replace Node's navigator/fetch primitives unnecessarily.

### Run 35728968129
9 / 10 frontend tests passed.
The Grid test used `table.tBodies`, which happy-dom did not expose like a browser collection.

Correction:
- assert semantic body rows through `querySelectorAll("tbody tr")`.

### Run 35729070680
First complete frontend + .NET pipeline success.
This bootstrap run also generated the real npm lockfile.

### Run 35729280921
Reproducible `npm ci` pipeline passed using the committed lockfile.

### Run 35729371845
Final pipeline passed after dialog containment and lookup keyboard-hardening tests.

Failed runs remain part of canonical evidence and are not hidden.

## Dependency direction

Preserved:
- Mars.Web is browser technology only;
- no dependency was introduced from Mars.Domain or Mars.Application into browser code;
- generic API transport does not own domain rules;
- Mars.UI components do not own ERP domain semantics;
- server API remains `/api/v1` authority;
- Desktop/Mobile shell technology was not selected.

## Decisions preserved

- ADR-0001 — .NET 10 LTS;
- ADR-0002 — EF Core 10 + Npgsql;
- ADR-0003 — ASP.NET Core Identity + OpenIddict 7.7.1;
- ADR-0004 — Microsoft.AspNetCore.OpenApi 10.0.12;
- core frontend remains HTML + CSS + TypeScript + ES Modules + Vite;
- Mars.UI remains Mars-owned;
- no React/Vue/Angular/Bootstrap/Tailwind/jQuery.

## UNKNOWN / deferred

Not selected by FW-IMP-006:
- Desktop shell technology;
- Mobile shell technology;
- production secret store;
- production reverse proxy/tunnel;
- production deployment architecture;
- structured logging/metrics stack;
- object/file storage backend;
- optional scheduling library.

## Full Test Day pending

Still deferred:
- real browser E2E;
- full accessibility audit/WCAG assessment;
- Desktop/Mobile E2E;
- full authentication/authorization matrix;
- full PostgreSQL integration;
- concurrency/load;
- backup/restore;
- security regression;
- cross-platform regression.

## Planning metric

Exact master section-8 planning completion remains:
- 9 / 30 = 30.0%.

P2 remains:
- 8 / 8 = 100.0%.

FW-IMP-006 implementation completion does not change these planning metrics.

## Next package

Repository-defined next Foundation package:
- `FW-IMP-007 — Docker/test deployment baseline`.

Gate review:
- no owner decision currently blocks starting the test-deployment baseline;
- production secret-store choice remains deferred;
- production reverse-proxy/tunnel choice remains deferred;
- logging/metrics technology remains deferred;
- test decisions must not be promoted into production decisions.

FW-IMP-007 must begin with test-environment fact verification, including Docker/Compose version and actual deployment/service layout before mutation.
