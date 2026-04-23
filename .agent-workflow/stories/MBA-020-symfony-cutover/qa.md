# QA — MBA-020 Symfony Cutover

**QA agent:** qa
**Commit under test:** `3ffacb5`
**Branch:** `mba-020`
**Verdict:** `QA PASSED`

## Test run

- `make test` → **628 passed, 1936 assertions**, 10.47s. Zero failures,
  zero errors, zero skipped.
- `make test filter="InvalidateSymfonySessionsCommand|InvalidateAllSymfonySessions"`
  → **8 passed, 31 assertions** (4 unit + 4 feature, covering happy
  path, idempotency double-run, `<` boundary, dry-run, non-dry-run
  mutation, second-invocation failure exit, invalid `--cutover-at`).
- `make lint` → PASS (471 files).
- `make stan` → 0 errors (451 files).
- Smoke suite (`tests/Smoke/CriticalPathsTest.php`) correctly excluded
  from the default run via `phpunit.xml` `<exclude><group>smoke`.

## AC coverage

| AC | Deliverable | Evidence |
|---|---|---|
| 1. Parity for MBA-005..MBA-019 | Counterpart suites green | 628 default tests pass; smoke excluded by design |
| 2. Parity checklist | `parity-checklist.md` | 94 lines, one row per endpoint with Symfony URL, Laravel URL, story, test ref, sign-off |
| 3. Forced-logout job | `InvalidateAllSymfonySessionsAction` + command | Deletes `personal_access_tokens` where `created_at < cutoverAt`, inside `DB::transaction`. Does not touch Symfony JWT secret |
| 4. `security_events` audit table | Migration `2026_04_23_223810_create_security_events_table.php` + `SecurityEvent` model | Fields id, event, reason, affected_count, metadata, occurred_at, created_at. Indexes on event + occurred_at. Written in same transaction as token deletion |
| 5. Runbook | `runbook.md` (212 lines) | Numbered pre-flight, flip command (Traefik + nginx variants), smoke invocation, rollback, observability appendix |
| 6. DNS/proxy coordination | Plan §Cutover sequence + runbook §1 | T-14d mobile release, T-3d TTL lower, T-1d comms |
| 7. Email comms | `communications/email-T-1d.md` | RO + EN drafts, subject, sender, scheduled send |
| 8. In-app banner | Runbook T+0..T+7d entry | Documented; dependent on client release per story |
| 9. Decommission plan | `decommission.md` (134 lines) | T+30d archive, data-ownership (Laravel sole writer), container shutdown, AWS `async-aws/s3` key revocation |
| 10. Observability | `runbook.md` §5 appendix | Login success rate, 5xx rate, token issuance, replication lag; alarm `5xx > 5× baseline for 5m` |
| 11. Smoke suite + `make smoke` | `tests/Smoke/CriticalPathsTest.php` + `Makefile:64-70` | `@group smoke`, 5 endpoints, `.env.smoke` gate on make target |

## Edge cases probed

- **Boundary (`<` vs `<=`)**: unit test `it does not revoke tokens
  created exactly at the cutover timestamp` pins the inclusive-
  boundary bug. Pass.
- **Idempotency**: unit + feature tests confirm second run throws
  `SymfonyCutoverAlreadyExecutedException` and artisan exits with
  non-zero code. No duplicate audit row written.
- **Dry-run**: asserts zero mutation (token count + event count
  unchanged) while reporting the affected count.
- **Invalid input**: `--cutover-at=not-a-date` returns invalid exit
  code, no mutation.
- **Transaction atomicity**: deletion + audit insert wrapped in
  `DB::transaction` — partial failure leaves the DB clean.
- **Smoke suite accidental inclusion**: verified `phpunit.xml`
  `<groups><exclude><group>smoke` blocks default run; 628 total test
  count matches prior suite size (no smoke contamination).

## Regression check

- Full default suite green: 628/628. No MBA-005..MBA-019 regressions.
- `SecurityEvent` is append-only (no `UPDATED_AT`), does not intersect
  existing domains — zero cross-domain risk.
- Migration adds a new table only; no schema change to existing
  tables.

## Review follow-through

All `review.md` Warnings are acknowledged with documented
justification (operational single-operator run, forward-looking
nullable field, runbook-specified gitignore hygiene deferred to ops).
No Critical findings were raised. No residual action needed for QA
sign-off.

## Deferred tasks (acceptable)

Tasks 1 (prod reverse-proxy discovery), 2 (client User-Agent grep),
and 17 (staging dry-run) require production infra / snapshot access
and are explicitly deferred to ops with templates in place. This is
expected for an operational cutover story and does not block QA.

## Outcome

All acceptance criteria have passing tests or documented deliverables.
Lint, static analysis, and the full default test suite are green. No
Critical review items remain. Advancing to `qa-passed`.
