# Code Review — MBA-031-horizon-and-symfony-etl

## Summary

Two-track delivery: Track A swaps the queue from `database` to `redis` and mounts Horizon at `/horizon` behind the existing super-admin gate; Track B introduces 17 idempotent ETL sub-jobs orchestrated by `RunSymfonyEtlJob` and an `symfony:etl` Artisan command with `--confirm`/`--dry-run`/`--resume`/`--only`.

The Horizon swap (Track A) is solid: gate mirrors `EnsureSuperAdmin`, supervisor split (default/etl/cleanup) is sensible, dashboard auth is wired correctly, and `DeleteUploadedObjectJob` is pinned to `cleanup`. The ETL chassis (`BaseEtlJob`, `EtlJobReporter`, `EtlChunkProcessor`, DTOs, `Partial` enum case) is clean.

The ETL sub-jobs themselves are largely correct, but the plan's three test deliverables (per-sub-job assertions, end-to-end integration, and idempotency-on-data) are not actually covered: every fixture-free `EtlSubJobsIdempotencyTest` data row exercises a sub-job against a schema where Symfony source tables don't exist, so almost every job no-ops via its `Schema::hasTable` guard and the test asserts only "reaches a terminal state." One sub-job (`EtlNotesAndFavoritesJob`) is missing two of three planned behaviours. These need to land before QA.

**Verdict:** REQUEST CHANGES.

---

## Critical

### [x] C1 — `EtlNotesAndFavoritesJob` missing color backfill and FK rewire (plan task 22, AC §8)

`app/Application/Jobs/Etl/EtlNotesAndFavoritesJob.php:32-49` only canonises `(book, chapter, position)` triplets into `reference` strings. Plan task 22 explicitly requires three things:

> Create `EtlNotesAndFavoritesJob` consuming `BookCodeNormalizer`: convert `(book, chapter, position)` to canonical reference strings; **backfill `color`**; **rewire FKs**.

AC §8 (story) repeats this:

> backfill `color` from any `color` column already present in Symfony rows; rewire FKs.

Neither colour copying nor FK rewiring is implemented. Additionally, the plan's helper table promises the job will consume `BookCodeNormalizer`; instead, line 81 silently skips any row whose `book` is not already in `BibleBookCatalog`, which means an unmapped legacy book name leaves the row untouched without being routed to `payload.errors`.

**Fix:** add the colour backfill + FK rewire branches (one `affectingStatement` each), and either consume `BookCodeNormalizer` or surface skipped rows as `appendError()` so an operator sees them.

### [x] C2 — Integration test `RunSymfonyEtlEndToEndTest` is missing (plan task 28, AC §15)

Plan task 28 / AC §15:

> Integration test running the full chain end-to-end on a fixture that exercises every sub-job at least once, asserting the final DB matches the expected Laravel state.

No such test exists (`grep -rn "EndToEnd"` and `find … -name "RunSymfonyEtlEndToEndTest*"` return zero). This is the *one* test that proves the orchestrator wires the chain correctly and that the sub-jobs produce the planned target shape.

**Fix:** add `tests/Feature/Application/Etl/RunSymfonyEtlEndToEndTest.php` that seeds a tiny Symfony-shaped fixture (a few rows in each `_legacy_*` / Symfony source table the jobs touch), runs `RunSymfonyEtlJob` synchronously (e.g. `Bus::fake()` won't work — use `Queue::sync()` or directly invoke the chain's contents via `dispatchSync`), and asserts target rows.

### [x] C3 — Per-sub-job feature tests do not assert the planned shape, idempotency, or error routing (plan task 27, AC §14)

`tests/Feature/Application/Etl/EtlSubJobsIdempotencyTest.php:83-115` runs every sub-job against an empty `RefreshDatabase` schema. The Symfony source tables (`sb_content`, `_legacy_book_map`, `resource_download` singular, `collection_topic_collection`, `devotional_type` singular, `hymnal_verses`, `reading_plan_subscription_days_legacy`, etc.) are not seeded, so each sub-job hits its `Schema::hasTable(...)` guard and returns an empty `EtlSubJobResult`. The test then asserts `status->isTerminal()` and that `succeeded === succeeded` across two runs — both trivially true for `Succeeded(0,0)`.

Plan task 27 requires:

> (a) target shape, (b) idempotency on re-run (counts match after second invocation), (c) error rows route to legacy/archive tables, not lost.

Of these, only the "counts match" half of (b) is tested, and only because the counts are zero. (a) and (c) are not exercised at all.

The tests that *do* assert behaviour (`EtlJobReporterTest`, `RunSymfonyEtlCommandTest`, `DispatchesJobsViaHorizonTest`) are good, but they cover the chassis, not the transformations.

**Fix:** for each sub-job, add a fixture-driven test that seeds the relevant source rows, runs the job, asserts the target rows match the expected shape (counts + a representative row), runs the job again, asserts no new rows appear, and (where applicable, e.g. `EtlSabbathSchoolHighlightsJob`, `BackfillBookCodesJob`) seeds an unparseable row and asserts it lands in the legacy/archive table rather than the target.

---

## Warnings

- [x] **W1 — Orchestrator pre-creates `ImportJob` rows for every sub-job before any of them run.** `app/Application/Jobs/Etl/RunSymfonyEtlJob.php:121-146` calls `$reporter->start($slug)` for every sub-job in `buildStage`, which creates a `Running` row with `started_at = now()`. These rows will sit in `Running` state for the entire duration of the chain (potentially 30–60 min per the story estimate). Consequences: (a) `started_at` is misleading — it reflects when the orchestrator dispatched, not when the sub-job actually began; (b) `payload` cannot reflect partial progress because the row is created with `payload: []` and only updated when the job actually executes. **Fix:** move `$reporter->start($slug)` into the sub-job lifecycle (`BaseEtlJob.handle()`) so each row's `started_at` is when the worker picked it up. The orchestrator only needs to pass the `slug`, not a pre-computed `importJobId`.

- [x] **W2 — Orphaned `Running` rows are not detected by `EtlJobReporter::start()`.** `app/Domain/Migration/Etl/Support/EtlJobReporter.php:22-41` only short-circuits on `Succeeded` / `Partial`. If the orchestrator dispatches, pre-creates rows (W1), then crashes before the chain runs, those rows remain `Running` forever. A subsequent `--resume` calls `start()` again and creates *another* `Running` row of the same `type`, so over multiple botched cutovers the table accumulates orphans. **Fix:** in `start()`, also detect a stale `Running` row (e.g. `started_at < now()->subHours(2)`) and either reuse or transition it to `Failed` before creating a new one. The cleanest variant pairs with W1 — if rows are only created when work begins, this whole class disappears.

- [x] **W3 — `RunSymfonyEtlJob::onFailure` only fails the orchestrator row.** `RunSymfonyEtlJob.php:148-157` writes `Failed` to the orchestrator's `ImportJob` but leaves every pre-created sub-job row in `Running`. After resolving W1+W2, this concern goes away; if W1 stays, the failure callback should also flip every still-`Running` sub-job row of this orchestrator's run to `Failed`.

- [x] **W4 — `composer.json` adds `audit.ignore: ["PKSA-qgg1-cfs4-rkb8"]` without justification.** `composer.json:88-91`. The advisory ID is not documented, so neither a future maintainer nor a code reviewer can verify whether it's a known false positive on a transitive dep or a genuine vulnerability that's been silenced. **Fix:** add a one-line comment describing which package the advisory targets, why we're ignoring it, and the date — or remove the ignore and address the underlying advisory.

- [x] **W5 — Plan's helper table classes were inlined into sub-jobs rather than extracted.** Plan §Helpers names `LanguageCodeNormalizer`, `BookCodeNormalizer`, `ReadingPlanFragmentBuilder`, `SegmentContentSplitter`, `HymnalStanzaAggregator`, and `ResolveSegmentContentOffsets`. None of these classes exist (only the existing `LegacyLanguageCodeMap` and `BackfillLegacyBookAbbreviationsAction` are reused). The transformations are inlined inside `EtlReadingPlansJob`, `EtlSabbathSchoolContentJob`, `EtlHymnalStanzasJob`, `EtlSabbathSchoolHighlightsJob`. This is a deliberate-looking simplification (each transformation is small and used by exactly one job), but the deviation should be acknowledged in `audit.md`. **If you decide to keep the inlined form**, this Warning can be acknowledged-only — the plan helper extraction is not load-bearing for correctness.

- [x] **W6 — `EtlCollectionsParentJob.backfillCdnUrls()` uses double-quoted `"/"` in raw SQL.** `app/Application/Jobs/Etl/EtlCollectionsParentJob.php:78-82` passes `CONCAT(?, "/", image_path)`. MySQL with default `sql_mode` reads `"/"` as a string literal, but a server with `ANSI_QUOTES` set treats it as an identifier reference and the statement errors. **Fix:** swap to single quotes — `CONCAT(?, '/', image_path)`.

- [x] **W7 — `EtlBibleBooksAndVersesJob.rewireVerseFks()` `COALESCE(bible_version_id, …)` is dead.** `app/Application/Jobs/Etl/EtlBibleBooksAndVersesJob.php:64-72` updates `bible_version_id = COALESCE(bv.bible_version_id, m.bible_version_id)` but the `WHERE bv.bible_book_id IS NULL` filter means rows where `bible_book_id` is NULL are processed; rows where `bible_version_id` is already set but `bible_book_id` is NULL would be rare. The `COALESCE` is harmless but obscures intent. **Fix:** drop it (`SET bv.bible_version_id = m.bible_version_id`) or move the version-rewire into its own UPDATE filtered by `bv.bible_version_id IS NULL` if rewiring versions independently is the goal.

- [x] **W8 — Auditor will flag the pre-emptive sub-job ImportJob row creation as silent degradation.** Risks list in plan calls this out: `Per-row failure policy = partial status. Auditor may flag this as silent degradation. Mitigated by always emitting a security_events row + appending error samples to ImportJob.payload`. Today, only `EtlSabbathSchoolHighlightsJob` emits a `security_events` row on per-row failures. `BackfillBookCodesJob`, `EtlReadingPlanSubscriptionsJob`, `EtlNotesAndFavoritesJob` (after C1's fix) accumulate errors but do not emit a security event. **Fix:** in `EtlJobReporter::complete`, when status is `Partial`, emit a `security_events` row of `event = 'etl_sub_job_partial'` with the sub-job slug and error count. That centralises the contract and keeps Auditor's degradation flag at bay.

---

## Suggestions

- **S1 — Use `EtlChunkProcessor` for `EtlHymnalStanzasJob` and `EtlReadingPlansJob.wrapPlanText`.** Both load the full table into memory (`EtlHymnalStanzasJob.php:36-43`, `EtlReadingPlansJob.php:66`). The tables are small today, so this is not a Warning. Moving to `chunkById` matches the convention enforced everywhere else and protects against future row growth.

- **S2 — `EtlSabbathSchoolHighlightsJob.resolveOffsets()` returns the first `mb_strpos` match.** `EtlSabbathSchoolHighlightsJob.php:99-126`. If the same passage substring appears twice in a content body, the highlight resolves to whichever happens to be first. Consider archiving when the match is ambiguous (e.g. `mb_substr_count($body, $passage) > 1`) so a manual operator can decide.

- **S3 — `EtlOlympiadUuidsJob` performs per-row UPDATE in a loop.** `EtlOlympiadUuidsJob.php:49-54`. MySQL 8 supports `UPDATE … SET uuid = UUID() WHERE uuid IS NULL` in one statement; would be one SQL per table instead of N.

- **S4 — `DryRunRollback` lives at the bottom of `RunSymfonyEtlCommand.php`.** Two classes per file is unusual for this codebase. Either move it to its own file under `App\Application\Commands\Exceptions\` or inline it as an anonymous class.

- **S5 — `RunSymfonyEtlJob::handle()` always opens a `ImportJob` orchestrator row even on `--resume`.** `RunSymfonyEtlJob.php:78-79`. If the previous run already left a `symfony_etl` row in `Failed`, `EtlJobReporter::start('symfony_etl')` creates a new one rather than reusing the failed row. Across multiple resume attempts, the table accumulates orchestrator rows. Same root cause as W2.

- **S6 — `RunSymfonyEtlCommandTest::confirm_dispatches_orchestrator_with_correct_options` asserts `$job->queue === 'etl'`.** That property is set in the constructor via `$this->onQueue('etl')`. With `Bus::fake()`, the assertion works, but worth pairing with `Queue::assertPushedOn('etl', RunSymfonyEtlJob::class)` if you ever move to `Queue::fake()`.

---

## Out of scope but flagged

- The Horizon dashboard at `/horizon` *is* a deviation from the JSON-only posture, deliberately scoped (gate mirrors `EnsureSuperAdmin`, no public surface). The plan and CLAUDE.md acknowledge this. **No action.** Worth noting so a future Auditor pass doesn't re-flag it.

- `boost.json` adding `configuring-horizon` to skills is correct and matches the new domain.
