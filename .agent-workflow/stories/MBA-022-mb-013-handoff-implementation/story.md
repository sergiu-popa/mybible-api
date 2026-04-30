# Story: MBA-022-mb-013-handoff-implementation

## Title

API-side implementation of the MB-013 handoff backlog (admin/schema improvements raised by MB-001…MB-012)

## Status

`done`

## Description

Companion story on the API side for the admin-repo handoff backlog
[`MB-013-api-db-improvements`](../../../../admin/.agent-workflow/stories/MB-013-api-db-improvements/story.md).
That admin story produced — and curates — `improvements.md`, a structured
backlog of schema deltas, missing endpoints, policy gaps, performance
opportunities, and tech-debt cleanups discovered while writing the admin
rewrite stories MB-001…MB-012. The admin story explicitly produces no
admin-side code; **this** story is the API-side delivery of a subset of
those items.

Scope is **the items implemented on branch `mb-013-api-improvements`**, not
the whole backlog — several items remain `proposed` in `improvements.md`
and will be picked up in follow-up stories. The branch closes every P1 in
the handoff plus a handful of P2/P3 items the engineer rolled in while the
context was hot.

`improvements.md` is the single source of truth for status. This story
references it by item ID (S-NN, E-NN, P-NN, etc.) rather than restating
each item. The append log inside `improvements.md` already records which
commit shipped each item.

## Acceptance Criteria

### Schema

1. `users.is_super` boolean (default `false`) added; cast on `User`;
   factory `super()` state; oldest `admin`-roled user promoted to
   `is_super = true` by migration. (Item **S-10**.)
2. `users.languages` JSON array column added alongside legacy
   `users.language`; backfill maps 3-char legacy codes to 2-char codes;
   model accessor exposes the array; legacy column preserved until admin
   and frontend stop reading it. (Item **S-01**.)
3. `users.ui_locale` and `users.is_active` columns added; cast on `User`.
   (Backing for **E-07**.)
4. `resource_categories.position` and `educational_resources.position`
   columns added with appropriate composite indexes; backfilled by
   `id ASC` (per category for resources). Public list inside a category
   continues to surface newest-first by `published_at`; `position` is the
   admin reorder concern. (Items **S-07** + **S-08**.)
5. `news.image_path` column renamed to `news.image_url`; resource still
   resolves the stored relative path to an absolute URL. (Item **S-04**.)
6. `daily_verse.language` (nullable, 2-char) column added; existing rows
   stay `NULL` (= "all languages") to preserve legacy behaviour. Resource
   exposes `language` (nullable). (Item **S-09**.)
7. `users.roles` normalized to a single `admin` value: legacy
   `ROLE_EDITOR` collapses into `admin`, duplicate roles deduplicated.
   Migration is reversible (forward-only data normalization with no
   destructive `down()`). (Item **C-01**.)
8. `import_jobs` table created (id, type, status, progress, payload,
   error, timestamps). Backs the import-job polling endpoint. (Backing
   for **E-04**.)
9. `olympiad_questions.position` column added with appropriate index;
   backfilled by `id ASC` per theme tuple. (Backing for **E-02
   Olympiad**.)

### Endpoints

10. All admin endpoints land under the `/api/v1/admin/*` prefix gated by
    the `auth:sanctum` + scoped admin middleware (`admin` for content
    ops, `super-admin` for platform-wide ops). (Item **E-01**.)
11. `/api/v1/admin/users` CRUD endpoints implemented (gated by
    `super-admin`):
    - `GET /` — list admins.
    - `POST /` — create admin (random bcrypt password seeded; real
      password set via password-reset email).
    - `PATCH /{user}/enable`, `PATCH /{user}/disable` — disable revokes
      every active Sanctum token for the target.
    - `POST /{user}/password-reset` — sends reset email.
    (Item **E-09**.)
12. Reorder endpoints, full-array idempotent and transaction-wrapped:
    - `POST /api/v1/admin/resource-categories/reorder` and
      `POST /api/v1/admin/resource-categories/{category}/resources/reorder`.
    - `POST /api/v1/admin/sabbath-school/lessons/{lesson}/segments/reorder`
      and
      `POST /api/v1/admin/sabbath-school/segments/{segment}/questions/reorder`.
    - `POST /api/v1/admin/olympiad/themes/{book}/{chapters}/{language}/questions/reorder`.
    (Item **E-02** — Resources, Sabbath School, Olympiad. Devotional
    types reorder remains proposed.)
13. `GET /api/v1/auth/me` payload enriched with `languages[]`,
    `ui_locale`, `is_super`, `active`. Legacy `language` field preserved
    for existing consumers. (Item **E-07**.)
14. `POST /api/v1/admin/references/validate` — validation-only endpoint
    that mirrors existing reference parsing logic; no side effects.
    (Item **E-03**.)
15. `GET /api/v1/admin/imports/{importJob}` — import-job status polling
    endpoint with standardized response (status, progress, error
    details). (Item **E-04**.)
16. `POST /api/v1/admin/uploads/presign` — issues presigned S3 upload
    URLs with content-type and size constraints. Triggers the
    object-cleanup job when an upload is abandoned. (Item **E-06**.)
17. Olympiad public endpoints (`GET /api/v1/olympiad/themes` and
    `GET /api/v1/olympiad/themes/{book}/{chapters}/{language}`) honour
    the new `position` column for question ordering. (Backing for
    **E-02 Olympiad**.)
18. Olympiad theme aggregation contract documented (status, counts,
    scoring, traversal). (Item **E-10**.)

### Policies & authorization

19. `EnsureAdmin` middleware (alias `admin`) registered: 401 without a
    Sanctum bearer, 403 for authenticated non-admins (membership of
    `"admin"` in `users.roles`). (Item **P-02**.)
20. `EnsureSuperAdmin` middleware (alias `super-admin`) registered: 401
    without a bearer, 403 for non-admins or admins with
    `is_super = false`. Applied on `/api/v1/admin/users/*` today; ready
    for Bible catalog and Mobile Versions admin endpoints when those
    land. (Item **P-03**.)
21. `User::canManageLanguage(string $code): bool` and
    `User::canManageLanguageless(): bool` helpers added. Super-admins
    pass both; non-super admins are gated by their per-user
    `languages[]` set. Policies and language-scoped admin controllers
    must call these helpers. (Item **P-01**.)

### Performance

22. `Cache-Control` response headers added on read-mostly public
    endpoints (Bible catalog reads, daily-verse, public news listing,
    public Sabbath School / Olympiad / Educational Resources reads).
    HTTP-cache only; application-level Valkey caching is a separate
    story (`MBA-021-public-read-caching`). (Item **Perf-02**.)

### Cleanup / tech debt

23. Deletion of an `EducationalResource` schedules an S3 object cleanup
    job (`DeleteUploadedObjectJob`) so storage doesn't leak. Soft
    deletes do **not** purge S3 — only hard deletes do. (Item **C-03**.)

### Tests

24. Each new endpoint has a feature test exercising auth,
    authorization, validation errors, the happy path, and the relevant
    edge cases (e.g. reorder with mismatched IDs returns 422; disable
    revokes tokens; `is_super=false` gets 403 on super-only routes).
25. Each schema delta has a migration / model test asserting the column
    exists, the cast is applied, and the helper or scope works
    end-to-end (`UserIsSuperTest`, `UserLanguagesTest`,
    `UserLanguageScopeTest`, `NormalizeUsersRolesMigrationTest`).
26. Middleware tests cover the four states explicitly: missing bearer
    (401), wrong role (403), correct role (200), and (for
    `EnsureSuperAdmin`) admin-without-`is_super` (403).

## Scope

### In Scope

- Items closed by branch `mb-013-api-improvements`: **S-01, S-04, S-07,
  S-08, S-09, S-10, E-01, E-02 (Resources + Sabbath School + Olympiad),
  E-03, E-04, E-06, E-07, E-09, E-10, P-01, P-02, P-03, Perf-02, C-01,
  C-03**.
- The two backing schema deltas not in `improvements.md` but required to
  ship E-07 (`users.ui_locale`, `users.is_active`) and E-04
  (`import_jobs` table) and E-02 Olympiad (`olympiad_questions.position`).

### Out of Scope

- **S-02** (drop `users.salt`) and **S-03** (`mobile_news.language`):
  confirmed already shipped in earlier API migrations during the MB-013
  audit; no work needed in this branch. See `improvements.md` Tracking
  fields for the prior commits.
- **E-08** (profile / password / avatar): confirmed already shipped under
  `/api/v1/profile/*`. Admin client should consume those; no `/auth/*`
  alias added.
- Items still `proposed` after this branch lands: **S-05, S-06**, **E-02
  Devotional types reorder**, **E-05**, **Perf-01**, **C-02**. They will
  be picked up in follow-up stories under their own MBA-* IDs.
- Application-level Valkey caching (covered by `MBA-021`).

## API Contract Required

All endpoints listed in AC §10–18 are produced by this story. Each is
covered by a feature test and exposed under `/api/v1` as documented in
the `improvements.md` Tracking fields.

## Technical Notes

- `improvements.md` (admin repo) is updated on each commit: the
  `Tracking` field of each shipped item now points at the API commit
  hash, and the **Append log** entry dated 2026-04-30 enumerates the
  full set.
- Branch shape: 19 commits, one item per commit (or item-pair where the
  schema and the endpoint that uses it ship together). This keeps the
  per-item review surface small and lets the reviewer cross-reference
  each `[MB-013] <ID>:` commit subject directly to `improvements.md`.
- The `super-admin` middleware is wired on `/api/v1/admin/users/*` today
  but is not yet applied on Bible catalog and Mobile Versions admin
  endpoints — those don't exist on the API yet. Apply when those
  capabilities land (no work in this story).
- `User::canManageLanguage()` is not yet called from policies or
  controllers — those touchpoints don't exist yet either. The helpers
  ship now so that future language-scoped admin endpoints can call them
  by name (avoiding ad-hoc reimplementation).
- Reorder payloads are full-array idempotent (`{ ids: [] }`) — chosen
  per the `improvements.md` recommendation. If reorder ever becomes a
  hot path, switch to fractional indexing (item Perf-01, deferred).

## Mockups / References

- Admin handoff backlog:
  [`MB-013/improvements.md`](../../../../admin/.agent-workflow/stories/MB-013-api-db-improvements/improvements.md).
- Admin story:
  [`MB-013/story.md`](../../../../admin/.agent-workflow/stories/MB-013-api-db-improvements/story.md).
- Branch: `mb-013-api-improvements` (this repo).
- Companion caching story: `MBA-021-public-read-caching`.
