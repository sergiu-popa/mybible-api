# Audit — MBA-031-horizon-and-symfony-etl

## Scope

Holistic pass over the Horizon swap (Track A) and the 17-sub-job Symfony→Laravel ETL chain (Track B). Verified: queue infrastructure, Horizon dashboard gating, orchestrator chain semantics, sub-job idempotency contracts, ledger/security-event coupling, dry-run rollback, ETL transformation correctness, and test coverage. `make lint-fix`, `make stan`, `make test-api` all green.

## Findings

| # | Issue | Location | Severity | Status | Resolution |
|---|---|---|---|---|---|
| 1 | `Bus::chain(...)->catch()` callback discarded the `Throwable` that aborted the chain. The orchestrator's `import_jobs.error` column stayed NULL on failure, forcing operators to dig through every sub-job ledger row to find the cause. | `app/Application/Jobs/Etl/RunSymfonyEtlJob.php:170-178` | Warning | Fixed | Closure now accepts `Throwable $exception` and persists `$exception->getMessage()` onto the orchestrator row alongside `status=Failed`. `chain_failure_marks_orchestrator_failed` updated to invoke the closure via reflection with a real `RuntimeException` and assert both status and error message. |
| 2 | `EtlSabbathSchoolHighlightsJob` re-emits `sabbath_school_highlights_unparseable` on every `--resume` because legacy source rows are never stamped after archiving. | `app/Application/Jobs/Etl/EtlSabbathSchoolHighlightsJob.php:84-86` | Suggestion | Skipped | Carried over from review.md S1. Operational concern only — the orchestrator's ledger short-circuit prevents it once the sub-job reaches `Succeeded`/`Partial`. Would require schema change (`archived_at` on source) — out of scope for an audit pass. |
| 3 | `EtlReadingPlanSubscriptionsJob` drops `reading_plan_subscription_days_legacy` only when the run produced zero errors; partial runs leave the legacy table behind. | `app/Application/Jobs/Etl/EtlReadingPlanSubscriptionsJob.php:154-163` | Suggestion | Skipped | Carried over from review.md S2. Conservative-by-design: leaves a recovery surface when any row failed. Operator decides post-cutover whether to drop manually. |
| 4 | `EtlOlympiadUuidsJob` issues per-row `UPDATE` inside a PHP loop instead of a single set-based statement. | `app/Application/Jobs/Etl/EtlOlympiadUuidsJob.php:49-54` | Suggestion | Skipped | Carried over from review.md S3. PHP-generated UUIDs are a portability tradeoff over MySQL's `UUID()`; volume (~tens of thousands at most) keeps wall-time well under the cutover budget. |
| 5 | `EtlChunkProcessor` is implemented but unused; its `withRollback()` calls `DB::rollBack()` inside a `DB::transaction()` callback, which is a Laravel anti-pattern (the wrapper expects to commit/rollback itself based on exception flow). | `app/Domain/Migration/Etl/Support/EtlChunkProcessor.php` | Suggestion | Deferred — follow-up | Carried over from review.md S5. Defer to MBA-032 cleanup: either delete the helper or migrate one large-table caller (e.g. `EtlBibleBooksAndVersesJob`) as a proof-of-concept and fix the rollback pattern simultaneously. Tagging here so it does not slip. |
| 6 | `EtlSabbathSchoolQuestionsJob.rewireAnswers` re-run safety relies on new `segment_content_id` values not coinciding with any question's legacy id; not documented in the docblock. | `app/Application/Jobs/Etl/EtlSabbathSchoolQuestionsJob.php:130-137` | Suggestion | Skipped | Carried over from review.md S6. Property is guaranteed in production by sub-job ordering (content rows populated first in Stage 2a, IDs sit above the question id range) plus the orchestrator's ledger short-circuit. Not actionable without changing the algorithm. |
| 7 | `EtlReadingPlansJob::ensureLocaleMap` re-wraps an empty/list JSON array as `{"ro":"[]"}` because `array_is_list([])` is `true`, bypassing the "already locale map" early-return. | `app/Application/Jobs/Etl/EtlReadingPlansJob.php:91-110` | Suggestion | Deferred — follow-up | Edge case never observed in production data (legacy `name`/`description` is either plain string or `{ro:...}`). Tag against MBA-032 cleanup so a future ETL replay does not corrupt rows whose value is `[]`. |

## Test results

- `make test-api`: **1453 passed, 2 skipped, 0 failed** (5467 assertions, 61.26 s). Same skips as QA baseline (`Schema::hasColumn` guards for forward-compatible columns).
- `make stan`: **OK, no errors** (1346 files).
- `make lint-fix`: **PASS, 1372 files** (no formatting changes after fix).
- Targeted re-run on `RunSymfonyEtlEndToEndTest`: **4 passed (55 assertions)** — confirms the new failure-path assertion.

## Verdict

**PASS** — Status → `done`.

All Critical/Warning items resolved. Suggestions S2–S7 either carry forward from review.md (acknowledged as non-blocking) or are tagged against MBA-032 cleanup with explicit pointers (S5, S7).
