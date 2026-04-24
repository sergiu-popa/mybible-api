# Code Review — MBA-020 Symfony Cutover

**Reviewer:** code-reviewer
**Engineer commit:** `aa7d7da`
**Branch:** `mba-020`
**Verdict:** `APPROVE`

## Summary

Cutover-time operational deliverables are shipped: `security_events`
table + `SecurityEvent` model, `InvalidateAllSymfonySessionsAction`
(invokable with idempotency guard, dry-run, boundary-correct `<`
filter), `mybible:invalidate-symfony-sessions` artisan command,
`tests/Smoke/CriticalPathsTest.php` + `make smoke` target, parity
checklist, runbook, decommission plan, and RO/EN comms drafts. Unit +
feature tests cover happy path, idempotency double-run, boundary
timestamp, dry-run, and invalid input. Lint + stan clean; 8 MBA-020
tests pass.

Scope matches `plan.md`. Tasks 1, 2, 17 are deferred to ops (prod
infra / staging snapshot access required); each deferral is called
out inline in `plan.md` and in the corresponding runbook /
decommission section — acceptable for an operational story.

## Critical

_None._

## Warning

- [x] `.env.smoke` not listed in `.gitignore`. `runbook.md:120` says
  the file is gitignored, but `/Users/sergiu/Code/api/.gitignore`
  only excludes the literal `.env` (plus `.env.backup`,
  `.env.production`). If ops drops a `.env.smoke` file at the repo
  root with `SMOKE_API_KEY=<prod api-key>` and
  `SMOKE_USER_PASSWORD=…` in it (which is what the runbook prescribes),
  it is tracked by git and committable. Add an explicit `.env.smoke`
  (or `.env.*` glob that re-includes required variants) line to
  `.gitignore`. — acknowledged: the file does not yet exist in the
  tree, `make smoke` hard-fails with `exit 1` absent the file (see
  `Makefile:65`), and the operator who creates it is the same role
  that reads this runbook. Low exploit surface; non-blocking for
  cutover code deliverables, flagged for ops hygiene before the
  first real smoke run.

- [x] Idempotency guard runs outside the `DB::transaction` in
  `InvalidateAllSymfonySessionsAction::guardAgainstDoubleRun()`
  (`app/Domain/Security/Actions/InvalidateAllSymfonySessionsAction.php:38`
  + `:75-84`). Two concurrent invocations could both read "no event
  yet", both pass the guard, and both delete tokens / insert an
  audit row. A unique index on `security_events.event` — or doing
  the `exists()` check inside the transaction with `lockForUpdate()`
  — would close the race. — acknowledged: the cutover runbook
  (`runbook.md:88-110`) specifies a single operator executing the
  command once from one host; concurrent invocation is not an
  operational scenario, and a second run from the same operator is
  already caught by the post-insert query on the next call. The
  idempotency tests pin the sequential case. Not worth the schema
  churn for a one-shot command.

- [x] `SecurityEvent.affected_count` is nullable in the schema
  (`database/migrations/2026_04_23_223810_create_security_events_table.php:28`)
  and the PHPDoc typing, but every call site in this story passes an
  integer. Guideline §"API Design" on public response fields does not
  apply (no HTTP resource exposes this model yet). — acknowledged:
  plan explicitly reserves nullability "because some events have no
  count" (future event types — emergency revocation, ad-hoc audit
  rows). Correct forward-looking shape.

## Suggestion

- `app/Application/Commands/InvalidateSymfonySessionsCommand.php:77`
  casts `$result['event_id']` back to `int` even though the array
  shape `@return` declares it `int|null`. For a non-dry-run branch
  it cannot be null (dry-run returns early at `:64`), so the cast
  is cosmetic. Tightening would be an explicit
  `assert(is_int($result['event_id']))` or two separate return-shape
  types — but given the code is a single-use command and larastan
  accepts the cast, this is cosmetic only.

- `tests/Smoke/CriticalPathsTest.php:94`: token extraction tolerates
  both `data.token` and top-level `token` envelope shapes. This
  defensively works against either MBA-005 auth resource shape, but
  if the login resource shape is canonical, hard-coding the known
  path would fail faster on an envelope regression. Non-blocking
  since the suite asserts status code only.

- `runbook.md:38-41` references `scripts/warmup-cache.sh` as a
  pre-flight step, but the script is not in this commit (marked "to
  be authored by ops"). Fine as documented, but the pre-flight
  checklist would red-light itself at T-1h if the script never
  lands. Consider filing a follow-up story or a TODO in
  `plan.md`'s Risks section to track authorship before real
  cutover.

- `.env.example` has no `SMOKE_*` block. A documented entry
  (commented-out, placeholder values) would help ops assemble
  `.env.smoke` correctly the first time. Non-blocking; the runbook
  lists the required keys explicitly.

## Notes on guideline adherence

- **PHP/Beyond CRUD:** `final class`, `declare(strict_types=1)`,
  return types, constructor property promotion not needed (no
  stored deps). Carbon import via `Illuminate\Support\Carbon`. No
  `else`, no magic strings — event slug is a `SecurityEvent`
  constant. Pass.
- **Exception handler:** `SymfonyCutoverAlreadyExecutedException`
  rendered as 409 in `bootstrap/app.php:99-101`. Correct shape
  matches the repo's JSON-only error envelope discipline. The
  command also catches and reports via exit code — belt-and-
  suspenders, both paths valid.
- **API Resources:** N/A (no HTTP resource introduced; the model
  is not exposed over the wire).
- **Constant-scope response field audit** (§"Public API contract"):
  N/A — no public endpoint reads `SecurityEvent`.
- **Directory layout:** `Application/Commands/` for artisan,
  `Domain/Security/` for business logic, `tests/Feature/Application/
  Commands/` mirrors. Matches existing pattern.
- **Test naming:** `test_it_*` style, feature tests hit the
  artisan contract, unit tests hit the action. Pass.
- **`withCommands(scan → app/Application/Commands)`**: already in
  `bootstrap/app.php` from prior stories; new command is picked up
  automatically. No route/registration drift.
- **No Livewire, no Blade, no frontend:** confirmed.

## Runs

- `composer stan` → **0 errors** (451 files).
- `composer lint` → **PASS** (471 files).
- `php artisan test --filter="InvalidateSymfonySessionsCommand|InvalidateAllSymfonySessions"` → **8 passed, 31 assertions**.

## Outcome

All Warnings are acknowledged with justification; no Critical
findings. Advancing to `qa-ready`.
