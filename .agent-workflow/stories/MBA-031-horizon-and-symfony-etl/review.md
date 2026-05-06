# Code Review — MBA-031-horizon-and-symfony-etl

## Summary

Third review pass after the engineer's `9157a3d` commit addressing W1–W5 from the prior round. All five Warnings now have correct, focused fixes plus dedicated tests:

- **W1** — `EtlResourceDownloadsJob.php:71-78` drops `resource_download.ip_address` whenever the column is still present, no longer gated on `$succeeded > 0`. Re-runs after a fully-migrated prior pass now correctly remove the legacy PII column. Covered by an extended assertion in `resource_downloads_copies_legacy_singular_rows_to_polymorphic` (`EtlSubJobTransformationsTest.php:701-705`) plus an explicit no-op-re-run check at line 707-712.
- **W2** — `RunSymfonyEtlJob.php:80-91` mirrors `BaseEtlJob`'s pattern: after `start('symfony_etl')`, returns early when `isAlreadyTerminal($orchestratorJob)` is true, preventing duplicate `symfony_etl_started` security events and timestamp clobbering on resume. Covered by `orchestrator_short_circuits_when_prior_run_already_terminal` (`RunSymfonyEtlEndToEndTest.php:88-119`).
- **W3** — `RunSymfonyEtlCommand.php:149-158` deletes any prior ledger rows for the slug *inside* the rolled-back transaction so the reporter no longer surfaces stale `Succeeded`/`Partial` rows during dry-run rehearsal. Deletion is reverted when `DryRunRollback` unwinds. Covered by `dry_run_bypasses_prior_terminal_ledger_rows` (`RunSymfonyEtlCommandTest.php:71-99`), which asserts the prior row survives but is bypassed mid-run.
- **W4** — `EtlSubJobTransformationsTest.php` adds fixture-driven tests for every previously uncovered branch: `hymnal_stanzas_aggregates_verses_with_chorus_detection` (line 815), `collections_parent_relinks_from_legacy_join_and_backfills_cdn_url` (line 853), `sabbath_school_content_splits_legacy_sb_content_rows` (line 893), `reading_plans_wraps_plain_string_name_into_locale_map` (line 943), `reading_plans_backfills_slug_when_missing` (line 977), `devotional_types_seeds_from_devotionals_enum` (line 996), and `devotional_types_backfills_type_id_when_null` (line 1043). Coverage gap is closed.
- **W5** — `EtlSabbathSchoolQuestionsJob.php:109-141` replaces the per-row UPDATE with a single `JOIN-UPDATE` through a transient mapping table; the docblock is rewritten to accurately describe the post-MBA-025 column shape and the collision-safe rewrite. Covered by `sabbath_school_questions_rewires_answers_post_mba_025_rename` (`EtlSubJobTransformationsTest.php:482-562`).

Companion fixes also picked up cleanly: `EtlReadingPlansJob.ensureLocaleMap` (line 91-110) correctly unwraps a JSON-encoded scalar before re-wrapping, so a column that already holds `"Bare title"` becomes `{"ro":"Bare title"}` rather than `{"ro":"\"Bare title\""}`.

The Suggestions S1–S5 from the prior pass were not addressed and remain optional — none block this review.

**Verdict:** APPROVE.

---

## Suggestions

- **S1 — Carried over: `EtlSabbathSchoolHighlightsJob` re-emits security events on every re-run.** `app/Application/Jobs/Etl/EtlSabbathSchoolHighlightsJob.php:84-86`. Source rows in `sabbath_school_highlights` (with `passage` non-null and `segment_content_id` null) are not cleared after archiving, so each `--resume` re-scans them and emits a duplicate count event. Stamping the source row (e.g. clearing `passage` or setting `archived_at`) would make subsequent runs idempotent on the security-event timeline. Operational concern only — the orchestrator's ledger short-circuit prevents it in practice once the sub-job is `Succeeded`/`Partial`.

- **S2 — Carried over: `EtlReadingPlanSubscriptionsJob` drops the legacy table only when there are no errors.** `app/Application/Jobs/Etl/EtlReadingPlanSubscriptionsJob.php:154-163`. A run that produced any per-row errors leaves `reading_plan_subscription_days_legacy` behind. Acceptable post-cutover, but worth deciding whether this should drop unconditionally on a successful pass (errors-or-not) or stay under operator control.

- **S3 — Carried over: `EtlOlympiadUuidsJob` per-row UPDATE in a PHP loop.** `app/Application/Jobs/Etl/EtlOlympiadUuidsJob.php:49-54`. PHP-generated UUIDs for portability is a defensible trade-off; only worth revisiting if production volume turns out to be much larger than tens-of-thousands.

- **S5 — Carried over: `EtlChunkProcessor` is implemented but unused.** `app/Domain/Migration/Etl/Support/EtlChunkProcessor.php`. Worth either migrating one large-table caller as a proof-of-concept or deleting if the engineer has decided the sub-jobs hand-roll iteration is the better fit.

- **S6 — `EtlSabbathSchoolQuestionsJob.rewireAnswers` re-run safety depends on legacy-id / new-content-id non-overlap.** `app/Application/Jobs/Etl/EtlSabbathSchoolQuestionsJob.php:130-137`. The JOIN-UPDATE relies on already-rewritten `segment_content_id` values not coinciding with any question's legacy id. In production this is effectively guaranteed (content rows are populated first by `EtlSabbathSchoolContentJob`, so their IDs sit above the question id range), and the orchestrator's ledger short-circuit prevents a fresh `execute()` from running twice anyway. A note in the docblock acknowledging the assumption — "this re-run idempotency assumes new content row IDs do not coincide with question legacy IDs, which `EtlSabbathSchoolContentJob` running first guarantees" — would close the loop for future readers.

---

## Out of scope but flagged

- The Horizon dashboard at `/horizon` remains a deliberate exception to the JSON-only posture, gated behind `auth:sanctum`+`viewHorizon` (super-admin). No action.
- Supervisor entry at `apps/api/.docker/supervisor/conf.d/horizon.conf` runs `php artisan horizon` directly; the healthcheck lives in the monorepo `docker-compose.yml`. QA should confirm `docker compose ps` reports the container as healthy.
