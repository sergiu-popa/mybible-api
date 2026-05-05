# Code Review — MBA-029-commentary-ai-workflow-and-sqlite-export

Scope: commit `ca9e934` ("[MBA-029] Implement commentary AI workflow + SQLite export") on top of MBA-028.

Verdict: **REQUEST CHANGES**. The implementation matches the plan's domain layout, schema, and HTTP contract; tests cover the counter-math matrix, SQLite output shape, batch-runner partial semantics, and every new endpoint's auth/validation/dispatch. Issues below are correctness and contract gaps rather than architectural problems.

---

## Critical

_None._

---

## Warnings

- [x] **W1 — Translate pass overwrites `ai_corrected_prompt_version` with `TranslateV1::VERSION` on the *target* row, conflating two distinct prompt provenances.**
  - `app/Domain/Commentary/Actions/TranslateCommentaryTextAction.php:84` writes `'ai_corrected_prompt_version' => TranslateV1::VERSION`. Both `CorrectV1::VERSION` and `TranslateV1::VERSION` are the literal string `'1.0.0'`, so an admin reading `ai_corrected_prompt_version` on a translated row cannot distinguish "corrected by Commentary\CorrectV1@1.0.0" from "translated by Commentary\TranslateV1@1.0.0". The story's audit-trail intent (story §AC8: "each prompt's version is recorded on the row that consumed it") is weakened — the row says it was corrected when it was actually translated.
  - **Fix:** either store a qualified value (`'commentary_translate@1.0.0'` vs `'commentary_correct@1.0.0'`) using the prompt name + version, or add a separate `ai_translated_at` / `ai_translated_prompt_version` column pair via a follow-up migration. The first option is cheaper and keeps the existing schema; do that and update the two writers (`CorrectCommentaryTextAction` and `TranslateCommentaryTextAction`) to write `prompt::NAME . '@' . prompt::VERSION` consistently.

- [x] **W2 — `TranslateCommentaryRequest::overwrite()` reads unvalidated input.**
  - `app/Http/Requests/Admin/Commentary/TranslateCommentaryRequest.php:38-41` calls `$this->boolean('overwrite')` but `overwrite` is not in `rules()`. Project convention (apps/api/CLAUDE.md §2 "Validation"): all incoming fields go through Form Request rules, never bypassed. The `boolean()` helper silently coerces nonsense values to false, which makes the `overwrite=truthy-but-mistyped` path indistinguishable from `overwrite=false` — and the controller never sees a 422.
  - **Fix:** add `'overwrite' => ['sometimes', 'boolean']` to `rules()`, then keep `overwrite()` as-is (Laravel's `boolean()` already accepts `1`, `'true'`, `true`, etc.).

- [x] **W3 — `ExportCommentarySqliteAction` materialises the entire SQLite file in PHP memory before uploading.**
  - `app/Domain/Commentary/Actions/ExportCommentarySqliteAction.php:61-66` does `file_get_contents($tmpPath)` then `$disk->put($key, $contents)`. The plan's Risks section explicitly calls out that real exports (SDA × 7 languages) reach 30–80 MB, and the worker has constrained memory headroom. `file_get_contents` on an 80 MB file allocates an 80 MB string in PHP; for back-to-back exports this is the difference between staying under `memory_limit` and OOMing the worker.
  - **Fix:** use `$disk->putFileAs(dirname($key), new \Illuminate\Http\File($tmpPath), basename($key))` (streams via fopen) or open a stream with `fopen($tmpPath, 'rb')` and pass the resource to `$disk->put`/`writeStream`.

- [x] **W4 — `ExportCommentarySqliteJob` cannot recover when the target Commentary disappears mid-flight.**
  - `app/Application/Jobs/ExportCommentarySqliteJob.php:49-58` correctly transitions the import job to `Failed` when the commentary is missing at job start. But `app/Application/Jobs/TranslateCommentaryJob.php:54-60` throws an unhandled `RuntimeException` if `$target` is missing, and the import job stays in `Pending`/`Running`. The admin polling endpoint sees a job that never terminates.
  - **Fix:** mirror the export job's pattern — if `$target` is null (or `$importJob` is null at the second find), update `$importJob->status = ImportJobStatus::Failed` with a descriptive `error` and `finished_at`, then `return` instead of throwing.

- [x] **W5 — `CommentaryErrorReport` factory generates `book='GEN', chapter=1, verse=1` regardless of the parent text's denormalised values.**
  - `database/factories/CommentaryErrorReportFactory.php:23-35` always seeds `'book' => 'GEN'` etc. The model's `book`/`chapter`/`verse` are denormalised from the parent `CommentaryText` for triage filters (story §AC4). When tests create a report via `CommentaryErrorReport::factory()->create(['commentary_text_id' => $text->id])`, the report's denorm columns no longer match the text's. This passes today because no test asserts the denorm consistency, but it sets a trap: a future feature filtering reports by `book` (story §AC18 mentions admin queue filters) on top of factory-generated fixtures will produce inconsistent test data.
  - **Fix:** in the factory's `definition()`, derive `book`/`chapter`/`verse` from the resolved `CommentaryText` factory call, e.g. via `afterMaking` that copies the parent's values, or accept defaults but note in a docblock that hand-written tests must align them.

---

## Suggestions

- [x] **S1 — SQLite `commentary_text` schema adds an `id INTEGER PRIMARY KEY AUTOINCREMENT` column not specified in story §AC20.**
  - `app/Domain/Commentary/Support/CommentarySqliteSchemaBuilder.php:46-56`. Story §AC20 enumerates the column set as `(book, chapter, position, verse_label, verse_from, verse_to, content_*)` with `UNIQUE(book, chapter, position)`. The added `id` column is harmless to mobile (which queries by the unique tuple), but it deviates from the documented mobile contract; if the mobile team auto-generates ORM bindings off the schema, an unexpected `id` field appears.
  - **Fix:** drop the `id` column — `UNIQUE(book, chapter, position)` already provides row identity, and SQLite gives you a hidden `rowid` for free. If kept, update story §AC20 / mobile schema documentation to match.

- [x] **S2 — `UpdateCommentaryErrorReportStatusAction` has a dead null branch.**
  - `app/Domain/Commentary/Actions/UpdateCommentaryErrorReportStatusAction.php:31-45` does `$text = ...->lockForUpdate()->find(...)` and then `if ($text instanceof CommentaryText && $delta !== 0)`. Because `commentary_error_reports.commentary_text_id` has `ON DELETE CASCADE`, a report cannot exist if its parent text is gone — the null branch is unreachable.
  - **Fix:** replace `find` with `findOrFail` (or drop the `instanceof` guard) so the impossible state surfaces loudly if the FK invariant is ever broken.

- [x] **S3 — `TranslateCommentaryData::triggeredByUserId` is unused.**
  - `app/Domain/Commentary/DataTransferObjects/TranslateCommentaryData.php:13`. The controller passes `$userId` into the DTO, but `TranslateCommentaryAction::prepare()` never reads it; the controller separately writes `'user_id' => $userId` into the import job. The DTO field is dead.
  - **Fix:** drop the field from the DTO, or have the action use it (e.g. attach to an `ai_calls` audit row at prepare time).

- [x] **S4 — `AICommentaryBatchRequest::filters()` reads `$this->input(...)` instead of `$this->validated(...)`.**
  - `app/Http/Requests/Admin/Commentary/AICommentaryBatchRequest.php:48-55`. After validation passes, both yield the same value, so this is correctness-equivalent — but it diverges from the project's "always read from `validated()`" convention (apps/api/CLAUDE.md §2). A future maintainer adding sanitisation/casts in rules will be surprised that `filters()` ignores them.
  - **Fix:** read from `$this->validated('book')` / `$this->validated('chapter')`.

- [x] **S5 — Repeated `$user instanceof User ? (int) $user->id : null` across six new controllers.**
  - `AICorrectCommentaryTextController:21-22`, `AIAddReferencesCommentaryTextController:21-22`, `AICorrectCommentaryBatchController:24-25`, `AIAddReferencesCommentaryBatchController:24-25`, `TranslateCommentaryController:27-28`, `ExportCommentarySqliteController:24-25`, `UpdateCommentaryErrorReportController:21-22`, `SubmitCommentaryErrorReportController:23-24`. This pattern is worth a small `triggeringUserId(Request $request): ?int` helper or a base-controller method.
  - **Fix:** either accept the duplication and log it in the §7 Tripwire register on `apps/api/.agent-workflow/CLAUDE.md` so a future story can extract, or extract now to `App\Support\Controllers\ResolvesTriggeringUser` trait.

- [x] **S6 — `CommentaryBatchRunnerTest` partial-failure case uses 5 rows; plan §AC §26 specifies 100.**
  - `tests/Unit/Application/Support/CommentaryBatchRunnerTest.php:45-78`. The test exercises the same code path either way, but the plan's number-of-rows specification was deliberate (50 rows = a full chunk plus partial, validates `chunkById` boundary behaviour). With only 5 rows, only one chunk executes — the multi-chunk progress update at line 86-88 of the runner is uncovered.
  - **Fix:** either bump the test to ≥100 rows with a failure at row ~50 to match the plan, or document why the smaller case is sufficient.

---

## Notes / acknowledgements

- The `partial` `ImportJobStatus` extension is wired through `isTerminal()` and consumed correctly by the runner.
- Rate limiter `commentary-error-reports` is registered with the requested `5/min/ip` budget, and the throttle test asserts the 6th call returns 429.
- The exception handler in `bootstrap/app.php` maps `CommentaryNotCorrectedException` and `CommentaryTextNotCorrectedException` to 422 and `TranslationTargetExistsException` to 409 with `existing_commentary_id` in the body — matches story §AC11.
- `Cache::lock("sqlite-export:{slug}")` lives in `ExportCommentarySqliteJob`, not in the action, so the action's own tests bypass the lock. This is consistent with the plan's "callers wrap" stance; flagged as nuance, not a defect.
- Empty Form Requests (`AICorrectCommentaryTextRequest`, `AIAddReferencesCommentaryTextRequest`, `ExportCommentarySqliteRequest`) are scaffolding placeholders. Acceptable; future validation lands in the existing class.
