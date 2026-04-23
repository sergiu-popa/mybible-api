# Story: MBA-020-symfony-cutover

## Title
Symfony cutover — forced logout, traffic flip, decommission plan

## Status
`qa-ready`

## Description
Final migration story. Switch production traffic from the Symfony API
to the Laravel API, invalidate every existing Symfony-issued JWT so
mobile clients are forced to re-authenticate against Sanctum, and
publish a decommission runbook for the Symfony codebase.

This story is operational as much as code. Most of the work is
coordination (DNS / reverse-proxy flip, client release coordination,
monitoring), not new PHP.

## Acceptance Criteria

### Feature parity gate
1. Every Symfony endpoint in the MBA-005–MBA-019 migration list has a
   Laravel counterpart shipped and covered by feature tests.
2. A parity checklist document lives at `.agent-workflow/stories/
   MBA-020-symfony-cutover/parity-checklist.md` — one row per endpoint,
   Symfony URL, Laravel URL, test ref, sign-off.

### Forced logout
3. A one-shot job `InvalidateAllSymfonySessions` runs at cutover and:
   - Clears any Sanctum tokens issued before the cutover timestamp
     (defensive — anything issued during side-by-side testing should
     not persist).
   - Does NOT touch the Symfony JWT secret rotation (that is a
     Symfony-side concern; document it).
4. A migration adds a `security_events` table (one row written when
   the forced logout runs — timestamp, reason, count) so we have an
   audit trail.

### Traffic switch
5. Runbook (`.agent-workflow/stories/MBA-020-symfony-cutover/
   runbook.md`) documents the exact steps to flip the reverse proxy
   from Symfony to Laravel. Include:
   - Pre-flight checklist.
   - Flip command (nginx / Caddy / whatever is in use).
   - Smoke test set (curl commands against 5 critical Laravel
     endpoints post-flip).
   - Rollback procedure (flip back).
6. DNS / proxy change is coordinated with:
   - Mobile client team: a new app release targeting
     `/api/v1/auth/login` must be available in stores 24h before flip.
   - Web clients that depend on the Symfony API (if any) — confirm the
     list during architecture.

### Client communication
7. Email / push notification drafted to all active users the day
   before cutover: "We're updating our systems; you will be signed
   out and need to log in again tomorrow. Your data is safe."
8. In-app banner on the mobile client (dependent on client release)
   during the 7 days post-cutover explaining the re-login requirement
   in case users missed the email.

### Symfony decommission plan
9. Decommission document
   (`.agent-workflow/stories/MBA-020-symfony-cutover/decommission.md`)
   lists:
   - Date (set at cutover + 30 days) after which the Symfony code is
     archived.
   - Data ownership: the Laravel API is the single writer of the
     shared DB from cutover onward. The Symfony repo is frozen.
   - Infrastructure teardown steps: stop Symfony containers, archive
     the repo, revoke AWS keys scoped to Symfony's `async-aws/s3`
     client.

### Post-cutover observability
10. A dashboard panel tracks:
    - `/api/v1/auth/login` success rate (should spike then normalize).
    - 4xx/5xx rate across all Laravel endpoints.
    - Token issuance rate.
    Alarms fire if the 5xx rate exceeds baseline by 5× for more than
    5 minutes — fast rollback signal.

### Tests
11. Automated smoke test suite callable post-flip (`make smoke`)
    — hits the 5 critical endpoints with api-key credentials and
    asserts `200`. This is new test infrastructure, not just a test.

## Scope

### In Scope
- `InvalidateAllSymfonySessions` job + migration for
  `security_events`.
- Parity checklist document.
- Runbook, communication templates, decommission plan.
- Smoke test suite.

### Out of Scope
- Schema changes — all schema reconciliation happens in MBA-005.
- Individual domain migrations — those are their own stories.
- Archiving of the Symfony repo itself (run the steps post-cutover;
  the document is the deliverable here).

## Technical Notes

### Smoke test shape
```
tests/Smoke/CriticalPathsTest.php
```
Marked `@group smoke`, excluded from the default `make test` run, and
runnable via `make smoke` against a target URL (env-configurable).
Assertions are status code only — this is a "is it up" test, not a
correctness test (correctness is the unit+feature suites).

### Forced logout decision
Decision in memory: option (b) from the clarification round —
forced global logout. The job runs once, invalidates all Sanctum
tokens issued pre-cutover, then self-deletes (via a migration that
drops the job trigger, or just a command run once).

### Runbook discipline
Runbook is numbered, imperative, and tested by a dry-run. Do NOT
treat it as optional. The dry-run of the runbook on staging is a
pre-flight gate.

## Dependencies
- **MBA-005 through MBA-019** (all domain migrations must be shipped).
- External: mobile client release, web client sign-off.

## Open Questions for Architect
1. **Reverse proxy choice.** What is currently in front of the
   Symfony app? Inspect infra config before writing the runbook.
2. **Cutover window.** Length and time of day. Recommend a low-
   traffic hour on a weekday with engineers on standby.
3. **Rollback cliff.** If we flip back, all Laravel-issued Sanctum
   tokens persist but Symfony doesn't honor them. Users get logged
   out twice — once forward, once back. Document this risk; the
   acceptable mitigation is "don't roll back unless you have to."
4. **Are there web clients at all?** The team mentioned mobile and
   admin; confirm no third-party integrations are on Symfony API.
