# Story: MBA-032-cleanup-post-cutover

## Title

Post-cutover cleanup: drop deprecated columns and tables, lock NOT NULL
on backfilled columns, drop temporary mapping tables, drop legacy
sentinel patterns and backward-compat shims.

## Status

`draft`

## Description

After MBA-031 completes the Symfony→Laravel ETL and a soak window
confirms mobile and frontend traffic flows cleanly against the new API
shape, several columns and tables left around for compatibility become
dead weight. This story cleans them up.

The story is intentionally last: it is not safe to run until we have
confidence that no caller (mobile build, admin page, scheduled job) is
still reading the legacy shapes. It runs as a single migration set,
reversible only by restore (drops are destructive).

## Acceptance Criteria

### Drop legacy columns

1. `users.language` (3-char) — superseded by `users.preferred_language`
   (2-char) backfilled by MBA-031.
2. `users.salt`, `users.reset_token`, `users.reset_date` — Doctrine
   artefacts confirmed dropped by MBA-023; this story is a re-assert
   guard (no-op if already dropped).
3. `devotionals.type` (string enum) — superseded by
   `devotionals.type_id` from MBA-027.
4. `sabbath_school_segments.content` (LONGTEXT flat) — superseded by
   typed `sabbath_school_segment_contents` rows from MBA-025.
5. `sabbath_school_segments.day` (TINYINT) — superseded by
   `sabbath_school_segments.for_date` from MBA-025.
6. `qr_codes.url` (Laravel-trimmed) — superseded by
   `qr_codes.destination` from MBA-027.

### Drop legacy tables

7. `sabbath_school_questions` — questions are now content rows with
   `type='question'` (MBA-025 §11). Confirmed empty by ETL after FK
   rewire on `sabbath_school_answers` to `segment_content_id`.
8. `hymnal_verses` — superseded by `hymnal_songs.stanzas` JSON aggregated
   by MBA-031.
9. `reading_plan_subscription_days_legacy` — temporary holder created
   by MBA-023 for the Symfony `plan_progress` table; consumed by
   MBA-031 ETL.
10. `_legacy_book_map`, `_legacy_book_abbreviation_map` — temporary
    mapping tables from MBA-023.
11. `sabbath_school_highlights_legacy` — archive table for unparseable
    legacy passage strings (MBA-025 §18, MBA-031). Drop only if the
    archive has been reviewed and either re-injected by hand or
    accepted as lost. **Gate this drop behind explicit operator
    confirmation** (CLI flag `--drop-archives`); default is to leave
    these tables in place.
12. `doctrine_migration_versions` — confirmed drop guard.

### Lock NOT NULL after backfill

13. After MBA-031 backfills, the following columns flip from
    NULLABLE to NOT NULL:
    - `devotionals.type_id` — every devotional has a type.
    - `sabbath_school_lessons.age_group`, `.number` — backfilled from
      Symfony.
    - `bible_verses.bible_version_id`, `bible_verses.bible_book_id`
      — FK rewrite by ETL.
    - `commentary_texts.original` — populated for every imported row.
    - `olympiad_questions.uuid`, `olympiad_answers.uuid` — backfilled.
    - `mobile_versions.platform`, `.kind`, `.version` — seeded.
    - `users.preferred_language` stays NULLABLE — the post-login modal
      forces the user to set it; users who haven't logged in since
      cutover legitimately have NULL.
14. UNIQUE indexes that depend on NOT NULL columns are re-asserted /
    confirmed (e.g. `(devotionals.language, type_id, date)`).

### Sentinel cleanup

15. `sabbath_school_favorites.sabbath_school_segment_id`: confirm the
    sentinel `0` → `NULL` conversion from MBA-025 §19 produced no
    leftover `0` rows.
16. Drop the partial-UNIQUE fallback (two non-partial UNIQUEs) if
    MariaDB-compat fallback was used and the production target is
    MySQL 8 — switch to the cleaner functional partial unique. (No-op
    on MySQL 8.)

### Backward-compat shims

17. The `GET /api/v1/sabbath-school/lessons/{lesson}` Resource fallback
    that emitted `segments[].content` when no content rows existed
    (MBA-025 §13) is removed — by this story all segments have
    typed contents, the fallback is dead code.
18. The `GET /api/v1/devotionals/` parameter `type` accepts both slug
    (post-MBA-027) and the legacy enum strings (`adults` / `kids`) —
    the legacy enum acceptance is removed; clients must send slugs.
19. The `news.language` legacy fallback (defaulted to `ro` at read time
    when NULL) is removed — every news row has a language post-ETL.

### Tests

20. Migration tests asserting:
    - Each dropped column is gone (negative `Schema::hasColumn`).
    - Each dropped table is gone.
    - NOT NULL flip rows up correctly: an explicit attempt to insert
      NULL into a flipped column fails with the expected exception.
21. Smoke tests on each public endpoint that previously had a legacy
    fallback (sabbath school lesson detail, devotional show, news
    list) to confirm no consumer accidentally breaks.
22. Horizon Job test confirming `--drop-archives` is gated and the
    default run leaves archives in place.

## Scope

### In Scope

- All drops listed above.
- All NOT NULL flips listed above.
- Removal of backward-compat shims documented in MBA-024 / MBA-025 /
  MBA-027.

### Out of Scope

- Schema changes that go beyond cleanup (new features, refactors).
- Reorganising data layout (partitioning, sharding) — out of cutover
  scope.
- Drop of `sabbath_school_highlights_legacy` and other archive tables
  — explicitly gated, only on operator opt-in.

## API Contract Required

- Removal of the backward-compat shims listed in AC §17–19. Any
  consumer still relying on them will break — the cutover schedule
  must communicate this. The shims are dropped only after the soak
  window confirms no traffic is using them.

## Technical Notes

- This story runs **after** the cutover soak window (suggest at
  least 14 days of clean operation post-MBA-031). Until then, the
  legacy columns and shims are insurance — if a critical bug surfaces,
  rolling back is much less painful with the legacy data still in
  place.
- The decision to keep `users.preferred_language` nullable
  intentionally leaks an "unfinished" state for users who haven't
  logged in. The frontend modal handles it. A more aggressive option
  would be to backfill from `users.language`; we explicitly chose not
  to (per stakeholder decision: existing users may have wrong
  defaults from Symfony).
- `sabbath_school_highlights_legacy` and any other archive table is
  flagged with a `--drop-archives` CLI gate because a small number
  of users may have created highlights against passage strings that
  the ETL couldn't parse. The archive lets a human attempt manual
  rescue. Drop only when the rescue effort is explicitly closed.
- We deliberately do not run `OPTIMIZE TABLE` on the cleaned tables
  here — that's a separate ops decision based on observed bloat,
  not a routine post-migration step.

## References

- MBA-023 (created the legacy maps + temporary tables this story
  drops).
- MBA-024, MBA-025, MBA-026, MBA-027, MBA-029 (introduced the columns
  this story locks NOT NULL).
- MBA-031 (ran the ETL whose completion this story depends on).
- Cutover operational runbook (separate doc, lives in
  `apps/api/docs/cutover/` — to be authored prior to deploy).
