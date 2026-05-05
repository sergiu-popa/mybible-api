# Audit — MBA-029-commentary-ai-workflow-and-sqlite-export

Scope: post-QA holistic pass over commits `ca9e934` ("Implement commentary
AI workflow + SQLite export") and `2f251d0` ("Address review.md W1–W5 +
S1–S6"). Re-checked architecture compliance, code quality, security,
performance, API design, and test coverage on top of the work the Code
Reviewer and QA already verified.

## Verdict

**`PASS`** — every Critical/Warning is resolved or consciously deferred
with a recovery path; full test suite green (**1364 passed**, 5074
assertions; commentary slice **89 passed**, 277 assertions); lint clean;
PHPStan clean.

## Issue table

| # | Issue | Location | Severity | Status | Resolution |
|---|---|---|---|---|---|
| A1 | `ExportCommentarySqliteAction` silently drops source content when `source.language` is outside `CommentarySqliteSchemaBuilder::ALLOWED_LANGUAGES` (`ro/en/hu/es/fr/de/it`). The artefact's `meta.source_language` would record e.g. `pt`, but no `content_pt` column exists, so the source HTML never lands anywhere — and mobile readers see only the translations. The story §AC20 explicitly enumerates the seven languages, so this is a real boundary, not a missing schema column. | `app/Domain/Commentary/Actions/ExportCommentarySqliteAction.php:45` | Warning | Fixed | Added an early guard that throws `RuntimeException` with the offending language and the allow-list. The export job already catches `Throwable` and transitions the import job to `Failed` with the message, so the admin sees a clean explanation instead of an empty-looking file. New unit test `test_export_rejects_source_language_outside_allow_list` exercises the path. |
| A2 | `SubmitCommentaryErrorReportAction` carried the same dead null branch the Reviewer's S2 fixed for `UpdateCommentaryErrorReportStatusAction`: it `find`s the parent `CommentaryText`, then throws a generic `RuntimeException` if missing — but the controller already model-binds `{text}` so the row is proven to exist before the action runs. Inconsistent with the other action and silently mutes any real invariant break. | `app/Domain/Commentary/Actions/SubmitCommentaryErrorReportAction.php:24-34` | Warning | Fixed | Replaced `find` + `instanceof` guard with `findOrFail`; dropped the now-unused `RuntimeException` import. The `lockForUpdate` is preserved since it is the actual reason the action re-fetches the row. Existing tests still pass. |
| A3 | `CommentaryBatchRunner::run` invokes `chunkById` without an explicit `orderBy`, relying on Laravel's documented default of ordering by the primary key. Reads correctly today; explicit ordering would protect against future Builder changes. | `app/Application/Support/CommentaryBatchRunner.php:68` | Suggestion | Skipped | `chunkById` is documented to default to the primary key (and re-orders internally by `id` regardless of any prior `orderBy`); making it explicit would add noise without changing behaviour. Re-evaluate if the runner ever supports a non-default key column. |
| A4 | Three Form Requests (`AICorrectCommentaryTextRequest`, `AIAddReferencesCommentaryTextRequest`, `ExportCommentarySqliteRequest`) ship with empty `rules()`. | `app/Http/Requests/Admin/Commentary/{AICorrectCommentaryTextRequest,AIAddReferencesCommentaryTextRequest,ExportCommentarySqliteRequest}.php` | Suggestion | Skipped | Already acknowledged in the previous review as scaffolding placeholders so future validation lands in the existing class instead of moving routes around. The story has no inputs to validate on these endpoints; auth is enforced by the route middleware. Leaving as-is is the cheapest forward path. |
| A5 | `TranslateCommentaryAction::prepare` performs a non-locking `SELECT` for an existing `(source_commentary_id, target_language)` target before the transaction. Two concurrent admin translate requests for the same pair could each pass the existence check, then race on create/delete-and-keep. | `app/Domain/Commentary/Actions/TranslateCommentaryAction.php:41-52` | Suggestion | Deferred | Realistic blast radius is tiny: the endpoint is `super-admin` gated and humans rarely double-click translate within the same DB round-trip. Mitigation when it becomes load-bearing: wrap the existence check in the same transaction with `lockForUpdate` against the source row, or add a unique index on `(source_commentary_id, language)` and let the DB serialise it. Tracked here so a future story (likely MBA-031 Horizon) wires the lock if needed. |
| A6 | Translation/correct/add-references jobs do not declare `tries`/`backoff`. If a worker crash retries the job after partial work, `TranslateCommentaryTextAction::execute` would re-`create` source rows that already wrote successfully — and `commentary_texts` has no `(commentary_id, book, chapter, position)` unique constraint to bounce the duplicate. | `app/Application/Jobs/{TranslateCommentaryJob,CorrectCommentaryBatchJob,AddReferencesCommentaryBatchJob}.php`; `app/Domain/Commentary/Actions/TranslateCommentaryTextAction.php:70-85` | Suggestion | Deferred | Default queue behaviour is single-attempt today, which makes this latent rather than active. Two natural fixes when MBA-031 ships Horizon: (a) add the unique index + `INSERT ... ON DUPLICATE KEY` for translate, or (b) set `public int $tries = 1` explicitly on the three jobs and keep partial-failure recovery the admin's responsibility (re-run with `?overwrite=true`). Either is one-line; both wait on Horizon's retry semantics. |
| A7 | `TranslateCommentaryAction::prepare` deletes existing target texts (when `overwrite=true`) before the controller creates the `ImportJob` and dispatches the job. If `ImportJob::create` or `Bus::dispatch` fails after `prepare()` returns, the texts are gone. | `app/Domain/Commentary/Actions/TranslateCommentaryAction.php:55-58`; `app/Http/Controllers/Api/V1/Admin/Commentary/TranslateCommentaryController.php:31-55` | Suggestion | Deferred | Recoverable: re-running with `?overwrite=true` deletes 0 rows and re-dispatches successfully. The plan deliberately puts the validation/idempotency check in the action's prepare phase so the controller only orchestrates HTTP shape, and moving the delete into the job would mean the 409 / "all texts corrected" check leaks into queue land. Keeping current shape; flagged for awareness. |

## Test results

- `make lint-fix` — 1284 files clean.
- `make stan` — `[OK] No errors` over 1259 files.
- `make test-api filter=Commentary` — **89 passed**, 277 assertions (one new test added).
- `make test-api` (full) — **1364 passed**, 5074 assertions, 50 s.

## Notes

- The earlier review pass already extracted the duplicated `triggeringUserId(...)` helper into `App\Support\Controllers\ResolvesTriggeringUser`; all eight commentary controllers consume it consistently. Tripwire register entry is unaffected.
- `commentary-error-reports` rate limiter is registered in `AppServiceProvider::boot` at 5/min/ip and is exercised by `SubmitCommentaryErrorReportTest`; throttling continues to bypass `public-anon` to keep one corporate NAT from poisoning ordinary commentary reads.
- `bootstrap/app.php` exception handler maps `CommentaryNotCorrectedException` and `CommentaryTextNotCorrectedException` to 422 and `TranslationTargetExistsException` to 409 (with `existing_commentary_id` in the body); confirmed end-to-end via `CommentaryAiEndpointsTest::test_translate_returns_409_when_target_exists_and_no_overwrite`.
- The `Cache::lock("sqlite-export:{slug}")` lives in `ExportCommentarySqliteJob`, not the action — so the action's unit tests bypass the lock. Acceptable per plan; flagged in the previous review and unchanged here.
