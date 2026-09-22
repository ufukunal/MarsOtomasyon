# Active Tasks

## FW-IMP-006 — Mars.Web + Mars.UI foundation
**Status:** READY — IMPLEMENTATION NOT STARTED

Predecessor:
- FW-IMP-005 — COMPLETED
- canonical evidence: `docs/plan/01-foundation/fw-imp-005-implementation.md`
- tested implementation commit: `22f31b087f887270bb43f4a39038d7c9a9e07b87`
- build/test/migration/API/OpenAPI evidence: GitHub Actions run `35722169157`

Repository-defined FW-IMP-006 scope:
- Vite/TypeScript;
- web shell;
- router boundary;
- API client boundary;
- design tokens;
- Mars.UI Button/Field/Dialog/Tabs/Lookup/Grid baseline;
- targeted frontend build/component/static verification.

Inherited constraints:
- core frontend remains HTML + CSS + TypeScript + ES Modules + Vite;
- Mars.UI remains the project-owned component system;
- do not add React, Vue, Angular, Bootstrap, Tailwind or jQuery to the core frontend;
- web auth must preserve secure HttpOnly cookie/session semantics where appropriate;
- localStorage token is not the baseline;
- no ERP business screen/rule merely to prove the framework.

Decision-gate review:
- no FW-IMP-006-specific unresolved owner technology gate is currently identified;
- Desktop/Mobile shell technologies remain later P10 decisions;
- deployment/production ingress decisions remain later work.

Do not expand into:
- domain module implementation;
- Desktop/Mobile shell technology selection;
- Docker/test deployment baseline;
- production secret store;
- production reverse proxy/tunnel;
- unrelated logging/metrics, file-storage or scheduling decisions.

Exact planning progress:
- master: 9 / 30 = 30.0%
- P2: 8 / 8 = 100.0%

Heavy tests remain Full Test Day only.
