# Symfony Decommission Plan

Executed at **T+30 days** after cutover. Until then the Symfony
containers stay powered down but preserved — fast rollback is still on
the table for the first 24 hours, and a low-bandwidth "warm archive"
posture for the remainder. After T+30d the following steps execute;
this document is the checklist.

## Archive date

**T+30d = cutover-date + 30.** Record actual date at cutover:
`Archive on: _____`.

## Data ownership

From **T-0 onward**, the **Laravel API is the sole writer** of the
shared MySQL database. The Symfony codebase is frozen. No patches, no
hotfixes, no "just one more endpoint". If a production issue needs a
change during the T-0 → T+30d window, it lands in the Laravel codebase
or it does not land.

Rationale: the schema reconciliation in MBA-005 preserved Symfony-era
column types (e.g. `users.id` as unsigned INT) precisely so both apps
could coexist during migration. Post-cutover, the shared schema is
owned by Laravel migrations only; any future schema drift would be
undetected by a frozen Symfony codebase and cause divergent writes if
re-enabled.

## Active client enumeration

Before the archive date, re-confirm no unexpected clients still hit
the Symfony URL. Grep the reverse-proxy access logs for the prior 30
days:

```bash
# Distinct User-Agents hitting the Symfony host, last 30 days.
awk '{print $NF}' /var/log/nginx/symfony-access.log.* \
    | sort -u
```

Expected set:

| User-Agent substring | Client | Action |
|---|---|---|
| `MyBible/iOS` | Mobile app (iOS) | Confirm on latest release. |
| `MyBible/Android` | Mobile app (Android) | Confirm on latest release. |
| `Mozilla/*` (public site) | mybible.eu website | Confirm migrated or deprecated. |
| `curl/*`, `wget/*` | Ad-hoc / ops | Ignore. |
| _any other third-party_ | **investigate** | 30-day deprecation notice by email; proceed regardless after notice window. |

Record the enumeration result dated within 7 days of archive:

```
Enumeration date: _____
Unexpected clients found: _____
Action taken: _____
```

## Container shutdown steps

At T+24h the Symfony container is already `docker compose stop`'d.
At T+30d:

1. **Confirm the Symfony container has been off for ≥ 29 days.**
   ```bash
   docker inspect symfony-api --format '{{.State.Status}} (since {{.State.FinishedAt}})'
   ```
   Expected: `exited`.
2. **Remove the container + volumes from production compose.**
   ```bash
   docker compose -f docker-compose.prod.yml rm -fsv symfony-api
   ```
3. **Delete the Symfony service block from `docker-compose.prod.yml`**
   and commit the change. Tag the commit `symfony-decommissioned-YYYY-MM-DD`.
4. **Verify zero references to the Symfony host/port remain:**
   ```bash
   grep -rn 'symfony-api' docker-compose.prod.yml /etc/nginx/
   ```
   Expected: no matches.

## AWS key revocation

Symfony used `async-aws/s3` for user-uploaded avatars and media
exports. These keys become orphaned at T-0 but are not revoked until
T+30d (in case a rollback reveals a forgotten consumer):

1. **List the access keys scoped to the Symfony IAM user:**
   ```bash
   aws iam list-access-keys --user-name symfony-api
   ```
2. **Deactivate (not delete) each key first:**
   ```bash
   aws iam update-access-key --user-name symfony-api \
       --access-key-id AKIA... --status Inactive
   ```
3. **Wait 7 days.** Monitor CloudWatch for any `AccessDenied` events on
   the S3 bucket attributable to these keys. None expected — but if
   something trips, we've deactivated not deleted, so re-enabling is
   cheap.
4. **Delete the keys and the IAM user:**
   ```bash
   aws iam delete-access-key --user-name symfony-api --access-key-id AKIA...
   aws iam delete-user --user-name symfony-api
   ```
5. Record key IDs and deletion timestamps in the ops log.

## Symfony repo archival

1. **Tag the final commit.**
   ```bash
   cd /path/to/symfony-repo
   git tag -a archived-$(date +%F) -m "Symfony API frozen at cutover. Laravel is the sole writer."
   git push origin archived-$(date +%F)
   ```
2. **GitHub archive.** Settings → General → "Archive this repository".
   Read-only from now on.
3. **README banner.** Add a one-sentence banner at the top of the
   archived repo's README:
   > **This repository is archived.** The production API moved to
   > Laravel on YYYY-MM-DD. See `mybible-api` for the active codebase.
4. **Remove from CI.** Disable Symfony's CI pipeline in whatever CI
   system is in use (GitHub Actions, CircleCI, etc.).

## Final sign-off

| Step | Done | Operator | Date |
|---|---|---|---|
| Enumeration check | [ ] | | |
| Containers removed | [ ] | | |
| AWS keys deactivated | [ ] | | |
| AWS keys deleted | [ ] | | |
| Repo tagged + archived | [ ] | | |
| CI disabled | [ ] | | |
| Post to `#engineering`: "Symfony decommissioned." | [ ] | | |
