# MarsOtomasyon — Session Execution and Handoff Protocol

Status: BINDING
Applies to: every planning, coding, database, UI, infrastructure, test, review and read-only session

## 1. Purpose

Every chat must behave like an independent engineering work session that can be understood without prior chat history. Repository state, accepted plans and explicit user instructions are the continuity mechanism.

Each session has exactly one primary work package unless the user explicitly combines tightly-coupled work.

## 2. Session start sequence

Before making architecture, DB, code, UI or integration changes:

1. Verify repository: `ufukunal/MarsOtomasyon`.
2. Verify current `main` HEAD from GitHub.
3. Read:
   - `docs/plan/ai-cmd.md`
   - `docs/ai/README.md`
   - `docs/ai/autocomplete.md`
   - `docs/ai/skill-router.md`
   - `docs/ai/session-execution-protocol.md`
   - `docs/plan/master-project-plan.md`
   - `docs/plan/project-state.yaml`
   - `docs/plan/active-task.yaml`
   - `docs/plan/handoff/current.md`
4. Read every module/skill/DB/code source required by the active work package.
5. Select ACTIVE SKILLS.
6. Produce a CONTEXT RECEIPT before implementation.

## 3. CONTEXT RECEIPT format

```
CONTEXT RECEIPT

Repository:
Branch:
Verified HEAD:
Task ID:
Task:
Module/Phase:

Sources checked:
- exact/path
- exact/path

ACTIVE SKILLS

Primary:
- skill-name — decision responsibility in this task

Reviewers:
- skill-name — risk/review responsibility in this task

Scope:
- allowed work
- expected files
- DB impact
- API impact
- UI impact
- integration impact
- deployment impact

Forbidden assumptions:
- ...

Known decisions:
- ...

UNKNOWN:
- ...

BLOCKED:
- ...
```

A role name without a responsibility statement is insufficient.

## 4. Role assignment rule

The AI must not activate every skill by default. It selects only roles that own a real decision/risk.

For cross-cutting master planning:
Primary:
- mba-business-manager
- software-architect

Reviewers are selected from:
- erp-domain-specialist
- accounting-finance-specialist
- database-architect
- software-developer
- software-test-engineer
- security-specialist
- system-devops-specialist
- ux-ui-specialist
- web-design-specialist
- graphic-design-specialist
- warehouse-operations-shipping-specialist
- production-planning-specialist
- ecommerce-integration-specialist
- statistics-analysis-specialist

For each active role answer internally:
1. What decision/risk does this role own?
2. What exact repository sources did it verify?
3. What assumptions is it forbidden to make?
4. What evidence is required for DONE?

## 5. Work-package execution

Normal order:

1. Verify reality.
2. Read required sources.
3. Compare current state to acceptance criteria.
4. Identify SOURCE / INFERENCE / UNKNOWN / BLOCKED.
5. Make the minimum required repository changes.
6. Run only permitted fast verification.
7. Review with active reviewer roles.
8. Update project state/task/handoff when the work package changes project status.
9. Verify final `main` HEAD.
10. Produce SESSION REPORT.
11. Produce NEXT PROMPT.

Do not defer a safe in-scope step merely because the task is large. Partial work is acceptable only if explicitly reported as partial and the next prompt resumes the exact remaining work.

## 6. SESSION REPORT format

Every final answer begins with a concise report:

```
SESSION REPORT

Task:
Status: COMPLETED | PARTIAL | BLOCKED | READ-ONLY VERIFIED

Repository:
Branch:
Start HEAD:
Final HEAD:

Roles used:
Primary:
- ...
Reviewers:
- ...

What was done:
- ...

Files changed:
- path — purpose

Decisions locked:
- ...

Verification:
- check — PASS/FAIL/NOT RUN

UNKNOWN/BLOCKED:
- ...

Full Test Day pending:
- ...

Next work package:
- ID — exact title
```

Rules:
- Do not say PASS without tool/test evidence.
- Do not hide failed checks.
- Do not expose secrets.
- Planning completion is not implementation completion.

## 7. How to choose the next work package

Priority:
1. explicit current owner instruction,
2. unresolved blocker of current task,
3. `active-task.yaml` next action,
4. `master-project-plan.md` dependency order,
5. backlog.

Never invent a later feature while a dependency is unresolved.

If the user explicitly changes priority, update state/handoff so the repository records the new sequence. Paused work remains paused, not falsely completed.

## 8. NEXT PROMPT generation algorithm

The NEXT PROMPT is a complete new-session bootstrap prompt. It must be generated from the final repository state, not copied blindly from the prior prompt.

Before writing it:
1. verify final main HEAD,
2. identify the exact next task,
3. identify required sources,
4. select primary/reviewer skills for that task,
5. state known decisions inherited from completed work,
6. list open UNKNOWN/BLOCKED items,
7. define allowed/forbidden scope,
8. define exact work order,
9. define measurable acceptance criteria,
10. define fast checks,
11. list Full Test Day items,
12. require final state/handoff updates,
13. require another SESSION REPORT and NEXT PROMPT.

## 9. Mandatory NEXT PROMPT structure

```
NEXT PROMPT — KOPYALA / YAPIŞTIR

MarsOtomasyon projesinde aşağıdaki işi bağımsız yeni bir çalışma oturumu olarak yürüt.

REPOSITORY / GIT
Repository: ufukunal/MarsOtomasyon
Target branch: main
Expected starting HEAD: <verified SHA>
Rules:
- main dışında branch oluşturma
- PR oluşturma
- force push yapma
- başlamadan gerçek HEAD'i tekrar doğrula
- HEAD farklıysa repo gerçeğini esas al ve farkı CONTEXT RECEIPT'te belirt

TASK
ID:
Title:
Phase/Module:
Goal:

MANDATORY SOURCES
Önce eksiksiz oku:
- docs/plan/ai-cmd.md
- docs/ai/README.md
- docs/ai/autocomplete.md
- docs/ai/skill-router.md
- docs/ai/session-execution-protocol.md
- docs/plan/master-project-plan.md
- docs/plan/project-state.yaml
- docs/plan/active-task.yaml
- docs/plan/handoff/current.md
- <task-specific sources>

CONTEXT RECEIPT
Her değişiklikten önce üret:
- Repository
- Branch
- Verified HEAD
- Task
- Module/Phase
- Sources checked
- ACTIVE SKILLS with responsibility
- Scope
- Forbidden assumptions
- Known decisions
- UNKNOWN
- BLOCKED

ACTIVE SKILLS
Primary:
- skill — exact ownership

Reviewers:
- skill — exact review/risk

KNOWN / LOCKED DECISIONS
- ...

GOAL
- exact desired end state

EXECUTION ORDER
1. ...
2. ...
3. ...

SCOPE / ALLOWED PATHS
- ...

DO NOT
- undocumented business rule uydurma
- scope dışı refactor
- duplicate framework/mechanism
- unapproved dependency
- source-of-truth change
- branch/PR/force push
- unauthorized heavy tests
- claim unverified success
- expose credentials/secrets
- ...

ACCEPTANCE CRITERIA
- measurable criterion
- ...

FAST VERIFICATION
- exact quick checks

FULL TEST DAY
Run etmeyeceğin ancak backlog'a taşıyacağın ağır senaryolar:
- ...

STATE/HANDOFF UPDATE
Görev sonucu proje durumunu değiştiriyorsa şunları güncelle:
- docs/plan/project-state.yaml
- docs/plan/active-task.yaml
- docs/plan/handoff/current.md
- docs/plan/tasks/*
- related module plan/status

FINAL OUTPUT
SESSION REPORT formatında:
- status
- start/final HEAD
- roles
- what changed
- files changed
- decisions
- verification
- UNKNOWN/BLOCKED
- Full Test Day pending
- exact next work package

En sonda final repo durumundan yeniden üretilmiş ayrıntılı:
NEXT PROMPT — KOPYALA / YAPIŞTIR
bölümü bulunmak zorunda.
```

## 10. Planning-session specialization

When the task is planning only:
- do not create application code,
- do not create SQL schema unless the active planning phase explicitly permits logical/schema design,
- distinguish target architecture from implemented reality,
- use UNKNOWN/BLOCKED instead of inventing policy,
- V38 is a reference, not automatic requirement,
- produce implementable contracts rather than vague prose.

## 11. Implementation-session specialization

When implementation is permitted:
- read existing code before designing replacements,
- use accepted plan/DB/API/UI contracts,
- make smallest correct change,
- add no package without dependency review,
- compile/build,
- run targeted tests,
- use test deployment only when task requires it,
- never claim production readiness from test smoke.

## 12. Security and credentials

- Never echo passwords, tokens or API keys in final reports or workflow logs.
- Production secrets are never committed.
- Test-only repository credential exception remains limited to the explicit owner-approved policy.
- A prompt must refer to credential source/path, not copy the credential value.

## 13. Completion gate

A session is not complete unless:
- final repo state is known,
- report is produced,
- changed files are identified,
- verification is truthful,
- UNKNOWN/BLOCKED is explicit,
- next work package is derived from repository plan,
- a standalone NEXT PROMPT is produced.
