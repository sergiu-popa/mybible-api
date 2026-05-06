# Code Review — MBA-031-horizon-and-symfony-etl

## Summary

Re-review after the engineer addressed the previous round's findings. Track A (Horizon swap) is unchanged from the prior pass and remains solid. Track B's three Critical items are now properly resolved:

- **C1** — `EtlNotesAndFavoritesJob` now backfills `color` (from `colour` and `favorite_categories.color`), rewires `favorites.category_id` from `_legacy_favorite_category_map`, and surfaces unmapped books to `payload.errors` with dedicated tests for all three branches.
- **C2** — `tests/Feature/Application/Etl/RunSymfonyEtlEndToEndTest.php` exists, seeds a multi-domain fixture, and asserts the resulting target shape across news/olympiad/reading-plans/sabbath-school/notes/favorites/mobile-versions.
- **C3** — `tests/Feature/Application/Etl/EtlSubJobTransformationsTest.php` adds fixture-driven coverage with target-shape, idempotency, and error-routing assertions for the priority paths.

Prior Warnings W1–W4 + W6–W8 are correctly addressed (ImportJob rows are now opened by the sub-job's own `handle()` lifecycle; stale `Running` rows are retired in `EtlJobReporter::start()`; the audit ignore is gone; CONCAT uses single-quoted `'/'`; the dead COALESCE is dropped; `complete()` emits `etl_sub_job_partial` for partial runs). W5 is acknowledged as kept-inlined per the engineer's note.

A handful of new issues surfaced during re-review — mostly edge cases on re-run/dry-run idempotency and gaps in fixture coverage. None are catastrophic, but together they're worth one more pass before QA.

**Verdict:** REQUEST CHANGES.

---

## Warnings

- [x] **W1 — `EtlResourceDownloadsJob` only drops `resource_download.ip_address` when `$succeeded > 0`.** `app/Application/Jobs/Etl/EtlResourceDownloadsJob.php:71`. If a prior partial run already inserted every row, a re-run sees nothing new (`$succeeded === 0`) and the `ALTER TABLE ... DROP COLUMN ip_address` is skipped. The legacy PII column then survives across all subsequent re-runs. The drop should be unconditional once the source has been seen — the `Schema::hasColumn('resource_download', 'ip_address')` guard is sufficient on its own. **Fix:** change the predicate to `if (Schema::hasTable('resource_download') && Schema::hasColumn('resource_download', 'ip_address'))`.

- [x] **W2 — Orchestrator does not short-circuit when the `symfony_etl` row is already in a non-failed terminal state.** `app/Application/Jobs/Etl/RunSymfonyEtlJob.php:78-115`. `$reporter->start('symfony_etl')` happily returns an existing `Succeeded`/`Partial` row, but the orchestrator does NOT call `isAlreadyTerminal()` (unlike `BaseEtlJob::handle()`). It then emits a duplicate `symfony_etl_started` `security_events` row, and — when `--resume` skips every settled sub-job — the empty-batches branch overwrites the orchestrator row's payload via `complete()`. Across multiple resume attempts, the security_events timeline accumulates noise and the orchestrator's `started_at`/`finished_at` no longer reflect the original successful run. **Fix:** mirror `BaseEtlJob`'s pattern: after `start('symfony_etl')`, return early if `$reporter->isAlreadyTerminal($orchestratorJob)`.

- [x] **W3 — `--dry-run` returns stale results when prior real runs left `Succeeded`/`Partial` ledger rows.** `app/Application/Commands/RunSymfonyEtlCommand.php:142-181` invokes `BaseEtlJob::handle()` inside a transaction. Because the reporter's `start()` surfaces an existing terminal row of the same `type`, `isAlreadyTerminal()` short-circuits the sub-job body, and the dry-run summary captures the OLD payload — not what a fresh run would produce. Operators running `--dry-run` after a partial cutover (the exact scenario where dry-run fidelity matters) will see "already-Succeeded" status across the board with no indication of pending work. **Fix:** in dry-run mode, either delete the prior ledger rows for the dry-run's lifetime (already inside a rolled-back transaction so the deletion is reverted), or thread a `bypassTerminal=true` flag through `EtlJobReporter::start()` for this code path so it always opens a fresh row.

- [x] **W4 — Plan task 27 (per-sub-job fixture-driven assertions) is not uniform across the 17 sub-jobs.** `tests/Feature/Application/Etl/EtlSubJobTransformationsTest.php` has solid coverage for the priority paths but several sub-jobs only get a "safe no-op when source absent" assertion, which doesn't exercise the actual transformation:
  - `EtlHymnalStanzasJob` — `hymnal_stanzas_is_a_safe_no_op_when_legacy_verses_table_absent` (line 717) only verifies the no-source path. The aggregator (chorus detection from `number='C'`, per-language stanza JSON) is untested.
  - `EtlCollectionsParentJob` — `collections_parent_is_a_safe_no_op_when_no_legacy_join_table_exists` (line 579) only asserts the missing-source branch. Neither `relinkParents` nor `backfillCdnUrls` is exercised with a populated legacy join.
  - `EtlSabbathSchoolContentJob` — only the fallback `type='text'` path is tested (line 437); the `splitFromLegacy` branch (consuming `sb_content`) has no fixture.
  - `EtlReadingPlansJob.wrapPlanText` and `backfillSlugs` — the test seeds rows already in target shape (line 333+), so neither the "wrap a plain string" nor the "generate slug from missing slug" branch executes.
  - `EtlDevotionalTypesJob` — only `seedTypesFromLegacyTable` is tested (line 542); `seedTypesFromDevotionalsEnum` and `backfillDevotionalTypeIds` are not.

  The same pattern (inline `Schema::create()` for the legacy source, then `DB::table()->insert()` of a representative row) used by `resource_downloads_copies_legacy_singular_rows_to_polymorphic` would close these. **Fix:** extend `EtlSubJobTransformationsTest` with one fixture-driven test per uncovered branch above, or **acknowledge** if the priority paths are deemed sufficient and the rest is covered by the existing end-to-end test (which does exercise some of these on a smaller fixture).

- [x] **W5 — `EtlSabbathSchoolQuestionsJob.rewireAnswers` only rewires when the legacy `sabbath_school_question_id` column still exists.** `app/Application/Jobs/Etl/EtlSabbathSchoolQuestionsJob.php:106-111`. The comment ("Two possible shapes pre/post MBA-025") shows the engineer thought about this, but the second branch (post-MBA-025-rename) is empty: if MBA-025 already renamed the column to `segment_content_id`, that column carries the OLD question ID values that need rewriting to the NEW content row IDs, but the code never does that remap. After MBA-025 ships and the legacy column is gone, this sub-job becomes a no-op for already-renamed answers and they will point at non-existent question IDs once MBA-032 drops the questions table. **Fix:** add a second UPDATE branch that, when only `segment_content_id` exists, rewrites `WHERE segment_content_id = $legacyQuestionId AND <some marker that this is a legacy unrewritten value>` to the new content row id. Alternatively, document explicitly that MBA-025 keeps the legacy column in place until MBA-032 and link to the migration that proves it (the deferred extractions tracker should reflect this contract).

---

## Suggestions

- **S1 — `EtlSabbathSchoolHighlightsJob` re-emits `security_events` on every re-run.** `app/Application/Jobs/Etl/EtlSabbathSchoolHighlightsJob.php:84-86` emits one event per run with the count of unparseable highlights, but the original rows in `sabbath_school_highlights` (with `passage` non-null and `segment_content_id` null) are not cleared after archiving. Each `--resume` rescans them and emits a duplicate event with the same count. Consider clearing `passage` (or stamping `archived_at` on the source row) so subsequent runs filter them out.

- **S2 — `EtlReadingPlanSubscriptionsJob` drops `reading_plan_subscription_days_legacy` only when no errors.** `app/Application/Jobs/Etl/EtlReadingPlanSubscriptionsJob.php:154-163`. A run with 1 error and 1000 successful materialisations leaves the legacy table behind forever (the next run sees nothing to do, errors stay empty, and the drop only triggered on a fully clean pass — which it now is, but the table was never dropped). Either drop unconditionally on a successful pass (regardless of per-row partial errors) or accept that this is operator-triggered cleanup post-cutover.

- **S3 — `EtlOlympiadUuidsJob` per-row UPDATE in a PHP loop.** `app/Application/Jobs/Etl/EtlOlympiadUuidsJob.php:49-54`. Carried over from the prior review's S3. The engineer has chosen PHP-generated UUIDs for portability, which is fine — but with two tables and N rows each, this is 2N round trips. For a one-shot ETL on tens-of-thousands of rows it's acceptable; flag only if the actual production volume is much larger.

- **S4 — `EtlSabbathSchoolQuestionsJob` docblock references a tracking key that is not used.** `app/Application/Jobs/Etl/EtlSabbathSchoolQuestionsJob.php:19-22` claims the job tracks the question→content mapping in `import_jobs.payload['question_to_content']`, but the actual implementation uses a `(segment_id, title='legacy_question_<id>')` lookup on `sabbath_school_segment_contents`. Update the docblock to match.

- **S5 — `EtlChunkProcessor` is implemented but unused.** `app/Domain/Migration/Etl/Support/EtlChunkProcessor.php` is a clean abstraction; only `EtlNotesAndFavoritesJob.canoniseReferences` uses `chunkById` directly without going through it. The other large-table sub-jobs (`EtlBibleBooksAndVersesJob`, `EtlSabbathSchoolContentJob`, `EtlSabbathSchoolHighlightsJob`) hand-roll their own iteration. The chassis is the right place to enforce per-row try/catch + reporter wiring uniformly; consider migrating one or two callers as a proof-of-concept and leaving a comment explaining when to use it vs. when to chunk inline.

---

## Out of scope but flagged

- The Horizon dashboard at `/horizon` remains a deliberate exception to the JSON-only posture, gated behind `auth:sanctum`+`viewHorizon` (super-admin). No action.

- The supervisor file at `apps/api/.docker/supervisor/conf.d/horizon.conf` runs `php artisan horizon` directly without an embedded healthcheck — the healthcheck lives in the monorepo `docker-compose.yml`. Worth verifying once at QA time that `docker compose ps` shows the container as healthy.

- Plan task 27's docblock-acknowledged trade-off (idempotency-protocol test in `EtlSubJobsIdempotencyTest` doesn't seed source data because Symfony source tables are not in CI) is fine **provided** the dedicated fixture-driven tests in `EtlSubJobTransformationsTest` close the gap. W4 above flags where they don't.
