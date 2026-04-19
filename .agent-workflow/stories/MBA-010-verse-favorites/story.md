# Story: MBA-010-verse-favorites

## Title
Verse favorites and favorite categories

## Status
`draft`

## Description
Users can bookmark verses (or verse ranges) and organize them into
user-owned categories. Separate from note-taking (MBA-011) and reading
progress (MBA-009) — this is a tagged bookmark.

Symfony source:
- `FavoriteController::index/store/update/destroy()`
- `FavoriteCategoryController::index/store/update/destroy()`

Two Doctrine entities behind them: `Favorite` (a bookmarked passage) and
`FavoriteCategory` (user-scoped tag). Both use the Symfony reference
parser (MBA-006) for the `passage` field.

## Acceptance Criteria

### Categories
1. `GET /api/v1/favorite-categories` — list the caller's categories,
   paginated.
2. `POST /api/v1/favorite-categories` — create with `{ name, color? }`.
   `name` is unique per user. Returns `201`.
3. `PATCH /api/v1/favorite-categories/{category}` — rename or recolor,
   owner only. `name` uniqueness per user preserved. `403` if not owner.
4. `DELETE /api/v1/favorite-categories/{category}` — owner only. Any
   favorites assigned to it cascade to the default "Uncategorized"
   (server-managed category, not user-owned). Returns `204`.

### Favorites
5. `GET /api/v1/favorites` — paginated list of the caller's favorites.
   Supports `?category={id}` and `?book={abbr}`.
6. `POST /api/v1/favorites` — create with
   `{ reference, category_id?, note? }`. `reference` is validated via the
   MBA-006 parser; invalid ⇒ `422`.
7. `PATCH /api/v1/favorites/{favorite}` — owner can change `category_id`
   or `note`. `reference` is immutable post-creation (create a new
   favorite if you want a different passage). `403` if not owner.
8. `DELETE /api/v1/favorites/{favorite}` — owner only. Returns `204`.

### Authorization
9. All endpoints require Sanctum. Ownership enforced via Form Request
   `authorize()` (tripwire in `.agent-workflow/CLAUDE.md` counts this
   pattern — check the current tally before copying).

### Tests
10. Feature tests: full CRUD on categories and favorites, cross-user
    access denied, duplicate category name per user rejected, favorite
    creation with invalid reference, cascade-to-uncategorized on category
    delete.
11. Unit tests for each Action.

## Scope

### In Scope
- Two resource routes (`favorites`, `favorite-categories`).
- `Favorite` and `FavoriteCategory` Eloquent models.
- Actions, DTOs, Form Requests, API Resources, Feature tests.
- "Uncategorized" default category logic.

### Out of Scope
- Sharing favorites between users.
- Exporting favorites to PDF / email.
- Devotional favorites (MBA-012) and hymnal favorites (MBA-013) —
  separate tables, separate stories.

## Technical Notes

### "Uncategorized" fallback
Option A: real row in `favorite_categories` with `user_id = NULL` +
`is_default = true`, displayed to every user.
Option B: virtual category — `favorites.category_id = NULL` means
uncategorized, and the API surfaces it as a synthetic category.

Recommend B — no bootstrap seeding required, no NULL-owned row
confusion. Architect to confirm.

### Reference storage
Store the canonical form (`GEN.1:1-3.VDC`) in the DB column. Re-parse
on read for display / response shape. Keeps the DB a source of truth
and lets the parser evolve without data migrations.

### Owner authorize() tripwire
`.agent-workflow/CLAUDE.md` shows 4 copies of the owner-gated Form
Request pattern. Extracting at 5 — this story introduces several new
request classes and will hit that threshold. Check the tripwire and
plan the extraction AS PART OF this story if the counter crosses 5.

## Dependencies
- **MBA-005** (auth + users).
- **MBA-006** (reference parser).

## Open Questions for Architect
1. **Uncategorized as real or virtual category.** Recommend virtual
   (B above). Product confirms.
2. **Reference immutability.** The spec above forbids editing a
   favorite's reference. Symfony allowed it via PUT — do we care? If
   product wants to keep edit, drop that rule; the Action is
   straightforward either way.
3. **Tripwire action.** With this story almost certainly crossing the
   5-copy extract threshold for owner `authorize()`, do the extraction
   in-story or spin a standalone refactor story? Recommend in-story —
   cheaper than context-switching.
