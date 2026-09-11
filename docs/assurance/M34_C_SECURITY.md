# M34 C — SAST, secrets, and supply-chain assurance

Slice C extends the existing `security` runner gate; it does not create another CI job.

## Blocking controls

- Composer advisory audit
- npm advisory audit at high severity and above
- tracked-secret scan
- application SAST for dynamic `eval()`, direct `unserialize()`, and disabled TLS peer verification
- required `composer.lock` and `package-lock.json`
- dependency constraints that reject wildcards, development branches, unpinned remote URLs, and `latest`

## Review findings

Process-execution primitives and raw-SQL primitives are recorded as review findings in `storage/app/assurance/security-report.json`. They are not blindly blocked because the application contains operational/admin code where those primitives can be legitimate when inputs are constrained.

The security gate remains fail-closed for high-confidence blockers. Validation still uses exactly three real self-hosted Foundation gates: `security`, `quality`, and `browser-smoke`.
