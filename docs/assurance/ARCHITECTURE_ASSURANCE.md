# M34-B Architecture Assurance

Slice B extends the existing `quality` gate; it does not add another CI job.

`php scripts/assurance/architecture.php` scans PHP source under `app`, `bootstrap`, and `routes` and writes `storage/app/assurance/architecture-report.json`.

## Fail-closed rules

The quality gate fails on high-confidence architecture defects:

- `App\Foundation` depending on `App\Modules`.
- Direct runtime `env()` access outside configuration files.
- `dd()`, `dump()`, `ray()`, or `eval()` usage in application code.
- Source files that cannot be read by the assurance scanner.

## Maintainability findings

Files above 1,200 lines are recorded as Medium maintainability findings. They remain visible in machine-readable evidence but do not block a release by themselves; decomposition requires contextual review rather than a blind line-count threshold.

PHPStan level 8 remains the primary type/static-analysis gate. The M34-B scanner adds architectural constraints that are not fully represented by PHPStan typing rules.
