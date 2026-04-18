# Story: MBA-001-reading-plan-catalog

## Title
Reading Plans — public catalog (browse + view)

## Status
`qa-passed`

## Description
A **Reading Plan** is a structured, multi-day guide that combines rich text
content and Bible passage references. Plans are multilingual (`name`,
`description`, `image`, `thumbnail`, and HTML fragments are translated per
language); Bible references are translation-agnostic and stored as global
reference strings.

This story delivers only the **read-only catalog**: the database tables
for plans/days/fragments, the language-resolution layer, and the two read
endpoints. Both routes are gated by the `api-key` middleware delivered in
MBA-002 (no user context needed — public catalog, app-level credential
only). Subscriptions, day completion, and lifecycle are split into MBA-003
and MBA-004.

## Acceptance Criteria

### Browsing
1. A client can list published reading plans with pagination (default
   `per_page = 15`, max `100`), filtered by language.
2. A client can view a single reading plan with all its days and fragments,
   filtered by language.
3. Multilingual fields (`name`, `description`, `image`, `thumbnail`, `html`
   fragments) return the requested language; if unavailable, fall back to `en`.
4. `references`-type fragments are returned as-is (raw strings) regardless of
   the requested language. A later story will resolve references into actual
   verse text — out of scope here.
5. Drafts and unpublished plans are never returned by the public endpoints.
6. Unknown plan slug returns `404` with the standard JSON error envelope.
7. The `language` query parameter accepts any of the supported languages
   (`en`, `ro`, `hu`); other values fall through to the `en` fallback.
8. Requests missing `X-Api-Key` or carrying an unknown key return `401`
   with the standard JSON error envelope (enforced by the `api-key`
   middleware from MBA-002).

### Out of acceptance criteria (deferred)
- Subscription data on plan responses: deferred to MBA-003.

## Scope

### In Scope
- Three database tables: `reading_plans`, `reading_plan_days`,
  `reading_plan_day_fragments`. Soft deletes on `reading_plans`.
- Two endpoints: `GET /api/v1/reading-plans`, `GET /api/v1/reading-plans/{plan:slug}`.
- `LanguageResolver` (JSON map → string with `en` fallback).
- `Language` enum (`en`, `ro`, `hu`).
- Factories for the three tables and a 7-day published seed plan in `en`+`ro`
  (Hungarian content omitted — fallback-to-en path is exercised by tests).

### Out of Scope
- Subscriptions and per-user data (MBA-003).
- Reschedule, finish, abandon (MBA-004).
- User-context auth (`api-key-or-sanctum`) — catalog routes use the
  app-level `api-key` middleware only.
- Admin CRUD for creating/editing plans (separate story).
- Caching, notifications, metrics dashboards.

## Technical Notes

### Bible reference format
References are stored as JSON arrays of strings following the format
`BOOK.CHAPTER[-CHAPTER_END][:VERSE_START[-VERSE_END]]`. Examples: `GEN.1-2`,
`MAT.5:27-48`, `PRO.1:1-6`. Stored and returned as plain strings; **no
parsing or validation** in this story. Book-code validation and
reference-to-verse resolution live in the future Bible-content domain.

### Multilingual storage
`name`, `description`, `image`, `thumbnail`, and html-type fragment `content`
are JSON columns shaped as `{ "en": "...", "ro": "...", "hu": "..." }`.
`image` and `thumbnail` hold full URLs to assets stored in DigitalOcean Spaces
(S3-compatible); the API stores and returns the URL as-is.

### Supported languages
`en`, `ro`, `hu`. Encoded as `App\Domain\Shared\Enums\Language`. Fallback
language is always `en`.

### Day model
Days are identified by `position` only — no `name` column for now. May change
in a future story.

### Slug
`slug` is mutable — there is no contract that it stays stable across edits.
Clients should use `id` for any internal references they cache.

## Dependencies
- MBA-002 (auth foundation) — required for the `api-key` middleware alias
  applied to both catalog routes. MBA-002 has shipped.

## Mockups / References
- Original combined feature spec: `reading-plan-feature-spec.md`.
- Bible book codes follow a future predefined set (e.g. `GEN`, `EXO`, `MAT`,
  `PSA`, `PRO`); validation is deferred.
