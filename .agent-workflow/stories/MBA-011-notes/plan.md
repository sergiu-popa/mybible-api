# Plan: MBA-011-notes

## Approach

Introduce a standalone `Notes` domain with a single `Note` model owning `{ user, reference, content }`, four CRUD endpoints, and per-field Actions. Reference validation and storage reuse MBA-006: a dedicated `Rules\ValidReference` Form Request rule parses input via `ReferenceParser` and raises `422` on failure, and the parsed `Reference` is re-canonicalised via `ReferenceFormatter::toCanonical()` before persistence so the DB always holds canonical form. Ownership uses the same Policy + scoped-base-FormRequest pattern as MBA-004 (`AuthorizedReadingPlanSubscriptionRequest` → `AuthorizedNoteRequest`).

## Open questions — resolutions

1. **Content format.** Plain text stored; Markdown rendered client-side. Strip HTML at validation via a dedicated `Rules\StripHtml` transform Rule (preferred over `strip_tags` inline — keeps the Form Request declarative and unit-testable).
2. **Length limit.** 10 000 chars (story AC 2); enforced by validation, not DB (`TEXT` column — ample headroom).
3. **Soft delete.** Out of scope per story. Flag as follow-up MBA-011a. Schema uses hard delete; `Note` model does NOT use `SoftDeletes`.

## Domain layout

```
app/Domain/Notes/
├── Models/Note.php
├── Actions/
│   ├── CreateNoteAction.php
│   ├── UpdateNoteAction.php
│   └── DeleteNoteAction.php
├── DataTransferObjects/
│   ├── CreateNoteData.php
│   └── UpdateNoteData.php
└── QueryBuilders/NoteQueryBuilder.php

app/Policies/NotePolicy.php

app/Http/
├── Controllers/Api/V1/Notes/
│   ├── ListNotesController.php
│   ├── StoreNoteController.php
│   ├── UpdateNoteController.php
│   └── DeleteNoteController.php
├── Requests/Notes/
│   ├── AuthorizedNoteRequest.php          (abstract base)
│   ├── ListNotesRequest.php
│   ├── StoreNoteRequest.php
│   ├── UpdateNoteRequest.php
│   └── DeleteNoteRequest.php
├── Resources/Notes/NoteResource.php
└── Rules/
    ├── ValidReference.php                 (parses via ReferenceParser)
    └── StripHtml.php                      (transform rule; strips tags before further validation)
```

## Key types

| Type | Role |
|---|---|
| `Note` (Eloquent) | `int id`, `int user_id`, `string reference` (canonical, e.g. `GEN.1:1.VDC`), `string book` (derived; 3-letter abbrev — stored for indexable `?book=` filter), `text content`, timestamps. Relations: `belongsTo User`. Scopes via `NoteQueryBuilder`. |
| `NoteQueryBuilder` | `forUser(User): self`; `forBook(string): self` (lowercases + compares case-insensitively to stored abbrev); `latest('created_at')` usage by controller. |
| `NotePolicy` | `manage(User, Note): bool` — `$note->user_id === $user->id`. Registered in `AuthServiceProvider` (or auto-discovery). |
| `AuthorizedNoteRequest` (abstract) | Mirrors `AuthorizedReadingPlanSubscriptionRequest`. `authorize()` resolves `{note}` route binding + authed user, delegates to `$user->can('manage', $note)`. Subclasses add `rules()`. |
| `CreateNoteData` (readonly) | `User $user`, `Reference $reference`, `string $canonicalReference`, `string $content`. Built from `StoreNoteRequest::toData()`. |
| `UpdateNoteData` (readonly) | `Note $note`, `string $content`. (Reference is immutable per AC 3.) |
| `CreateNoteAction::execute(CreateNoteData): Note` | Persists `user_id`, `reference` (canonical), `book` (derived from `Reference::$book`), `content`. No transaction needed — single insert. |
| `UpdateNoteAction::execute(UpdateNoteData): Note` | Updates `content` only; returns refreshed model. |
| `DeleteNoteAction::execute(Note): void` | `$note->delete()`. |
| `ValidReference` (Rule) | Receives the injected `ReferenceParser`; fails validation if parser throws `InvalidReferenceException`. Stores the parsed `Reference[]` on the request via a named attribute so the controller/Form Request can recover it without re-parsing. Consumed by `StoreNoteRequest::toData()`. |
| `StripHtml` (Rule) | Transform rule: replaces value with `strip_tags($value)` before length checks run. Consumed by `StoreNoteRequest` and `UpdateNoteRequest` (`content` field). |
| `NoteResource` | `id`, `reference` (canonical string), `book`, `content`, `created_at`, `updated_at`. |

## HTTP endpoints

| Method | Path | Controller | FormRequest | Resource | Auth |
|---|---|---|---|---|---|
| GET | `/api/v1/notes` | `ListNotesController` | `ListNotesRequest` | `NoteResource::collection` | `auth:sanctum` |
| POST | `/api/v1/notes` | `StoreNoteController` | `StoreNoteRequest` | `NoteResource` (201) | `auth:sanctum` |
| PATCH | `/api/v1/notes/{note}` | `UpdateNoteController` | `UpdateNoteRequest` | `NoteResource` (200) | `auth:sanctum` |
| DELETE | `/api/v1/notes/{note}` | `DeleteNoteController` | `DeleteNoteRequest` | — (204) | `auth:sanctum` |

Routes register under a `Route::middleware('auth:sanctum')->prefix('notes')->name('notes.')` group. No `scopeBindings()` — `Note` has no nested parent. Ownership is enforced by `AuthorizedNoteRequest::authorize()`, not by a route scope — preserves `403` (not `404`) for cross-user access per AC 3.

## Data & migrations

Single new migration `create_notes_table`:

| Column | Type | Notes |
|---|---|---|
| `id` | bigint pk | |
| `user_id` | **unsignedInteger** | Match `users.id` width — same reason as `reading_plan_subscriptions.user_id` (see existing migration). FK cascade on delete. |
| `reference` | `string(255)` | Canonical form; indexed for future reverse-lookup. |
| `book` | `string(3)` | Canonical 3-letter abbrev (e.g. `GEN`). Extracted at write time from the parsed `Reference`. Indexed compound with `user_id` for `?book=` filter. |
| `content` | `text` | |
| `created_at`, `updated_at` | timestamps | |

Indexes: `(user_id, created_at desc)` for pagination ordering; `(user_id, book)` for the book filter. No unique constraint on `(user_id, reference)` — duplicates are allowed (user may keep multiple notes on the same verse).

No Symfony `note` table exists in the current MySQL instance (verified via Boost schema tool) — this is a clean-slate port, not a reconciliation.

## Tasks

- [x] 1. Create migration `create_notes_table` with the columns and indexes specified above; `user_id` declared as `unsignedInteger` with an explicit FK to `users.id` cascading on delete.
- [x] 2. Create `App\Domain\Notes\Models\Note` with `$guarded = []`, relations (`user()` belongsTo), casts, and `newEloquentBuilder` returning `NoteQueryBuilder`. Add a `NoteFactory` that generates canonical references via `ReferenceFormatter::toCanonical` over a fake `Reference`.
- [x] 3. Create `App\Domain\Notes\QueryBuilders\NoteQueryBuilder` exposing `forUser(User)` and `forBook(string)`.
- [x] 4. Create `App\Policies\NotePolicy` with `manage(User, Note)`; wire it up (auto-discovery or `AuthServiceProvider::$policies`, matching the convention already used for `ReadingPlanSubscriptionPolicy`).
- [x] 5. Create `App\Http\Rules\ValidReference` that depends on `ReferenceParser` via constructor injection; on `passes()` failure raise the validator message derived from the `InvalidReferenceException::reason()`. Expose the parsed `Reference` on the request attributes under a named const.
- [x] 6. Create `App\Http\Rules\StripHtml` as a transform rule that mutates the validated value to `strip_tags($value)` before downstream rules run.
- [x] 7. Create DTOs `CreateNoteData` and `UpdateNoteData` (final readonly) per the Key types table.
- [x] 8. Create `CreateNoteAction`, `UpdateNoteAction`, `DeleteNoteAction` with the signatures in the Key types table. Unit-test each in isolation (happy path + any branching; no HTTP).
- [x] 9. Create abstract `App\Http\Requests\Notes\AuthorizedNoteRequest` whose `authorize()` resolves the `{note}` route parameter and the authed user, then returns `$user->can('manage', $note)`. Model on `AuthorizedReadingPlanSubscriptionRequest`.
- [x] 10. Create `ListNotesRequest` (no ownership gate — `authorize()` returns true; operates over the caller's own notes only via the query builder). Rules: `book` optional, 3-letter uppercase string matching `BibleBookCatalog::hasBook`. Expose `perPage()` (default 20) and `book(): ?string`.
- [x] 11. Create `StoreNoteRequest extends FormRequest` (no ownership gate — creating). Rules: `reference` required string with `ValidReference`; `content` required string with `StripHtml` then `max:10000`. `toData(): CreateNoteData` returns the DTO, pulling the pre-parsed `Reference` from request attributes.
- [x] 12. Create `UpdateNoteRequest extends AuthorizedNoteRequest`. Rules: `content` required string with `StripHtml` then `max:10000`. `reference` explicitly absent from rules (immutable per AC 3). `toData(Note): UpdateNoteData`.
- [x] 13. Create `DeleteNoteRequest extends AuthorizedNoteRequest` with `rules() === []`.
- [x] 14. Create `NoteResource` shaping `{ id, reference, book, content, created_at, updated_at }` (ISO-8601 timestamps).
- [x] 15. Create invokable controllers `ListNotesController`, `StoreNoteController`, `UpdateNoteController`, `DeleteNoteController` under `App\Http\Controllers\Api\V1\Notes`. Controllers only receive the request, call the Action, and return a Resource (or `response()->noContent()` for delete). `ListNotesController` composes `Note::query()->forUser($user)->forBook($request->book())->latest()->paginate($request->perPage())`.
- [x] 16. Register routes in `routes/api.php` under `Route::middleware('auth:sanctum')->prefix('notes')->name('notes.')->group(...)`. Use `Route::get/post/patch/delete` individually (no `apiResource` — the controllers are single-action invokables).
- [ ] 17. Feature tests (`tests/Feature/Notes/*`): index (pagination, newest-first, `?book=` filter, only caller's notes); store (happy path returns 201 + canonical reference; invalid reference → 422; content > 10 000 → 422; HTML stripped and length measured post-strip); update (owner success; cross-user → 403; `reference` field in body is silently ignored — asserted via DB value unchanged); delete (owner → 204; cross-user → 403; unknown id → 404); unauthenticated → 401 on all four routes.
- [ ] 18. Unit tests for `CreateNoteAction`, `UpdateNoteAction`, `DeleteNoteAction` — persistence + return shape only. No HTTP.
- [ ] 19. Unit tests for `ValidReference` (valid canonical → passes, gibberish → fails) and `StripHtml` (HTML stripped, plain text untouched).
- [ ] 20. Run `make lint-fix`, `make stan`, then `make test --filter=Notes`; finally run `make check` before marking the story ready for review.

## Risks & notes

- **Tripwire — owner-`authorize()` pattern.** The register lists 4 copies reaching threshold at 5; this story would push to 6. In practice those 4 copies already extend a shared `AuthorizedReadingPlanSubscriptionRequest` base (domain-scoped). The pattern being duplicated here is **the base-class shape**, not the inlined block. Proposal: create a parallel `AuthorizedNoteRequest` (task 9). If/when a third owner-gated resource appears, Improver should consider generalising to a trait `Tests\Concerns\AuthorizesRouteOwner` parameterised by the route-param name + policy ability. Flag for Improver review, not blocking this story.
- **`content` length measured post-strip.** `StripHtml` must run before `max:10000`. This is enforced by rule ordering (Laravel evaluates left-to-right for a single field) and covered by a test.
- **`book` column denormalisation.** Storing `book` alongside `reference` duplicates data (book is recoverable from the canonical string). Justification: `?book=` filter becomes an index hit instead of a `LIKE 'GEN.%'` prefix scan, and write-time cost is one `substr` on the already-parsed `Reference`. Document in the migration comment.
- **Reference immutability on PATCH.** Enforced by omitting `reference` from `UpdateNoteRequest::rules()` — `validated()` never contains it, so it cannot leak into the Action. Covered by a test that posts `reference` in the body and asserts the DB value is unchanged.
- **Sanitisation scope.** `strip_tags` is a client-compat courtesy, not a security boundary. Server never renders `content` as HTML; XSS is a client concern per Technical Notes.
- **No soft delete.** Deletes are permanent. Follow-up story MBA-011a can add `SoftDeletes` + a 30-day cleanup job if product wants recovery.
- **Deferred Extractions register update.** On story close, Improver should revise the owner-`authorize()` entry: the tripwire as written ("checks `subscription->user_id === request->user()->id`") no longer describes any copy (all 4 delegate to a Policy via a domain base class). Re-word the pattern around "scoped `AuthorizedXyzRequest` base + Policy" and reset copies to 2 (ReadingPlans + Notes); re-evaluate threshold.
