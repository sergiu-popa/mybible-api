# Plan: MBA-009-reading-progress

## Approach

Add a new `ReadingProgress` domain under `App\Domain\ReadingProgress` with its own table, two lifecycle Actions (`Toggle`, `Reset`), and three Sanctum-only endpoints nested under `/api/v1/reading-progress`. Use the MBA-006 `BibleBookCatalog` (exposed via a dedicated `BibleReferenceRule` Domain rule class) for book + chapter validation; `verse` is a plain positive integer (no verse-max catalog exists). Use `verse = 0` as the whole-chapter sentinel so a single composite unique index `(user_id, version, book, chapter, verse)` enforces toggle idempotency under MySQL NULL semantics.

## Open questions — resolutions

1. **Whole-chapter sentinel.** `verse = 0` on a NOT NULL column. Keeps the composite unique index simple; no generated columns, no MySQL NULL-distinct footgun. `ReadingProgressResource` maps `0 → null` on output to preserve the story's documented JSON shape.
2. **Reset scope.** `DELETE /api/v1/reading-progress` wipes every entry for the caller across all versions by default; `?version={abbr}` narrows to a single version. Matches AC 11/13.
3. **Soft-delete.** Hard-delete only (matches Symfony). No `deleted_at` column.
4. **Version validation.** No Bible version catalog exists in this repo yet, so `version` is validated structurally only (`string|regex:/^[A-Z0-9]{2,10}$/`). When a `BibleVersionCatalog` ships (future story), wire it into the same rule. Flag in Risks.
5. **Filter scope on list.** `?version` and `?book` filter; both fields stored uppercase. Normalize to uppercase in the Form Request before the query runs (consistency with stored rows).

## Domain layout

```
app/Domain/ReadingProgress/
├── Models/
│   └── ReadingProgress.php                     # Eloquent model
├── Actions/
│   ├── ToggleReadingProgressAction.php         # insert-or-delete + chapter-clears-verses cascade
│   └── ResetReadingProgressAction.php          # delete all / delete by version
├── DataTransferObjects/
│   ├── ToggleReadingProgressData.php           # user, version, book, chapter, verse (0 = whole chapter)
│   └── ResetReadingProgressData.php            # user, ?version
├── QueryBuilders/
│   └── ReadingProgressQueryBuilder.php         # forUser(), forVersion(), forBook()
└── Rules/
    └── BibleReferenceRule.php                  # validation rule adapter over BibleBookCatalog

app/Http/
├── Controllers/Api/V1/ReadingProgress/
│   ├── ListReadingProgressController.php
│   ├── ToggleReadingProgressController.php
│   └── ResetReadingProgressController.php
├── Requests/ReadingProgress/
│   ├── ListReadingProgressRequest.php
│   ├── ToggleReadingProgressRequest.php
│   └── ResetReadingProgressRequest.php
└── Resources/ReadingProgress/
    └── ReadingProgressResource.php
```

## Key types

| Type | Role |
|---|---|
| `ReadingProgress` (Eloquent model) | `user_id`, `version`, `book`, `chapter`, `verse` (int, 0 = whole chapter), `read_at`. `belongsTo(User)`. Custom builder. No soft deletes. Casts `read_at` to `datetime`. |
| `ReadingProgressQueryBuilder` | `forUser(User)`, `forVersion(string)`, `forBook(string)`. Consumed by `ListReadingProgressController` and `ResetReadingProgressAction`. |
| `ToggleReadingProgressData` (readonly) | `User $user`, `string $version`, `string $book`, `int $chapter`, `int $verse` (0 = whole chapter). |
| `ResetReadingProgressData` (readonly) | `User $user`, `?string $version`. |
| `ToggleReadingProgressAction` | Wraps in `DB::transaction`. If a row matches the tuple, hard-delete and return `null`. Otherwise, if `verse === 0` (whole chapter) delete all verse-level rows for the chapter first, then insert the whole-chapter row and return the model. Return type: `?ReadingProgress` — `null` signals deletion to the controller. |
| `ResetReadingProgressAction` | Deletes `forUser($user)` (optionally `forVersion(...)`). Returns the number of rows affected (unused by 204 response; handy for logs/tests). |
| `BibleReferenceRule` | Invokable validation rule checking `BibleBookCatalog::hasBook($book)` and `$chapter <= BibleBookCatalog::maxChapter($book)`. Used in `ToggleReadingProgressRequest` via cross-field `after` rules (validator closures) since the rule needs both `book` and `chapter` to evaluate. |
| `ListReadingProgressRequest` | Validates `version` (regex), `book` (regex), `per_page` (1..500 default 100). Exposes `perPage()`, `version()`, `book()` accessors; uppercases inputs. |
| `ToggleReadingProgressRequest` | Validates `version`, `book`, `chapter` (int ≥ 1), `verse` (nullable int ≥ 1). Cross-field validator closure applies `BibleReferenceRule`. `toData(): ToggleReadingProgressData` — maps missing `verse` to sentinel `0`. Uppercases `version` and `book`. `authorize()` returns `true` (Sanctum already guards the route; no resource in the URL to own). |
| `ResetReadingProgressRequest` | Validates optional `version` (regex). `toData(): ResetReadingProgressData`. |
| `ReadingProgressResource` | Returns `{ version, book, chapter, verse, read_at }`. Maps stored `verse = 0` → output `null`. `read_at` as ISO-8601 string. |
| `ListReadingProgressController` | Builds query via `ReadingProgress::query()->forUser($user)` + optional filters, `orderByDesc('read_at')`, paginates at `$request->perPage()`, returns `ReadingProgressResource::collection(...)`. |
| `ToggleReadingProgressController` | Invokes action; returns `201 Created` with `ReadingProgressResource` on insert, `200 OK` with `{"deleted": true}` JSON on delete. |
| `ResetReadingProgressController` | Invokes action; returns `204 No Content` via `response()->noContent()`. |

## HTTP endpoints

| Method | Path | Controller | Request | Resource | Auth | Status codes |
|---|---|---|---|---|---|---|
| GET | `/api/v1/reading-progress` | `ListReadingProgressController` | `ListReadingProgressRequest` | `ReadingProgressResource` (collection) | `auth:sanctum` | 200, 401, 422 |
| POST | `/api/v1/reading-progress/toggle` | `ToggleReadingProgressController` | `ToggleReadingProgressRequest` | `ReadingProgressResource` (single) or `{deleted: true}` | `auth:sanctum` | 201 (insert), 200 (delete), 401, 422 |
| DELETE | `/api/v1/reading-progress` | `ResetReadingProgressController` | `ResetReadingProgressRequest` | — | `auth:sanctum` | 204, 401, 422 |

Routes grouped under `Route::middleware('auth:sanctum')->prefix('reading-progress')->name('reading-progress.')`. No route-model binding — all three endpoints key off `$request->user()`, so no scope-binding decision is required.

## Data & migrations

One new migration: `create_reading_progress_table`.

| Column | Type | Notes |
|---|---|---|
| `id` | `bigIncrements` | primary key |
| `user_id` | `unsignedInteger` | FK to `users.id`, `cascadeOnDelete` (matches MBA-003 precedent — users table uses `increments`, not `bigIncrements`) |
| `version` | `string(10)` | Bible version abbreviation, uppercase |
| `book` | `char(3)` | canonical abbrev from `BibleBookCatalog::BOOKS` |
| `chapter` | `unsignedSmallInteger` | ≥ 1 |
| `verse` | `unsignedSmallInteger` | ≥ 0; `0` = whole chapter sentinel |
| `read_at` | `timestamp` | not null; set by Action on insert |
| `created_at` / `updated_at` | `timestamps` | |

Indexes:
- Composite unique: `(user_id, version, book, chapter, verse)` — enforces idempotent toggle.
- Composite: `(user_id, version, book)` — supports list-filter path.

No `deleted_at` (hard-delete semantics).

## Tasks

- [ ] 1. Create migration `create_reading_progress_table` with the columns and indexes above; add the user FK matching the `unsignedInteger` width from the users table.
- [ ] 2. Create `App\Domain\ReadingProgress\Models\ReadingProgress` with `user()` relation, `$guarded = []`, `read_at` cast, and custom `newEloquentBuilder` returning `ReadingProgressQueryBuilder`. Ship a factory + unit-testable PHPDoc property block.
- [ ] 3. Create `App\Domain\ReadingProgress\QueryBuilders\ReadingProgressQueryBuilder` with `forUser()`, `forVersion()`, `forBook()`. Unit test covers each filter plus a combined-filters case under `RefreshDatabase`.
- [ ] 4. Create `App\Domain\ReadingProgress\DataTransferObjects\ToggleReadingProgressData` (readonly) and `ResetReadingProgressData` (readonly).
- [ ] 5. Create `App\Domain\ReadingProgress\Rules\BibleReferenceRule` as an invokable rule taking `book` + `chapter`, delegating to `BibleBookCatalog::hasBook()` / `::maxChapter()`; emits distinct messages for unknown-book vs. chapter-out-of-range. Unit test covers both branches.
- [ ] 6. Create `App\Domain\ReadingProgress\Actions\ToggleReadingProgressAction` implementing the insert-or-delete semantics and chapter-clears-verses cascade inside `DB::transaction`. Unit test covers: first-call insert (returns model, `read_at` set), second-call delete, chapter-level toggle clears prior verse-level rows, toggle of a non-existent chapter-level entry with lingering verse rows still inserts + clears in one transaction, same tuple is idempotent under concurrent double-submit (simulate via direct duplicate insert → FK/unique catches it).
- [ ] 7. Create `App\Domain\ReadingProgress\Actions\ResetReadingProgressAction` supporting full-wipe and version-scoped wipe. Unit test covers both modes and "other user's rows untouched".
- [ ] 8. Create `App\Http\Requests\ReadingProgress\ListReadingProgressRequest` with version/book regex rules, `perPage()` (default 100, max 500), and uppercasing accessors.
- [ ] 9. Create `App\Http\Requests\ReadingProgress\ToggleReadingProgressRequest` with base rules plus a closure validator applying `BibleReferenceRule` across `book + chapter`; implement `toData()` mapping missing verse to sentinel `0`. Unit test covers: valid input, unknown book 422, chapter-out-of-range 422, negative verse 422, missing-verse → data carries `0`.
- [ ] 10. Create `App\Http\Requests\ReadingProgress\ResetReadingProgressRequest` with optional `version` regex rule and `toData()`.
- [ ] 11. Create `App\Http\Resources\ReadingProgress\ReadingProgressResource` shaping `{ version, book, chapter, verse, read_at }` and mapping stored `verse = 0` → output `null`.
- [ ] 12. Create `App\Http\Controllers\Api\V1\ReadingProgress\ListReadingProgressController` returning a paginated resource collection filtered by `forUser` + optional version/book.
- [ ] 13. Create `App\Http\Controllers\Api\V1\ReadingProgress\ToggleReadingProgressController` dispatching the Action and branching response: `201 + ReadingProgressResource` on insert, `200 + {deleted: true}` on delete.
- [ ] 14. Create `App\Http\Controllers\Api\V1\ReadingProgress\ResetReadingProgressController` returning `response()->noContent()`.
- [ ] 15. Register the three routes under `Route::middleware('auth:sanctum')->prefix('reading-progress')->name('reading-progress.')` in `routes/api.php`.
- [ ] 16. Add a `Tests\Concerns\InteractsWithReadingProgress` trait (factory helpers: `givenReadingProgressFor($user, array $overrides = [])`). Consumer: every reading-progress feature test in the next tasks.
- [ ] 17. Feature test `ListReadingProgressTest` covering: 401 when anonymous, 200 returns only the caller's rows, `?version` filter, `?book` filter, pagination `meta`/`links` shape, default 100 / max 500 enforced.
- [ ] 18. Feature test `ToggleReadingProgressTest` covering: 401 anonymous, 201 insert shape (`read_at` present), 200 delete shape (`{deleted: true}`), chapter-level toggle wipes pre-existing verse-level rows, unknown book / out-of-range chapter → 422, verse-less toggle stores sentinel, second toggle with same tuple deletes.
- [ ] 19. Feature test `ResetReadingProgressTest` covering: 401 anonymous, 204 full wipe, 204 `?version=VDC` wipes only that version, 204 when no rows exist, other users' rows untouched.
- [ ] 20. Run `make lint-fix`, `make stan`, `make test --filter=ReadingProgress`, then full `make test` before handoff.

## Risks & notes

- **No Bible version catalog yet.** `version` is validated as a structural string only. If an invalid `VER` gets through, the list endpoint will just return no rows and toggle will store an unreachable tuple. Low risk; tracked for a later "BibleVersionCatalog" story that will re-target `BibleReferenceRule`.
- **Sentinel `0` for whole chapter.** The API exposes `verse: null` in JSON (both request input and response output). Storage detail is intentionally leaky only to tests; product-facing contract is null. Document with an inline comment on the model/migration.
- **Verse-level catalog missing.** No max-verse lookup exists in `BibleBookCatalog`. Consistent with MBA-006 (story AC 8 of that port preserved this). Out-of-range verse numbers (e.g. `GEN 1:999`) will be accepted — matches Symfony and is acceptable for a bookmark layer.
- **Deferred Extractions tripwire.** Does not trigger:
  - No owner-`authorize()` duplication added — none of the three Form Requests use the subscription-owner pattern, and no new resource has a `{model}` URL parameter requiring owner checks (count stays at 4/5).
  - No new lifecycle Action with `withProgressCounts()` (count stays at 2/3).
- **Toggle concurrency.** Two simultaneous toggles for the same tuple can race. The composite unique index guarantees at-most-one row; the transaction + `forUpdate()`-style lookup inside the Action (or a unique-violation catch that re-reads) keeps the outcome deterministic. Engineer: pick the simplest correct form and pin it with a dedicated unit test (task 6).
- **Pagination cap.** Default 100 / max 500 departs from the `ListReadingPlansRequest` default (15 / max 100) because the story's AC 1 locks these numbers.
- **Authorization posture.** All three endpoints scope by `$request->user()->id`; no cross-user access vector exists, so no Policy/Gate is needed. Explicitly skipping Policy per the terseness rule.
