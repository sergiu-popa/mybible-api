# Audit — MBA-020 Symfony Cutover

**Auditor:** auditor
**Commit under audit:** `f031b86` (QA PASSED → qa-passed)
**Branch:** `mba-020`
**Verdict:** `PASS`

## Scope

Final holistic pass over the cutover deliverables: forced-logout job
+ `security_events` audit table, artisan command, smoke suite +
`make smoke`, parity checklist, runbook, decommission plan, and
RO/EN comms drafts. Operational/infra-sensitive story, so gave
extra scrutiny to idempotency, secret handling, and atomicity.

## Issue table

| # | Issue | Location | Severity | Status | Resolution |
|---|---|---|---|---|---|
| 1 | `.env.smoke` documented as gitignored in `runbook.md:119-125` but absent from `.gitignore`. Ops would drop a file with prod `SMOKE_API_KEY` + `SMOKE_USER_PASSWORD` at the repo root; nothing blocks a commit. Review flagged as acknowledged-non-blocker, but the risk is a real credential leak for a trivial fix. | `.gitignore` | Warning | Fixed | Added `.env.smoke` line to `.gitignore` so the runbook's "gitignored" promise is load-bearing, not documentation. |
| 2 | `.env.example` has no `SMOKE_*` placeholder block. Ops assembling `.env.smoke` for the first time has to cross-reference `runbook.md` §4 for the exact variable names, inviting drift. | `.env.example` | Suggestion | Fixed | Added commented `SMOKE_API_KEY`, `SMOKE_USER_EMAIL`, `SMOKE_USER_PASSWORD` with a note pointing to the separate `.env.smoke` file. |
| 3 | `SecurityEvent` has no factory. Boost rule recommends factories for new models. | `database/factories/` | Suggestion | Skipped — write-path goes exclusively through `InvalidateAllSymfonySessionsAction` (idempotency-guarded). Every existing test creates rows via the action; a factory would circumvent the business rule and has zero callers. | — |
| 4 | `InvalidateSymfonySessionsCommand.php:78` casts `$result['event_id']` back to `int` though the non-dry-run branch can never return null. | `app/Application/Commands/InvalidateSymfonySessionsCommand.php:78` | Suggestion | Skipped — cosmetic, review already flagged; typing the return shape as a discriminated union would churn the action for no behaviour change. | — |
| 5 | `scripts/warmup-cache.sh` referenced by `runbook.md:38-41` pre-flight but not in the tree. | `.agent-workflow/stories/MBA-020-symfony-cutover/runbook.md:38` | Suggestion | Deferred to ops — runbook explicitly marks it "to be authored by ops" and the pre-flight checklist hard-fails at T-1h if it's still missing, so the gate is in place. Follow-up ticket owner: ops. | — |
| 6 | Idempotency guard runs outside `DB::transaction` — two concurrent operators could both pass the guard. | `app/Domain/Security/Actions/InvalidateAllSymfonySessionsAction.php:38` | Warning | Skipped — review acknowledged with operational justification: the runbook prescribes a single operator executing once from one host. Concurrent invocation is not an operational scenario; sequential double-runs are pinned by tests. Schema churn (unique index on `event`) not worth the one-shot lifetime. | — |
| 7 | `SecurityEvent.affected_count` is nullable in schema + PHPDoc but every existing caller passes an int. | `database/migrations/2026_04_23_223810_create_security_events_table.php:28` | Warning | Skipped — plan explicitly reserves nullability for future event types (emergency revocation, ad-hoc audit rows) where no count applies. Forward-looking shape is correct. | — |

## Dimensions reviewed

- **Architecture.** Domain layout matches project convention:
  `Domain/Security/{Models,Actions,Exceptions}`,
  `Application/Commands/`. Action is invokable-style, returns a
  typed shape. Exception is a proper domain exception registered in
  `bootstrap/app.php:99-101` with 409 render. No leakage of framework
  concerns into the domain. Pass.
- **Code quality.** `final class`, `declare(strict_types=1)`,
  explicit return types, named arguments used where it clarifies
  call sites. No `else` branches. Magic-string-free (event slug is
  a `SecurityEvent::EVENT_*` constant). `DB::transaction` wraps the
  mutation + audit write. Pass.
- **API Design.** N/A — no HTTP endpoints introduced. The model is
  not exposed over the wire, so no Resource, no Form Request. The
  one exception rendered by the handler (`SymfonyCutoverAlreadyExecutedException`
  → 409) matches the repo's JSON error envelope. Pass.
- **Security.** The main finding (issue #1) was `.env.smoke` not in
  `.gitignore` — fixed. Secret handling otherwise correct:
  smoke-suite creds read from env, not committed; artisan command
  does not echo secrets; the forced-logout deletes all pre-cutover
  tokens via a parameterised where-clause (no string interpolation).
  No auth-bypass surface. Pass.
- **Performance.** `InvalidateAllSymfonySessionsAction` issues one
  count + one bulk delete + one insert inside a transaction. No
  N+1. Index on `security_events.event` means the idempotency
  lookup is O(1). Pass.
- **Test coverage.** 8 tests directly target this story (4 unit + 4
  feature) covering: happy-path delete + audit, idempotency double-
  run, `<` vs `<=` boundary (pre-existing correctness bug
  preventer), dry-run no-mutation, non-dry-run mutation, second-
  invocation failure exit, invalid `--cutover-at` input. Smoke
  suite is infra not assertion. Default suite: 628 pass, 1936
  assertions. Pass.

## Commands run

- `docker exec mybible-api-app composer lint` → PASS, 471 files.
- `docker exec mybible-api-app composer stan` → 0 errors, 451 files.
- `docker exec … php artisan migrate --force` (test DB) → already
  migrated.
- `docker exec mybible-api-app php artisan test --compact` →
  **628 passed, 1936 assertions** in 10.10s. Zero failures, zero
  errors. Smoke suite correctly excluded.

## Deferred to ops (accepted)

Tasks 1 (prod reverse-proxy discovery), 2 (client user-agent
enumeration), and 17 (staging dry-run on prod snapshot) require
production-infra access and remain deferred — same deferral
rationale the plan, review, and QA all signed off on. Templates
are in place (`runbook.md` §2, `decommission.md` §"Active client
enumeration", `runbook.md` Appendix A).

## Outcome

All Critical and Warning findings are resolved, skipped with
operational justification, or deferred with a pointer. Lint, stan,
and the full default test suite are green. Audit fixes are minor
(gitignore + example env doc) and do not alter behaviour.

Advancing to `done`.
