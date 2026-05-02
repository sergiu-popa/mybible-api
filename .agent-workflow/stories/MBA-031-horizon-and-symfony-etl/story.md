# Story: MBA-031-horizon-and-symfony-etl

## Title

Switch the API queue from `database` to `redis` with Laravel Horizon for
visibility, and run the full Symfony→Laravel data ETL via a Horizon-
backed job set: hymnal verse aggregation, reading plan progress
materialisation, Sabbath School content typed-block split, intra-text
highlight reshape, devotional type backfill, language code conversion,
book code conversion, polymorphic resource_downloads ETL, news language
default, Olympiad UUID backfill, mobile_versions seed, user
preferred_language NULL backfill.

## Status

`draft`

## Description

By the time this story runs, all schema reconciles (MBA-023 + every
parity story) have landed. The Laravel API is structurally ready to host
Symfony's data, but the rows are still in their original Symfony shape.
This story moves them.

Two things happen here:

1. **Queue infrastructure swap.** The API has been running on
   `QUEUE_CONNECTION=database` with a single `mybible-api-worker`
   container. Horizon gives us per-job introspection, automatic
   scaling, tag-based search, and a UI dashboard. The shared
   `mybible-redis` container already exists, so the swap is a
   composer install, an env change, and a docker compose update.
2. **ETL.** A coordinated set of Horizon jobs migrates Symfony rows
   into the Laravel shape. Each job is idempotent and resumable —
   running it twice produces the same outcome — and writes progress to
   `import_jobs`. Order matters because some jobs depend on others
   (e.g. content typed-block split must run before highlights ETL,
   because highlights reference content rows).

The ETL is meant to run **once** during the cutover maintenance
window, but the jobs ship with safe reruns so they can be exercised
in staging beforehand and re-run after data fixes.

## Acceptance Criteria

### Horizon migration

1. `laravel/horizon` installed via composer.
2. `QUEUE_CONNECTION` env value changed from `database` to `redis`.
   Redis connection points at the existing `mybible-redis` container.
3. The `mybible-api-worker` container in `docker-compose.yml` is
   replaced with `mybible-api-horizon` running `php artisan horizon`.
   Health check + restart policy preserved.
4. Horizon dashboard reachable at `/horizon` behind the existing
   super-admin gate (Sanctum + `super-admin` middleware).
5. Existing job classes (e.g. `DeleteUploadedObjectJob` from MBA-022)
   continue to function — verified by a smoke test (deleting an
   `EducationalResource` enqueues and processes the cleanup job).
6. Job batches (Bus::batch) supported (Horizon ships with batch
   support).

### ETL job orchestration

7. `App\Application\Jobs\Etl\RunSymfonyEtlJob` — top-level orchestrator.
   Triggered by `php artisan symfony:etl --confirm` (Artisan command,
   super-admin only when invoked via UI).
8. Sub-jobs run in dependency order via `Bus::chain` (sequential
   stages) and `Bus::batch` (parallelised within a stage):

   - **Stage 1 — Identifier normalisation** (parallelised):
     - `BackfillLanguageCodesJob` — `varchar(3)` → `char(2)` across
       every table touched by reconcile (per MBA-023 §12).
     - `BackfillBookCodesJob` — long-form / Romanian book names →
       USFM-3 in `notes`, `favorites.reference`, `olympiad_questions`
       (per MBA-023 §13–14). Unmapped values fail loudly.
   - **Stage 2 — Domain ETL** (parallelised after Stage 1):
     - `EtlBibleBooksAndVersesJob` — dedup `book` rows by
       abbreviation, populate `bible_chapters` from verse-counts,
       rewrite `bible_verses.bible_book_id` and
       `bible_verses.bible_version_id` from the temporary
       `_legacy_book_map` left behind by MBA-023.
     - `EtlHymnalStanzasJob` — aggregate `hymnal_verses` rows into
       `hymnal_songs.stanzas` JSON (per the existing JSON shape:
       `{language: [{index, text, is_chorus}]}`). Uses song's
       language from book metadata; `is_chorus` derived from the
       verse `number` field (Symfony convention: `number = "C"`
       indicates chorus).
     - `EtlReadingPlansJob` — wrap `reading_plans.title` /
       `description` in JSON (`{ "ro": "..." }`); generate slugs
       from titles; expand each `plan_day.passages` JSON into
       `reading_plan_day_fragments` rows with
       `type='verse_references'`.
     - `EtlReadingPlanSubscriptionsJob` — for each
       `plan_enrollment` (now `reading_plan_subscriptions`),
       materialise one `reading_plan_subscription_days` row per
       day in `[date_from, date_to]`. Mark `completed_at = now()`
       for any day whose position is present in the legacy
       `plan_progress` JSON. Drop the
       `reading_plan_subscription_days_legacy` table at end.
     - `EtlSabbathSchoolContentJob` — split each
       `sabbath_school_segments.content LONGTEXT` into typed
       `sabbath_school_segment_contents` rows where the existing
       `sb_content` table provides the typed shape; for legacy
       Laravel rows (segments authored without `sb_content`),
       parse the HTML into a single `type='text'` content row
       per segment.
     - `EtlSabbathSchoolQuestionsJob` — each
       `sabbath_school_questions` row becomes a content row with
       `type='question'`, `content = prompt`. Existing
       `sabbath_school_answers.sabbath_school_question_id` rewired
       to point at the new content row's id (FK rename in
       MBA-025).
     - `EtlSabbathSchoolHighlightsJob` — for each Laravel
       passage-string highlight, parse the passage; locate the
       containing `sabbath_school_segment_contents` row; compute
       offsets; insert offset-based highlight. Unparseable rows
       go to `sabbath_school_highlights_legacy` (per MBA-025 §18).
     - `EtlDevotionalTypesJob` — create `devotional_types` rows
       for the existing enum values and any Symfony
       `devotional_type` rows; backfill `devotionals.type_id`.
     - `EtlMobileVersionsSeedJob` — read `config/mobile.php` and
       insert one row per `(platform, kind)` if the table is empty.
     - `EtlCollectionsParentJob` — re-link existing
       `collection_topics.collection_id` from the legacy join;
       backfill `collection_topics.image_cdn_url` from Symfony.
     - `EtlOlympiadUuidsJob` — populate
       `olympiad_questions.uuid` and `olympiad_answers.uuid` for
       any row missing one.
     - `EtlResourceDownloadsJob` — read existing Symfony
       `resource_download` rows (single-typed), insert into the
       new polymorphic `resource_downloads` with
       `downloadable_type = 'educational_resource'`. Drop
       `ip_address`.
     - `EtlNewsLanguageDefaultJob` — set
       `news.language = 'ro'` on rows where NULL; set
       `news.published_at = created_at` where NULL.
     - `EtlNotesAndFavoritesJob` — convert
       `(book, chapter, position)` triplets to canonical
       reference strings; backfill `color` from any `color`
       column already present in Symfony rows; rewire FKs.
     - `EtlUserPreferredLanguageJob` — for **all** existing users,
       set `preferred_language = NULL` (per stakeholder decision)
       so the frontend post-login modal forces a confirmation.
       Existing `users.language` (3-char) is preserved as a
       fallback for one minor version, dropped by MBA-032.
9. Each sub-job is idempotent: re-running on already-migrated rows is
   a no-op (uses `INSERT ... ON DUPLICATE KEY UPDATE` or `WHERE
   target IS NULL` guards). Each sub-job records its own
   `import_jobs` row with progress percentage and row counts.
10. Failure semantics:
    - Within a sub-job, per-row failures are logged to
      `import_jobs.error` payload but don't abort the job (other
      rows still process). Job ends `partial`.
    - Stage failures (a whole sub-job fails) abort the chain and the
      orchestrator records `status = failed` on its own
      `import_jobs` row. Operator can re-run from CLI; idempotent
      sub-jobs resume cleanly.

### Operational tooling

11. CLI command: `php artisan symfony:etl --dry-run` simulates the
    full ETL on a clone of the prod DB and reports row counts, errors,
    estimated duration. Used pre-cutover.
12. CLI command: `php artisan symfony:etl --resume` re-runs only the
    sub-jobs whose `import_jobs.status` is not `completed`.
13. Read-only mode hint: the orchestrator emits a `security_events`
    row at start (`event = 'symfony_etl_started'`) and end
    (`event = 'symfony_etl_completed'` or `failed`) so the timeline
    is auditable.

### Tests

14. Unit tests for each sub-job against fixture data (small Symfony-
    shaped seeds) asserting:
    - Rows transform to the correct Laravel shape.
    - Idempotency: running twice yields identical state.
    - Error rows route to legacy/archive tables instead of being lost.
15. Integration test running the full chain end-to-end on a fixture
    that exercises every sub-job at least once, asserting the final
    DB matches the expected Laravel state.
16. Horizon migration smoke test: after the swap, queue work via
    `dispatch(new SomeKnownJob())`, assert it executes.
17. CLI test: `php artisan symfony:etl --dry-run` runs without
    side-effects (transactions rolled back) and produces a JSON
    summary on stdout.

## Scope

### In Scope

- Composer + env + docker-compose changes for Horizon.
- All ETL sub-jobs covering every Symfony→Laravel data transformation
  identified in MBA-023 through MBA-027 plus MBA-029.
- Top-level orchestrator command + dry-run + resume flags.

### Out of Scope

- Dropping legacy columns / temporary tables — that's MBA-032 (after
  the ETL completes and we confirm no rollback is needed).
- Row-level audit log (which Symfony row produced which Laravel row).
  We log counts and errors, not per-row provenance — the volume would
  be prohibitive and the legacy tables are still around for spot-check.
- Production cutover orchestration (DNS swap, mobile force-update,
  read-only mode on admin/frontend) — operational story, separate.

## API Contract Required

- No public API changes; this story is data movement only.
- The existing `import_jobs` polling endpoint (MBA-022 §15) gains
  rows with `type` values matching the new sub-job names so the admin
  status panel can render them.

## Technical Notes

- Splitting the ETL into many small idempotent sub-jobs (rather than
  one giant transaction) is a trade-off for resumability over atomicity.
  An ETL fail mid-stage is recoverable via `--resume`; an ETL that
  ran in one transaction would either succeed entirely or roll back
  hours of work. Idempotency + small jobs is industry-standard for
  multi-table migrations of this size.
- The ETL processes ~1 million `bible_verses` rows, ~50k
  `commentary_text` rows, ~tens of thousands of user-generated rows
  (notes, favourites, plan progress). With Horizon's parallelism
  (default 5 worker processes, 2 cores each), the total wall-clock
  is estimated 30–60 minutes — feasible inside a maintenance window.
- The decision to NULL all users' `preferred_language` is intentional
  — Symfony has many users whose `language` was set to a default
  rather than chosen by them; forcing the next-login modal lets us
  collect a high-quality preference from each user once. The 3-char
  `users.language` is kept as a fallback in case a user doesn't see
  the modal (e.g. mobile-only users until mobile is updated).
- Horizon migration on a quiet API (no production traffic yet on the
  Laravel surface — Symfony still serves prod) means we can ship it
  separately from the ETL if desired. We bundle them here because the
  ETL volume benefits from Horizon's scaling.

## References

- MBA-023 schema reconcile (creates the legacy maps and renames this
  ETL consumes).
- MBA-024 commentary domain (no ETL beyond what's already been
  reconciled — Symfony commentary rows simply move via reconcile).
- MBA-025 Sabbath School parity (depends on this ETL's content +
  highlights sub-jobs).
- MBA-026 Resource Books + Downloads (depends on the
  resource_downloads polymorphic ETL).
- MBA-027 Symfony parity catch-all (depends on devotional types,
  mobile versions, collections parent, Olympiad UUIDs, news language
  default, notes/favourites colour ETL).
- MBA-029 commentary AI workflow (`original` column populated from
  imported `content` during this ETL).
- MBA-030 analytics (no ETL — events go forward only).
- Laravel Horizon docs (queue dashboard + auto-scaling).
