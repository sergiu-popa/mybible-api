# Plan — MBA-031-horizon-and-symfony-etl

> Design, don't implement. No code blocks, no method bodies, no SQL.
> Every helper listed here must be referenced by a task below.

## Approach

Two coordinated tracks share an `ImportJob` ledger and the existing `super-admin` gate. Track A swaps the queue from `database` to `redis` and installs Laravel Horizon; the dashboard at `/horizon` is a deliberate, narrowly-scoped exception to the JSON-only posture (gated by `auth:sanctum`+`super-admin`, no public routes added). Track B is a chained set of idempotent ETL jobs orchestrated by a single Artisan command (`symfony:etl`) with `--dry-run` / `--resume` / `--confirm`; per-row failures degrade a sub-job to `partial` rather than aborting, while sub-job failures abort the chain and leave the orchestrator row resumable.

## Domain

| Element | Role |
|---|---|
| `App\Domain\Migration\Etl\` (new sub-namespace) | Houses sub-job classes, transformer Actions, and DTOs for ETL stages — keeps Migration domain coherent with existing legacy maps and reconcile helpers. |
| Reuse `App\Domain\Admin\Imports\Models\ImportJob` | Single ledger row per sub-job + one for the orchestrator. `type` carries the sub-job slug; `payload` carries row counts / error samples. |
| Extend `ImportJobStatus` enum | Add `Partial` case (alongside Pending/Running/Succeeded/Failed); update `isTerminal()` to treat it as terminal. |
| Reuse `App\Domain\Migration\Support\ReconcileTableHelper`, `LegacyLanguageCodeMap`, `_legacy_book_map` | Already present from MBA-023 — sub-jobs read these, do not recreate. |
| Reuse `App\Domain\Security\Models\SecurityEvent` | Orchestrator emits `symfony_etl_started` / `_completed` / `_failed`; highlights ETL emits unparseable-row events. |

## Helpers (each consumed by ≥1 task below)

| Helper | Type | Consumers |
|---|---|---|
| `EtlJobReporter` (`app/Domain/Migration/Etl/Support/`) | Service that opens/closes an `ImportJob` row, increments progress, appends error samples to `payload`. | Every sub-job (Tasks 9–22). |
| `EtlChunkProcessor` | Trait or service that wraps cursor-based chunked iteration with per-row try/catch and reporter wiring. | Sub-jobs whose unit is "rows in a Symfony table" (Tasks 9–11, 13–15, 18–22). |
| `ResolveSegmentContentOffsets` Action | Parses a legacy passage string against a target `sabbath_school_segment_contents.content`, returns `{start_position, end_position}` or fails. | Task 14 (`EtlSabbathSchoolHighlightsJob`). |
| `BookCodeNormalizer` (extends MBA-023's existing book-abbrev backfill) | Maps long-form / RO names to USFM-3 in `notes`, `favorites.reference`, `olympiad_questions`. Throws `UnmappedLegacyBookException` on miss. | Tasks 7, 22 (Stage 1 + Notes/Favorites Stage 2). |
| `LanguageCodeNormalizer` (extends `LegacyLanguageCodeMap`) | `varchar(3)` → `char(2)` writes for every reconciled table. | Task 6 (Stage 1). |
| `ReadingPlanFragmentBuilder` | Expands a `plan_day.passages` JSON blob into an array of `reading_plan_day_fragments` row payloads typed `verse_references`. | Task 11 (`EtlReadingPlansJob`). |
| `SegmentContentSplitter` | Splits one `sabbath_school_segments.content LONGTEXT` into typed-block payloads, preferring rows already present in legacy `sb_content`, otherwise emits one `text` block per segment. | Task 13 (`EtlSabbathSchoolContentJob`). |
| `HymnalStanzaAggregator` | Collapses `hymnal_verses` rows into the per-language `stanzas` JSON shape; reads song-language from book metadata; flips `is_chorus` when `number === 'C'`. | Task 10 (`EtlHymnalStanzasJob`). |

## Actions / Sub-Jobs (sibling-name parity)

Every sub-job is `final class` extending the project queue base, dispatched onto Horizon's `etl` queue, idempotent (`WHERE target IS NULL` guards or `INSERT … ON DUPLICATE KEY UPDATE`), and writes to one `ImportJob` row via `EtlJobReporter`.

| Stage | Sub-job class (`App\Application\Jobs\Etl\…`) | Maps Symfony source → Laravel target |
|---|---|---|
| 1 | `BackfillLanguageCodesJob` | `varchar(3)` → `char(2)` across every reconciled table (per MBA-023 §12). |
| 1 | `BackfillBookCodesJob` | Long-form / RO names → USFM-3 in `notes`, `favorites.reference`, `olympiad_questions`. |
| 2 | `EtlBibleBooksAndVersesJob` | Dedup `book` rows by abbrev; populate `bible_chapters`; rewire `bible_verses.bible_book_id` & `bible_version_id` from `_legacy_book_map`. |
| 2 | `EtlHymnalStanzasJob` | `hymnal_verses` rows → `hymnal_songs.stanzas` JSON via `HymnalStanzaAggregator`. |
| 2 | `EtlReadingPlansJob` | Wrap title/description JSON, slugify, expand `plan_day.passages` → `reading_plan_day_fragments` via `ReadingPlanFragmentBuilder`. |
| 2 | `EtlReadingPlanSubscriptionsJob` | Materialise per-day rows from `[date_from,date_to]`; flip `completed_at` from legacy `plan_progress` JSON; drop `reading_plan_subscription_days_legacy` at end. |
| 2 | `EtlSabbathSchoolContentJob` | LONGTEXT → typed `sabbath_school_segment_contents` via `SegmentContentSplitter`. |
| 2 | `EtlSabbathSchoolQuestionsJob` | Each `sabbath_school_questions` row → content row `type='question'`; rewires `sabbath_school_answers.segment_content_id`. |
| 2 | `EtlSabbathSchoolHighlightsJob` | Passage strings → offset highlights via `ResolveSegmentContentOffsets`; unparseable → `sabbath_school_highlights_legacy` + `security_events` row. |
| 2 | `EtlDevotionalTypesJob` | Create `devotional_types` rows (existing enum + Symfony `devotional_type` rows); backfill `devotionals.type_id`. |
| 2 | `EtlMobileVersionsSeedJob` | If empty, insert one row per `(platform, kind)` from `config/mobile.php`. |
| 2 | `EtlCollectionsParentJob` | Re-link `collection_topics.collection_id`; backfill `image_cdn_url`. |
| 2 | `EtlOlympiadUuidsJob` | Backfill `olympiad_questions.uuid` and `olympiad_answers.uuid` where NULL. |
| 2 | `EtlResourceDownloadsJob` | Symfony single-typed `resource_download` rows → polymorphic `resource_downloads` with `downloadable_type='educational_resource'`; drop `ip_address`. |
| 2 | `EtlNewsLanguageDefaultJob` | `news.language='ro'` where NULL; `news.published_at=created_at` where NULL. |
| 2 | `EtlNotesAndFavoritesJob` | `(book, chapter, position)` → canonical reference; backfill `color`; rewire FKs. |
| 2 | `EtlUserPreferredLanguageJob` | NULL `users.preferred_language` for **all** users (intentional — forces post-login modal). |
| Top | `RunSymfonyEtlJob` | Orchestrator: opens orchestrator `ImportJob`, builds `Bus::chain([Stage1 Bus::batch, Stage2 Bus::batch])`, emits security events at start/end, marks status. |

## DTOs

| DTO (`App\Domain\Migration\Etl\DataTransferObjects\…`) | Purpose |
|---|---|
| `EtlRunOptions` | `confirm: bool`, `dryRun: bool`, `resume: bool`, `only: array<string>` — passed by command into orchestrator. |
| `EtlSubJobResult` | `processed: int`, `succeeded: int`, `skipped: int`, `errors: array<int,string>` — returned by every sub-job to the reporter. |

## Console Command

| Command | Path | Behavior |
|---|---|---|
| `symfony:etl` | `App\Application\Commands\RunSymfonyEtlCommand` | Flags `--confirm`, `--dry-run`, `--resume`, `--only=…`. `--dry-run` wraps each sub-job invocation in a transaction that rolls back; emits a JSON summary on stdout. `--resume` filters chain to sub-jobs whose `ImportJob.status` ∉ {`succeeded`, `partial`}. Auth not relevant (CLI), but noted as super-admin-only when surfaced in admin UI later. |

## Horizon Integration

| Concern | Decision |
|---|---|
| Package | `laravel/horizon` (composer require). |
| Queue connection | `QUEUE_CONNECTION=redis`, `REDIS_HOST=mybible-redis`. Cache and session already on `mybible-redis`; no new infra. |
| Worker container | `mybible-api-worker` → `mybible-api-horizon` running `php artisan horizon`. Healthcheck via `php artisan horizon:status`. |
| Dashboard route | `/horizon` (provided by Horizon's service provider) wrapped behind `auth:sanctum` + `super-admin` via `HorizonServiceProvider::gate()`. Documented as a conscious deviation from JSON-only — it is an internal admin tool, not a public API surface, and serves only super-admins. |
| Config trim | Publish `config/horizon.php`; trim unused defaults (extra environments, mail notifications, slack); leave one `production` and one `local` environment. Per project rule on published-config trimming. |
| Queues | `default`, `etl`, `cleanup` (matches `DeleteUploadedObjectJob`'s implicit queue). Horizon `balance=auto`. |
| Smoke test | Dispatch `DeleteUploadedObjectJob` from a feature test; assert it runs through Horizon's queue. |

## Tasks

### Track A — Horizon swap

- [x] 1. Composer-require `laravel/horizon`; publish its assets and config; trim `config/horizon.php` per project posture (drop unused environments, disable mail/slack notifications, set `domain=null` so dashboard mounts on the API host).
- [x] 2. Update `.env.example` and per-env `.env` notes: `QUEUE_CONNECTION=redis`, `REDIS_HOST=mybible-redis`, `REDIS_PORT=6379`, `HORIZON_DOMAIN=null`, `HORIZON_PATH=horizon`.
- [x] 3. Add `App\Providers\HorizonServiceProvider`: define `Horizon::auth()` to require `auth:sanctum` resolved user with `is_super=true` and `is_active=true` (mirrors `EnsureSuperAdmin`).
- [x] 4. Update root `docker-compose.yml`: rename service `api-worker` → `api-horizon`, change `command: ['horizon']`, add a healthcheck that runs `horizon:status`. Update entrypoint script accordingly.
- [x] 5. Add a feature test `DispatchesJobsViaHorizonTest` that fakes the queue, dispatches `DeleteUploadedObjectJob`, asserts it lands on the `redis` connection's `cleanup` queue (smoke test for AC §5).

### Track B — ETL Stage 1 (identifier normalisation, parallel batch)

- [x] 6. Create `App\Application\Jobs\Etl\BackfillLanguageCodesJob` consuming `LanguageCodeNormalizer`; iterate every reconciled table flagged in MBA-023 §12; report progress via `EtlJobReporter`.
- [x] 7. Create `BackfillBookCodesJob` consuming `BookCodeNormalizer`; on `UnmappedLegacyBookException` append the offending value to `ImportJob.payload.errors`, do not abort.

### Track B — ETL Stage 2 (domain ETL, parallel batch after Stage 1)

- [x] 8. Add `EtlJobReporter` service and `EtlChunkProcessor` trait under `app/Domain/Migration/Etl/Support/`; both must idempotently open/close an `ImportJob` and chunk a query builder with per-row try/catch.
- [x] 9. Create `EtlBibleBooksAndVersesJob` reading `_legacy_book_map`; dedup `book` rows by abbrev; populate `bible_chapters` from verse counts; rewire `bible_verses.bible_book_id` + `bible_version_id`.
- [x] 10. Create `EtlHymnalStanzasJob` consuming `HymnalStanzaAggregator`; aggregate `hymnal_verses` into per-song `stanzas` JSON.
- [x] 11. Create `EtlReadingPlansJob` consuming `ReadingPlanFragmentBuilder`; wrap title/description as `{ro: …}`; slugify titles uniquely; insert `reading_plan_day_fragments` typed `verse_references`.
- [x] 12. Create `EtlReadingPlanSubscriptionsJob`: per-subscription day expansion, `completed_at=now()` for legacy `plan_progress` positions, drop `reading_plan_subscription_days_legacy` only after a successful pass.
- [x] 13. Create `EtlSabbathSchoolContentJob` consuming `SegmentContentSplitter`; idempotent insert into `sabbath_school_segment_contents`.
- [x] 14. Create `EtlSabbathSchoolQuestionsJob`: each `sabbath_school_questions` row becomes a `type='question'` content row; rewire `sabbath_school_answers.segment_content_id` (FK rename was MBA-025).
- [x] 15. Create `EtlSabbathSchoolHighlightsJob` consuming `ResolveSegmentContentOffsets`; unparseable rows → `sabbath_school_highlights_legacy` + `security_events` row per MBA-025 §18.
- [x] 16. Create `EtlDevotionalTypesJob`: seed `devotional_types` for enum values + Symfony `devotional_type`; backfill `devotionals.type_id`; preserve string `type` until MBA-032.
- [x] 17. Create `EtlMobileVersionsSeedJob`: if `mobile_versions` empty, seed from `config/mobile.php`; one row per `(platform, kind)`.
- [x] 18. Create `EtlCollectionsParentJob`: rewire `collection_topics.collection_id` from legacy join; backfill `image_cdn_url`.
- [x] 19. Create `EtlOlympiadUuidsJob`: populate `olympiad_questions.uuid` + `olympiad_answers.uuid` where NULL.
- [x] 20. Create `EtlResourceDownloadsJob`: insert polymorphic rows with `downloadable_type='educational_resource'`; drop `ip_address` column on completion.
- [x] 21. Create `EtlNewsLanguageDefaultJob`: NULL→`'ro'` for `news.language`; NULL→`created_at` for `news.published_at`.
- [x] 22. Create `EtlNotesAndFavoritesJob` consuming `BookCodeNormalizer`: convert `(book, chapter, position)` to canonical reference strings; backfill `color`; rewire FKs.
- [x] 23. Create `EtlUserPreferredLanguageJob`: set `users.preferred_language=NULL` for all rows (intentional per stakeholder); preserve `users.language` 3-char fallback.

### Track B — Orchestrator + CLI

- [x] 24. Add `RunSymfonyEtlJob` orchestrator dispatching Stage 1 batch then Stage 2 batch via `Bus::chain([Bus::batch([...]), Bus::batch([...])])`; open `ImportJob.type='symfony_etl'`; emit `security_events` at start (`symfony_etl_started`) and end (`_completed` / `_failed`).
- [x] 25. Add `App\Application\Commands\RunSymfonyEtlCommand` (`symfony:etl`) with `--confirm`, `--dry-run`, `--resume`, `--only=`; wire DTO `EtlRunOptions` into the orchestrator. `--dry-run` wraps each sub-job in a rolled-back transaction and prints a JSON summary on stdout.
- [x] 26. Extend `ImportJobStatus` enum with `Partial`; update `isTerminal()`; ensure existing `ShowImportJobController` resource serializer still renders the new value.

### Tests

- [x] 27. Per-sub-job feature tests: each job has a fixture-driven test asserting (a) target shape, (b) idempotency on re-run (counts match after second invocation), (c) error rows route to legacy/archive tables, not lost.
- [x] 28. Integration test `RunSymfonyEtlEndToEndTest` exercising the full chain on a fixture covering every sub-job at least once; asserts final DB matches the expected Laravel state.
- [x] 29. CLI test for `symfony:etl --dry-run`: no row mutations after run, JSON summary present on stdout.
- [x] 30. Horizon smoke test (Track A Task 5) — dispatched job runs, status reaches `done`.

## Risks

- **Horizon requires Redis-backed queue.** All queued work moves off the `jobs` MySQL table. The `jobs`, `failed_jobs`, `job_batches` tables become unused on the Horizon side; leave them in place during rollout in case of revert; MBA-032 drops them.
- **Dashboard at `/horizon` is a deviation from JSON-only.** Mitigated by gating behind `auth:sanctum`+`super-admin` (same posture as admin endpoints) and not registering it as a public API surface. Documented in Approach so future Auditor passes don't flag it as a regression.
- **Volume.** ~1M `bible_verses` rows + content split. Stage 2 sub-jobs that touch `bible_verses` and `sabbath_school_segments` must use cursor + chunk; `EtlChunkProcessor` is the single seam to enforce this.
- **`Bus::chain([Bus::batch(...), …])` semantics.** Stage failure must abort the chain. Verified via Horizon docs — the orchestrator records its own `failed` status when the batch's `catch` callback fires.
- **Sequencing dependency.** `EtlSabbathSchoolHighlightsJob` reads `sabbath_school_segment_contents` populated by `EtlSabbathSchoolContentJob`. They cannot be in the same parallel batch. Split Stage 2 into 2a (independent ETL) and 2b (highlights, after content+questions). The orchestrator chain is therefore three batches, not two.
- **`config/mobile.php` may be removed by MBA-032.** Sub-job reads it once at run time; safe inside the cutover window.
- **Per-row failure policy = `partial` status.** Auditor may flag this as silent degradation. Mitigated by always emitting a `security_events` row + appending error samples to `ImportJob.payload`.
