# Runbook — Symfony → Laravel API Cutover

Numbered, imperative, versioned. Any step failing resets the clock. Dry-run
this runbook on staging before the real cutover; un-dry-run'd cutover is
not permitted.

## Owners

| Role | Name | Pager |
|---|---|---|
| Cutover lead | _tbd (ops)_ | _tbd_ |
| Laravel API on-call | _tbd (backend)_ | _tbd_ |
| Mobile client liaison | _tbd (mobile)_ | _tbd_ |
| Comms (email/push) | _tbd (growth)_ | _tbd_ |

## 1. Pre-flight checklist (T-1h → T-0)

All items must be green before step 2. Each line has a pass/fail output.

1. **Mobile client parity.** Latest mobile app release (targeting
   `/api/v1/auth/login`) has been live in the App Store + Play Store for
   ≥24 hours. Confirm with the mobile liaison. _Pass: yes/no._
2. **DNS TTL.** The API hostname (e.g. `api.mybible.eu`) has a TTL ≤ 60s.
   Check: `dig +short api.mybible.eu SOA` and the `A` record TTL.
   _Pass: TTL ≤ 60._
3. **DB replication lag.** On the read replica:
   `SHOW SLAVE STATUS\G` — `Seconds_Behind_Master` must be 0.
   _Pass: 0._
4. **Queue drained.** `docker exec mybible-api-worker php artisan
   queue:monitor` — 0 queued, 0 reserved.
   _Pass: 0/0._
5. **Backup snapshot.** Trigger a full DB snapshot (RDS or equivalent).
   Record snapshot ID here before proceeding. _Snapshot ID:_ `_____`.
6. **Laravel health check.** `curl -sf https://api.mybible.eu/up`
   returns `{"status":"ok"}`. _Pass: 200._
7. **Parity checklist.** Every row in `parity-checklist.md` is checked.
   _Pass: all checked._
8. **Response cache pre-warm.** Run `scripts/warmup-cache.sh`
   (to be authored by ops — hits every cacheable GET with the smoke
   api-key to populate Redis). Prevents a cold-cache latency spike at
   T-0. _Pass: exit 0._
9. **Rollback rehearsal.** Step 6 (rollback procedure) was dry-run'd
   on staging in the last 7 days. _Pass: log entry present._

If any step fails: abort, triage, re-schedule cutover no sooner than
T+7d.

## 2. Flip command

> **Ops to confirm actual production reverse proxy before cutover.**
> The dev stack uses Traefik v2.11 (see `docker-compose.yml`). Production
> proxy is likely Traefik or nginx fronting the Symfony container. The
> exact flip command depends on what's deployed.

**Traefik (label swap).** Edit the Laravel service's Traefik labels to
own the `api.mybible.eu` host and re-deploy the Symfony service without
the matching Host rule:

```yaml
# docker-compose.prod.yml — Laravel service (after flip)
labels:
  - "traefik.enable=true"
  - "traefik.http.routers.laravel-api.rule=Host(`api.mybible.eu`)"
  - "traefik.http.routers.laravel-api.tls=true"
  - "traefik.http.services.laravel-api.loadbalancer.server.port=8080"
```

Apply with:

```bash
docker compose -f docker-compose.prod.yml up -d --no-deps laravel-api symfony-api
```

**nginx (upstream swap).** Change the `proxy_pass` target in
`/etc/nginx/sites-available/api.mybible.eu`:

```nginx
location / {
    proxy_pass http://laravel-upstream;  # was http://symfony-upstream
    ...
}
```

Reload: `sudo nginx -t && sudo systemctl reload nginx`.

_Actual flip command used at cutover:_ `_____` (fill in post-flip).

## 3. Forced-logout trigger (T+0 → T+5m)

Immediately after the proxy flip, invalidate every Sanctum token issued
before cutover. Idempotent — second run no-ops with a clear message.

```bash
docker exec mybible-api-app php artisan mybible:invalidate-symfony-sessions \
    --cutover-at="$(date -u +%Y-%m-%dT%H:%M:%S+00:00)" \
    --reason="Production cutover $(date -u +%F) — forced global logout."
```

Expected output:

```
Revoked N token(s) created before 2026-05-01T03:00:00+00:00. security_events id=1.
```

Record `affected_count` and `event_id` in the post-cutover log (appendix
§A).

**If the command exits with `already been executed`:** the job ran
earlier (during a rehearsal or rollback-forward). Stop. Do not re-run.
Escalate to the cutover lead.

## 4. Smoke test invocation (T+5m → T+15m)

```bash
# On the ops workstation, not inside the app container.
TARGET_URL=https://api.mybible.eu make smoke
```

Credentials are read from `.env.smoke` (gitignored):

```
SMOKE_API_KEY=<prod api-key>
SMOKE_USER_EMAIL=smoke@mybible.eu
SMOKE_USER_PASSWORD=<stored in ops vault>
```

All 4 tests must pass (health, bible-versions, books, login+me).
Any failure → proceed immediately to §6 rollback.

## 5. Observation protocol (T+15m → T+1h)

### Dashboard panels (Grafana / Datadog / your APM)

1. **`POST /api/v1/auth/login` success rate** — per-minute rate of 200s
   vs. total requests. Expected: spike for 15–30m post-cutover as every
   mobile client logs in again, then back to baseline.
2. **5xx rate by endpoint** — per-minute count of 5xx across all
   `/api/v1/*` routes. Baseline: set at T-7d during staging dry-run.
3. **Token issuance rate** — rate of `INSERT` into
   `personal_access_tokens`. Mirrors login success rate.
4. **DB replication lag** — `Seconds_Behind_Master` on the read replica.
   Should stay 0.
5. **Forced-logout event** — `SELECT * FROM security_events WHERE event
   = 'symfony_cutover_forced_logout'`. Exactly one row expected.

### Alarms

| Alarm | Threshold | Action |
|---|---|---|
| 5xx rate | `> 5× baseline for 5 consecutive minutes` | Rollback (§6) |
| Login success rate | `< 80% for 5 minutes` | Rollback (§6) |
| DB replication lag | `> 60s for 2 minutes` | Investigate; possible rollback |
| P1 data corruption | any on-call report | Rollback (§6) immediately |

### Go / No-Go decision

At **T+1h**: cutover lead, backend on-call, and mobile liaison hold a
5-minute stand-up.

- **Go** → proceed to §7 (post-cutover cleanup).
- **No-Go** → §6 rollback. Document why.

## 6. Rollback procedure

**Trigger:** any alarm from §5 firing, OR the Go/No-Go call voting
No-Go.

1. **Flip the proxy back.** Reverse the change from §2. Laravel
   `traefik.http.routers.laravel-api.rule` label removed (or nginx
   `proxy_pass` reverted to `symfony-upstream`). Reload proxy.
2. **Do NOT re-issue invalidated tokens.** The forced-logout already
   ran; Sanctum tokens created between T-0 and now are orphaned once
   the proxy points back to Symfony. That's by design — re-forwarding
   them would write into Symfony's JWT space, which this migration does
   not do.
3. **Announce.** Operator posts in `#incidents` with the rollback
   timestamp, the trigger reason, and a one-sentence user impact
   summary.
4. **Known asymmetry.** Users who logged in between T-0 and rollback
   get logged out a second time when the proxy flips back. Acceptable
   vs. the data-risk alternative.
5. **Incident review** within 24h. Root-cause fix lands before the next
   cutover attempt. Re-schedule no sooner than T+7d.

## 7. Post-cutover cleanup

| When | Action | Owner |
|---|---|---|
| T+1h | Cutover lead posts "cutover green" in `#engineering` with link to dashboard. | Cutover lead |
| T+24h | Symfony upstream container powered off (`docker compose stop symfony-api`). Preserved, not destroyed — fast rollback still possible. | Ops |
| T+7d | Mobile in-app banner removed. Raise the DNS TTL back to 1h. | Mobile + ops |
| T+30d | Execute `decommission.md`: archive Symfony repo, revoke AWS `async-aws/s3` keys, remove Symfony container from compose. | Ops |

---

## Appendix A — Dry-run log

Record outcomes of the staging dry-run here before real cutover.

| Date | Operator | Step 1 | Step 2 | Step 3 | Step 4 | Step 5 | Notes |
|---|---|---|---|---|---|---|---|
| _tbd_ | _tbd_ | | | | | | |

## Appendix B — Cutover log (filled during real cutover)

| Timestamp (UTC) | Step | Result | Operator |
|---|---|---|---|
| _tbd_ | §1 pre-flight | | |
| _tbd_ | §2 flip | | |
| _tbd_ | §3 forced logout (affected_count=..., event_id=...) | | |
| _tbd_ | §4 smoke | | |
| _tbd_ | §5 T+1h go/no-go | | |
