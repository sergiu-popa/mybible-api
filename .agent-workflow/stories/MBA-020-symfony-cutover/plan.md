# Plan: MBA-020-symfony-cutover

## Approach

This is an operational story, not a feature. The Laravel API ships a one-shot `InvalidateAllSymfonySessions` job + `security_events` audit table, a reverse-proxy flip runbook, a parity checklist, a smoke test suite (`make smoke`) and a decommission plan. Code deliverables are thin; the bulk of the story is sequencing, documentation, and reversibility. Plan assumes all domain stories (MBA-005..MBA-019) are `ready` and deployed to staging before this story enters execution — cutover cannot start with any domain `draft`.

## Open questions — resolutions

1. **Reverse proxy.** Dev compose uses **Traefik v2.11** (`docker-compose.yml`) routing `api.mybible.local` → `mybible-api-app:8080`. Production likely uses Traefik/nginx fronting the Symfony container; flip is a label/vhost swap, not a code change. Architect confirms actual prod proxy during task 1 discovery — runbook is authored against the discovered value, not the dev default.
2. **Cutover window.** Recommendation: a Tuesday or Wednesday 03:00–05:00 Europe/Bucharest (RO user base). 2h window: 0:00–30 pre-flight, 30–45 flip, 45–90 smoke + observation, 90–120 rollback buffer. Engineers on standby for 4h post-flip.
3. **Rollback cliff.** Documented as a known asymmetry: rolling back forces a *second* forced logout (Laravel-issued Sanctum tokens don't work against Symfony). Mitigation: rollback is triggered only if 5xx > 5× baseline for 5+ minutes or a P1 correctness bug surfaces. Otherwise ride through the spike.
4. **Web / third-party clients.** Confirmed during task 2 discovery (grep Symfony access logs for distinct User-Agents over the prior 30 days). Expected set: mobile app, admin (excluded from migration), public site. If any third-party appears, it gets a 30-day deprecation notice via email; if no response, proceed regardless (URLs break at cutover by design).

## Cutover sequence

T-30d: parity checklist filled; staging stands up Laravel pointing at a snapshot of prod DB.
T-14d: mobile client release submitted to stores (iOS review latency).
T-7d:  runbook dry-run executed on staging. Any failure resets the clock.
T-3d:  DNS TTL for the API hostname lowered to 60s (fast flip / fast rollback).
T-1d:  email blast + push notification sent ("we are updating; re-login tomorrow").
T-1h:  pre-flight checklist begins (health checks green, DB replication lag = 0, queue drained, backup snapshot taken).
T-0:   reverse-proxy routes `/api/*` from Symfony upstream → Laravel upstream. Symfony kept running (hot-standby) for 24h in case of rollback.
T+0–15m: `InvalidateAllSymfonySessions` job runs, writes `security_events` row, revokes any Sanctum tokens issued before the cutover timestamp.
T+15m:   smoke test suite runs (`make smoke TARGET_URL=…`). Any 4xx/5xx fails the cutover.
T+1h:    observability dashboard reviewed. Go/No-Go on keeping the switch.
T+24h:   Symfony upstream powered off (still preserved, not destroyed).
T+30d:   Symfony repo archived, AWS keys for Symfony's `async-aws/s3` client revoked.

## Parity checks

- `parity-checklist.md`: table with columns `Symfony URL`, `Laravel URL`, `Story`, `Feature test path`, `Sign-off`. One row per endpoint across MBA-005..MBA-019. Sign-off is a checkbox toggled during pre-flight.
- Automated parity diff script (shell, lives at `scripts/parity-diff.sh`): hits N endpoints on Symfony + Laravel with identical inputs, normalizes JSON (sort keys, strip timestamps), diffs. Expected diffs documented (pagination envelope, error shape). Unexpected diffs block cutover.
- DB divergence check: row counts of `users`, `bible`, `verse`, `note`, `favorite`, `devotional`, `hymnal_song`, `collection`, `reading_plan_subscription` on prod vs. staging after Laravel-only write test. Must match.

## Rollback plan

- **Trigger conditions:** 5xx error rate > 5× baseline for 5 consecutive minutes, `/api/v1/auth/login` success rate < 80% for 5 minutes, any P1 data-corruption report from on-call.
- **Procedure:** reverse-proxy flips upstream back to Symfony. DNS unchanged (same host). Forced-logout job already ran — re-issued Sanctum tokens are now orphaned; Symfony users will hit login again. Operator posts in #incidents with the rollback timestamp.
- **Post-rollback:** incident review within 24h; root-cause fix; re-schedule cutover no sooner than T+7d to allow mobile/client confidence to recover.
- **Known asymmetry:** rollback forces a second user logout. Acceptable vs. data risk.

## Runbook (document shape)

Authored at `.agent-workflow/stories/MBA-020-symfony-cutover/runbook.md`. Contents:

1. Pre-flight checklist (numbered, imperative, all must pass).
2. Flip command (exact proxy config change — Traefik label swap or nginx `proxy_pass` edit).
3. Forced-logout trigger (`php artisan mybible:invalidate-symfony-sessions`).
4. Smoke test invocation (`make smoke TARGET_URL=https://api.mybible.eu`).
5. Observation protocol (dashboard links, alarm thresholds, go/no-go decision point at T+1h).
6. Rollback procedure (flip proxy back; do NOT re-issue invalidated tokens).
7. Post-cutover cleanup (T+24h power-off, T+30d archive).

Each step has a named owner and a pass/fail output. Runbook is versioned in git; a dry-run on staging is a mandatory gate before real cutover.

## Domain layout

```
app/
├── Domain/
│   └── Security/
│       ├── Models/SecurityEvent.php                    # audit row
│       └── Actions/InvalidateAllSymfonySessionsAction.php
└── Application/
    └── Commands/InvalidateSymfonySessionsCommand.php   # artisan wrapper

database/migrations/
└── 2026_*_create_security_events_table.php

tests/Smoke/
└── CriticalPathsTest.php                               # @group smoke

scripts/
└── parity-diff.sh                                      # ad-hoc, not CI

.agent-workflow/stories/MBA-020-symfony-cutover/
├── parity-checklist.md
├── runbook.md
├── decommission.md
└── communications/
    ├── email-T-1d.md
    └── push-T-1d.md
```

## Key types

| Type | Role |
|---|---|
| `SecurityEvent` | Eloquent model over `security_events`. Fields: `id`, `event` (string, e.g. `symfony_cutover_forced_logout`), `reason` (string), `affected_count` (int, nullable), `metadata` (json, nullable), `occurred_at` (timestamp), `created_at`. No soft deletes. |
| `InvalidateAllSymfonySessionsAction` | Invokable. Deletes all rows from `personal_access_tokens` with `created_at < $cutoverAt`, then writes one `SecurityEvent` row. Idempotent guard: if a `symfony_cutover_forced_logout` event already exists, abort with a descriptive exception instead of double-running. |
| `InvalidateSymfonySessionsCommand` | Artisan command `mybible:invalidate-symfony-sessions`. Arguments: `--cutover-at=` (ISO-8601, defaults to `now()`). Prints affected-token count and the written `SecurityEvent` id. |
| `tests/Smoke/CriticalPathsTest.php` | PHPUnit feature test, `@group smoke`, excluded from default suite by `phpunit.xml` `<groups><exclude><group>smoke</group></exclude></groups>`. Uses api-key credentials from env. Hits 5 endpoints, asserts 200. No correctness assertions. |
| `make smoke` | Makefile target: `docker exec … php artisan test --group=smoke --compact` parameterised by `TARGET_URL`. |

## Critical endpoints for the smoke set

Selected for coverage breadth, not feature depth:

1. `GET /up` — framework health.
2. `GET /api/v1/bible-versions?language=ro` — read, cached, api-key.
3. `GET /api/v1/books?language=ro` — read, middleware-resolved language.
4. `POST /api/v1/auth/login` — write, token issuance (uses a dedicated smoke-test user).
5. `GET /api/v1/auth/me` — bearer-authenticated read (token from step 4).

A sixth endpoint (`GET /api/v1/verses/daily`) is added if MBA-008 ships in time; otherwise swapped for `GET /api/v1/reading-plans`.

## Tasks

- [ ] 1. Inspect production infrastructure (SSH to host or ask ops) and record the actual reverse-proxy software + config-file path in `runbook.md` §2. Do not use the dev Traefik assumption without confirmation.
- [ ] 2. Enumerate active Symfony clients by grepping the prior 30 days of access logs for distinct User-Agents; record in `decommission.md` and confirm the mobile + admin + public-site set (flag any unexpected third-party).
- [ ] 3. Create `App\Domain\Security\Models\SecurityEvent` + the `security_events` migration (id, event, reason, affected_count nullable, metadata json nullable, occurred_at, created_at; indexes on `event` and `occurred_at`).
- [ ] 4. Create `App\Domain\Security\Actions\InvalidateAllSymfonySessionsAction` as an invokable that deletes pre-cutover `personal_access_tokens` rows and writes a `SecurityEvent`. Idempotency guard: second run throws a typed exception rather than firing twice.
- [ ] 5. Register the typed exception from task 4 in `bootstrap/app.php` so a double-run returns a 409-shaped console exit, not a stack trace.
- [ ] 6. Create `InvalidateSymfonySessionsCommand` (`mybible:invalidate-symfony-sessions`) with `--cutover-at=` and `--dry-run` flags. `--dry-run` prints the affected count without deleting.
- [ ] 7. Write unit tests for `InvalidateAllSymfonySessionsAction`: happy path deletes tokens and writes the event; idempotency guard throws on second invocation; `--cutover-at` boundary filters correctly.
- [ ] 8. Write a feature test for the artisan command: `--dry-run` mutates nothing; non-dry run writes the event; exit codes match.
- [ ] 9. Author `parity-checklist.md` — one row per endpoint shipped by MBA-005..MBA-019 with Symfony URL, Laravel URL, story id, feature-test path, sign-off checkbox.
- [ ] 10. Create `scripts/parity-diff.sh` (bash) that reads an endpoint list, fires requests at both APIs, normalizes JSON (`jq -S`), diffs, and exits non-zero on unexpected divergence. Document expected diffs (envelope shape) in the checklist.
- [ ] 11. Create `tests/Smoke/CriticalPathsTest.php` under `@group smoke` covering the 5 endpoints in §Critical endpoints. Credentials read from env (`SMOKE_API_KEY`, `SMOKE_USER_EMAIL`, `SMOKE_USER_PASSWORD`). Base URL from `SMOKE_TARGET_URL`.
- [ ] 12. Exclude the `smoke` group from the default `phpunit.xml` suite and add a `make smoke` target that runs it with env vars sourced from `.env.smoke`.
- [ ] 13. Write `runbook.md` following the shape in §Runbook. Number every step; name an owner per step; include the exact flip command discovered in task 1.
- [ ] 14. Write `decommission.md` with the T+30d archive date, AWS-key revocation list (Symfony's `async-aws/s3` client), container-shutdown steps, and the "Laravel is the sole writer" data-ownership statement.
- [ ] 15. Draft `communications/email-T-1d.md` and `communications/push-T-1d.md` — RO + EN copy, under 300 chars for push. Subject, body, sender identity, scheduled-send time.
- [ ] 16. Author the observability spec (dashboard panel list + alarm thresholds) as an appendix in `runbook.md` §5. Panel set: login success rate, 5xx rate by endpoint, token issuance rate, DB replication lag. Alarm: 5xx > 5× baseline for 5 minutes.
- [ ] 17. Execute the staging dry-run: provision a prod-snapshot DB, run `make migrate`, run the invalidate command, run `make smoke`. Record outcomes as a dry-run log in `runbook.md` §Appendix.
- [ ] 18. Run `make lint-fix`, `make stan`, `make test` (default suite, smoke excluded) before handing off. Smoke suite is run separately against staging, not as a gate here.

## Risks & notes

- **Data loss.** Shared DB is a single MySQL instance — the risk is not data loss at flip time (both apps write the same rows) but **divergent concurrent writes** in the minutes surrounding the flip. Mitigation: the flip is atomic at the proxy layer; Symfony stops receiving traffic the instant the proxy is reconfigured. A T-0 DB snapshot is the true safety net.
- **Auth-session loss is the design.** Every user logs in again post-cutover. That is intentional (memory: "forced global logout at go-live"). The risk is not the logout itself but **login-endpoint saturation** if all mobile clients retry simultaneously on resume. Mitigation: rate-limit `POST /api/v1/auth/login` at the proxy (100 rpm per IP), confirm the mobile client has exponential backoff on 429.
- **DNS TTL.** The hostname must have a TTL ≤ 60s at T-3d. If ops has the TTL at 1h, rollback takes an hour for stale resolvers — unacceptable. Task 13 pre-flight checklist verifies current TTL.
- **Cache cold start.** Laravel response cache (bible-versions, books) starts empty at T-0. Pre-warm by hitting every cacheable GET from a warmup script at T-5m; otherwise the first live users see DB-backed latency and the smoke test can falsely show a latency regression. Add warmup as a pre-flight task item in `runbook.md` §1.
- **Prereqs from MBA-007..MBA-019 that look incomplete from a cutover lens.** All thirteen domain stories are status `draft` as of this plan's authoring. Every one of them must reach `ready` before task 9 (parity checklist) can be fully populated, and before task 11 (smoke test) has its bible-versions + books endpoints to hit. This story cannot enter Engineer execution until MBA-005..MBA-019 close. Track as an explicit external dependency.
- **Story splitting.** Keep as one story. The deliverables are tightly coupled (checklist, runbook, command, smoke suite all reference the same cutover event). Splitting adds coordination cost without reducing surface area. If the story bloats during execution, the natural cut line is: "cutover infrastructure" (job + command + migration + smoke suite) vs. "cutover operations" (runbook + parity checklist + decommission + comms). Defer that split unless task 7..12 alone exceed 2 working days.
- **Forced-logout idempotency.** If ops runs the command twice by accident, the second run must no-op with a clear message, not re-write another `security_events` row (audit table would show false duplicate events). Test 7 pins this.
- **Rollback asymmetry.** Sanctum tokens issued between T-0 and rollback-time are orphaned on rollback. Document in runbook §6; do not attempt to "forward" them back to Symfony.
- **No schema changes.** All schema reconciliation lived in MBA-005. This story adds only `security_events`. Do not expand scope.
