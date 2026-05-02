# Story: MBA-027-symfony-parity-catch-all

## Title

Restore Symfony parity across the smaller domains in a single coordinated
story: Devotional types as entity + audio/video, Mobile Versions DB-backed,
Collections parent + topic image, QR Codes full Symfony model, Olympiad
UUID + per-verse + is_reviewed + user attempts persistence, Notes &
Favorites colour, News detail endpoint + language defaults.

## Status

`draft`

## Description

Several Symfony features were trimmed during the Laravel rewrite:

- **Devotional types** were collapsed into a `varchar(16)` enum
  (`adults`/`kids`); Symfony has them as a first-class entity with
  `slug`, `title`, `position`, allowing admin-defined types.
- **Audio/video embeds on devotionals** (`audio_cdn_url`, `audio_embed`,
  `video_embed`) were dropped.
- **Mobile Versions** moved from a DB table to `config/mobile.php`,
  losing admin editability.
- **Collections** lost its `collection` parent entity (groups of topics)
  and `collection_topic.image_cdn_url`.
- **QR Codes** lost `place`, `source`, `destination`, `name`, `content`,
  `description` — Laravel kept only `reference` + `url` + `image_path`.
- **Olympiad** lost UUID stable identifiers, the per-verse granularity
  (Symfony `question.verse, book, chapter`; Laravel
  `chapters_from-chapters_to`), the editorial `is_reviewed` workflow
  flag, and never had user attempt persistence.
- **Notes & Favorites** lost the `color` column.
- **News** has no detail endpoint; existing rows lack `language` and
  `published_at`.

Each gap is small individually, but together they prevent the Laravel API
from being a drop-in replacement for the Symfony API that mobile and
admin already depend on. This story closes them as a coordinated batch.

User attempt persistence for Olympiad is added in this story (per
stakeholder confirmation that scores should be server-side persisted).

## Acceptance Criteria

### Devotional types entity + media

1. `devotional_types` table (renamed from Symfony `devotional_type` by
   MBA-023): `id`, `slug VARCHAR(64) UNIQUE`, `title VARCHAR(128)`,
   `position UNSIGNED SMALLINT DEFAULT 0`, `language CHAR(2) NULL`
   (NULL = global), timestamps.
2. `devotionals` adds `type_id BIGINT UNSIGNED NOT NULL` FK
   (`ON DELETE RESTRICT`); the existing string `type` column is kept
   one minor version for backward compat then dropped by MBA-032. ETL
   in MBA-031 backfills `type_id` from the existing `type` enum
   (`adults`, `kids`) + creates corresponding `devotional_types` rows.
3. `devotionals` adds:
   - `audio_cdn_url TEXT NULL`
   - `audio_embed LONGTEXT NULL`
   - `video_embed LONGTEXT NULL`
4. UNIQUE `(language, type_id, date)` re-asserted (replaces the
   regression flagged in MBA-023 §7).
5. Public `GET /api/v1/devotionals/?date=...&language=...&type=...` —
   `type` parameter accepts the type's `slug` (backward-compat) and
   resolves it to `type_id` server-side. Old enum values continue to work.
6. Admin CRUD on `/api/v1/admin/devotional-types/*` (super-admin only).
7. Admin reorder: `POST /api/v1/admin/devotional-types/reorder` (was
   deferred in MBA-022 E-02; now closed).

### Mobile Versions DB-backed

8. `mobile_versions` table (renamed from Symfony `mobile_version` by
   MBA-023): final shape — `id`, `platform VARCHAR(16)` (`ios`, `android`),
   `kind VARCHAR(16)` (`min_required`, `latest`), `version VARCHAR(25)`,
   `released_at TIMESTAMP NULL`, `release_notes JSON NULL`, `store_url
   VARCHAR(255) NULL`, timestamps.
9. UNIQUE `(platform, kind)` — exactly one `min_required` and one
   `latest` per platform.
10. `GET /api/v1/mobile/version?platform=ios|android` — replaces the
    config-driven endpoint with a DB query. Cache 5 min. Response shape
    is identical to the existing config-backed shape so mobile clients
    don't change.
11. Admin CRUD on `/api/v1/admin/mobile-versions/*` (super-admin only).
12. ETL in MBA-031 seeds the table from the existing `config/mobile.php`
    values (one-time on first deploy where the table is empty).

### Collections parent + image

13. `collections` table (renamed from Symfony `collection` by MBA-023):
    `id`, `slug VARCHAR(255) UNIQUE`, `name VARCHAR(255)`, `language
    CHAR(2)`, `position UNSIGNED INT DEFAULT 0`, timestamps. This is the
    "group of topics" parent (e.g. "Topical references", "Biblical
    persons").
14. `collection_topics` adds:
    - `collection_id BIGINT UNSIGNED NULL` FK (`ON DELETE SET NULL` —
      orphaned topics are still browsable)
    - `image_cdn_url TEXT NULL`
15. ETL in MBA-031 reads existing Symfony `collection_topic.collection_id`
    and `collection_topic.image_cdn_url` and populates accordingly.
16. Public endpoints:
    - `GET /api/v1/collections?language=` — list groups for a language.
    - `GET /api/v1/collections/{collection:slug}` — detail with nested
      topics list (id, name, image, position).
    - Existing `GET /api/v1/collections/...topics...` endpoint is kept
      but namespaced under the parent slug:
      `GET /api/v1/collections/{collection:slug}/topics/{topic}`.
17. Admin CRUD for both `collections` and `collection_topics` (with
    image upload integration via the existing `presign` endpoint).

### QR Codes full Symfony model

18. `qr_codes` Laravel-trimmed schema is extended back to the Symfony
    shape:
    - `place VARCHAR(255) NOT NULL` (location identifier — physical
      sticker placement)
    - `base_url VARCHAR(255) NOT NULL` (the redirector domain)
    - `source VARCHAR(255) NOT NULL` (campaign/source label)
    - `destination VARCHAR(255) NOT NULL` (the URL the QR ultimately
      resolves to — replaces the Laravel `url` column)
    - `name VARCHAR(50) NOT NULL`
    - `content LONGTEXT NOT NULL` (the encoded payload — typically
      Bible reference or campaign URL)
    - `description LONGTEXT NULL`
    - `image_path VARCHAR(255) NULL` (kept from Laravel)
    - `reference VARCHAR(255) NULL` — kept from Laravel for the existing
      verse-lookup flow; nullable now since not all QR rows are
      verse-targeted
    - timestamps
19. UNIQUE `(place, source)` — ensures a (location, campaign) pair has
    a single QR code.
20. Existing `GET /api/v1/qr-codes?reference=` endpoint preserved
    (reference-keyed lookup for the verse modal flow).
21. New scan-tracking endpoint: `POST /api/v1/qr-codes/{qr}/scans` —
    anonymous, fires `qr_code.scanned` analytics event with the QR
    metadata. Returns `204`.
22. Admin CRUD on `/api/v1/admin/qr-codes/*`.

### Olympiad parity + user attempts

23. `olympiad_questions` adds:
    - `uuid CHAR(36) UNIQUE NOT NULL` — backfilled with `Str::uuid()`
    - `verse SMALLINT UNSIGNED NULL` — per-verse granularity (Symfony
      `question.verse`)
    - `chapter SMALLINT UNSIGNED NULL` — single-chapter mode (Symfony
      `question.chapter`); when set, `chapters_from`/`chapters_to`
      are NULL. Existing range-based questions keep
      `chapters_from`/`chapters_to`.
    - `is_reviewed BOOLEAN DEFAULT false` + index — editorial workflow.
24. `olympiad_answers` adds `uuid CHAR(36) UNIQUE NOT NULL` — backfilled.
25. New `olympiad_attempts` table (server-side scoring persistence):
    - `id BIGINT UNSIGNED PRIMARY KEY`
    - `user_id INT UNSIGNED NOT NULL` FK `ON DELETE CASCADE`
    - `book VARCHAR(8) NOT NULL`
    - `chapters_label VARCHAR(32) NOT NULL` — e.g. `"1-3"`, `"5"` —
      the theme key for grouping
    - `language CHAR(2) NOT NULL`
    - `score SMALLINT UNSIGNED NOT NULL`
    - `total SMALLINT UNSIGNED NOT NULL`
    - `started_at TIMESTAMP NOT NULL`
    - `completed_at TIMESTAMP NOT NULL`
    - timestamps
26. New `olympiad_attempt_answers` table:
    - `attempt_id BIGINT UNSIGNED NOT NULL` FK
    - `olympiad_question_id BIGINT UNSIGNED NOT NULL` FK
    - `selected_answer_id BIGINT UNSIGNED NULL` FK (NULL = skipped)
    - `is_correct BOOLEAN NOT NULL`
    - PRIMARY KEY `(attempt_id, olympiad_question_id)`
27. Endpoints:
    - `POST /api/v1/olympiad/attempts` — start attempt (auth required;
      anonymous attempts not persisted, only client-side). Returns
      attempt id + question UUIDs to lock the set.
    - `POST /api/v1/olympiad/attempts/{attempt}/answers` — submit one
      or more answers (idempotent on `(attempt, question)`).
    - `POST /api/v1/olympiad/attempts/{attempt}/finish` — finalise score.
    - `GET /api/v1/olympiad/attempts?language=&book=&chapters=` —
      user history, paginated.
    - Admin: `GET /api/v1/admin/olympiad/attempts` — read-only metrics
      view (super-admin gated).

### Notes & Favorites colour

28. `notes` adds `color VARCHAR(9) NULL`. Backfilled NULL for existing
    rows; admin/frontend may set per-note.
29. `favorites` adds `color VARCHAR(9) NULL`. Backfilled NULL.
30. `POST /api/v1/notes` and `PATCH /api/v1/notes/{note}` accept
    `color` (`#RRGGBB[AA]` validation).
31. `POST /api/v1/favorites` and `PATCH /api/v1/favorites/{favorite}`
    accept `color` similarly.

### News detail + language defaults

32. `GET /api/v1/news/{news}` detail endpoint added (previously only
    list).
33. Symfony `news` rows get `language='ro'` by default (per stakeholder
    decision); MBA-031 ETL does the backfill.
34. Symfony `news` rows without `published_at` get
    `published_at = created_at` (preserves chronological order).

### Tests

35. Feature tests for every new endpoint covering auth, validation,
    happy path, edge cases (e.g. attempt finish with unanswered
    questions returns 422; UUID lookup on unknown returns 404; type-id
    backfill rejects types not in the seed list).
36. Migration tests asserting:
    - All existing devotionals get `type_id` populated correctly.
    - `mobile_versions` seeded from config has correct `(platform, kind,
      version)` rows.
    - Olympiad questions/answers get UUIDs.
    - News rows get `language='ro'` and `published_at` from `created_at`.

## Scope

### In Scope

- All seven domain catch-up items listed in the AC.
- Backwards-compatible endpoint shapes during the deprecation window
  (e.g. `devotionals.type` enum string still accepted).

### Out of Scope

- Admin UI alignment for any of these — admin MB-015 covers it.
- Frontend rendering — Devotional audio/video player is in frontend
  MB-019; QR landing pages in MB-017; Olympiad attempts UI defers
  until mobile/web product cycle picks it up.
- Hymnal book metadata `meta JSON` — explicitly deferred per
  stakeholder decision; comes later in a separate story.

## API Contract Required

Each domain ships its own Resources:

- `DevotionalTypeResource`, `DevotionalResource` extended with
  `audio_cdn_url`, `audio_embed`, `video_embed`.
- `MobileVersionResource` (matches existing config-backed shape).
- `CollectionResource`, `CollectionTopicResource` extended with
  `image_url`.
- `QrCodeResource` extended with `place`, `source`, `destination`,
  `name`, `content`, `description`.
- `OlympiadQuestionResource` extended with `uuid`, `verse`, `chapter`,
  `is_reviewed`. `OlympiadAnswerResource` extended with `uuid`.
- `OlympiadAttemptResource` (new) with nested `answers[]`.
- `NoteResource` extended with `color`. `FavoriteResource` extended
  with `color`.
- `NewsResource` (detail shape exposes `content`).

## Technical Notes

- Bundling these into one story is a deliberate trade-off. They are
  independent enough to ship separately but small enough that splitting
  them inflates ceremony (10 stories worth of plan/review/qa/audit for
  what amounts to ~150 lines of migration + ~25 endpoints). The
  acceptance criteria are sectioned so they can still be reviewed
  independently.
- Olympiad attempts schema keeps `chapters_label VARCHAR(32)` rather
  than re-deriving from the question's `(chapters_from, chapters_to)`.
  The label is the theme grouping key the client computes; persisting
  it makes user history queries trivial without joining back to the
  question table.
- The `qr_codes.reference` column is now nullable because not all QR
  rows are verse-targeted. The existing reference-lookup endpoint
  remains valid; it just queries `WHERE reference = ? AND reference IS
  NOT NULL`.
- `mobile_versions.kind` is an open-ended string rather than an enum so
  future kinds (e.g. `force_update`, `optional_update`) can be added
  without a migration.

## References

- MBA-023 schema reconcile foundation.
- MBA-031 ETL (devotional type backfill, news language default,
  Olympiad UUID backfill, mobile_versions seed).
- Admin MB-015 (UI alignment for all the catch-up items).
- Frontend MB-019 (devotional audio/video player; notes/favorites
  colour picker).
- Symfony DDL: `devotional_type`, `devotional_entry`, `mobile_version`,
  `collection`, `collection_topic`, `qr_codes`, `question`,
  `question_option`, `note`, `favorite`, `news` from production DDL
  2026-05-02.
