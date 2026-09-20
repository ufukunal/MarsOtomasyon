# Architecture and Business Decision Records

## Purpose
A decision belongs here when changing it later would materially affect architecture, data, workflows or multiple modules.

## Decision record format
Create files as:

```
ADR-0001-short-title.md
```

Use:

```
# ADR-XXXX — Title

Status: Proposed | Accepted | Superseded | Rejected
Date:
Owners:

## Context
What problem or constraint requires a durable decision?

## Decision
What exactly is decided?

## Consequences
Positive and negative consequences.

## Alternatives considered
List real alternatives, not invented strawmen.

## Affected areas
- modules
- database
- API
- UI
- deployment
- integrations

## Revisit conditions
What evidence or requirement would justify changing this decision?

## Sources
Exact repository paths or external documentation.
```

## Rule
Chat statements are not durable architecture decisions until captured in repository documentation when the decision materially affects future implementation.
