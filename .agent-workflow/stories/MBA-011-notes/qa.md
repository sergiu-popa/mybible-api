# QA: MBA-011-notes

**Verdict:** `QA PASSED`
**Branch:** `mba-011`
**Suite:** `make test` → **408 passed, 1257 assertions, 7.37s**
**Focused:** `make test filter=Notes` → 31 passed, 95 assertions
**Review gate:** iteration-2 `APPROVE`; 0 Critical / 0 unchecked Warnings.

## AC coverage

| AC | Requirement | Covering tests |
|---|---|---|
| 1 | `GET /api/v1/notes` paginated, newest first, `?book=` filter | `tests/Feature/Api/V1/Notes/ListNotesTest.php:19` (scoped to caller), `:32` (newest-first), `:51` (book filter), `:67` (invalid book), `:76` (pagination), `:93` (resource shape) |
| 2 | `POST /api/v1/notes` with `{reference, content}`, content ≤ 10 000, invalid reference → 422 | `tests/Feature/Api/V1/Notes/StoreNoteTest.php:17` (happy+canonical), `:39` (invalid ref), `:50` (multi-ref rejected), `:63`, `:72`, `:81`, `:92` (strip+measure), `:114` |
| 3 | `PATCH /api/v1/notes/{note}` owner updates content, reference immutable, cross-user 403 | `tests/Feature/Api/V1/Notes/UpdateNoteTest.php:18`, `:38` (reference ignored, DB unchanged), `:60` (403), `:75`, `:86`, `:97`, `:107` |
| 4 | `DELETE /api/v1/notes/{note}` owner only, 204 | `tests/Feature/Api/V1/Notes/DeleteNoteTest.php:17`, `:28` (403 + kept), `:39` (404) |
| 5 | Sanctum required on all four | `ListNotesTest.php:88`, `StoreNoteTest.php:127`, `UpdateNoteTest.php:116`, `DeleteNoteTest.php:47` |
| 6 | Ownership via Form Request `authorize()` | Cross-user `403` covered in `UpdateNoteTest.php:60` and `DeleteNoteTest.php:28`; `AuthorizedNoteRequest` delegates to `NotePolicy::manage` |
| 7 | Feature tests: CRUD, cross-user, invalid ref, length, pagination | All of `tests/Feature/Api/V1/Notes/*` |
| 8 | Unit tests for `CreateNoteAction`, `UpdateNoteAction`, `DeleteNoteAction` | `tests/Unit/Domain/Notes/Actions/CreateNoteActionTest.php:18`, `UpdateNoteActionTest.php:17`, `DeleteNoteActionTest.php:16` |

## Edge cases probed

- Multi-reference input (chapter range + verse list) → 422, no DB write (`StoreNoteTest.php:50`, `ValidReferenceTest.php:70,86`).
- Empty / gibberish / unknown-book reference → 422 (`ValidReferenceTest.php:35,48,59`).
- Content length boundary (post-strip measurement, 10 001 after strip) (`StoreNoteTest.php:92,114`; `StripHtmlTest.php:35,49`).
- `reference` field in PATCH body silently ignored; DB reference unchanged (`UpdateNoteTest.php:38-58`).
- Unknown note id → 404 for authed requests (`UpdateNoteTest.php:107`, `DeleteNoteTest.php:39`).
- Cross-user access returns `403`, not `404`, matching the Policy-based design (`UpdateNoteTest.php:60`, `DeleteNoteTest.php:28`).
- Unauthenticated access → 401 on all four routes.
- Invalid `?book=` filter → 422 (`ListNotesTest.php:67`).
- `per_page` pagination override respected (`ListNotesTest.php:76`).

## Regressions

None observed. Full suite green at 408 tests (baseline pre-MBA-011 suite grew by 31 Notes tests + the new rule/action units). No failures in MBA-004 subscription Form Request pattern, MBA-006 reference parser, or other shared primitives.

## Review follow-ups

All Critical + Warning items from iteration-1 review are ticked resolved in iteration-2 and verified against code:
- `ValidReference` now rejects `count !== 1` with tests (`app/Http/Rules/ValidReference.php` + new unit + feature tests).
- `PARSED_ATTRIBUTE_KEY` is `reference.parsed` (domain-neutral).

Non-blocking suggestions (Note `$guarded = []`, `forBook(?string)` signature, `newEloquentBuilder` return-type widening, controller-injected `ReferenceFormatter`, `StripHtmlTest` comment wording) remain as-is — all out-of-scope for QA.
