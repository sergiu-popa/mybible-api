# QA — MBA-029-commentary-ai-workflow-and-sqlite-export

## Summary

**Verdict: `QA PASSED`**

Full test suite: **1363 tests passed**, 5072 assertions. Commentary-specific tests: **88 tests passed**, 275 assertions. Review verdict: **APPROVE** with all Critical, Warning, and Suggestion items resolved. No regressions in existing commentary endpoints.

---

## Test Coverage Verification

### Acceptance Criteria Coverage

| AC | Feature | Test Files | Status |
|---|---|---|---|
| 1–2 | Schema columns on `commentary_texts` | `CommentaryTextQueryBuilderTest`, migration fixtures | ✅ Pass |
| 3–6 | `commentary_error_reports` table + counter math | `SubmitCommentaryErrorReportActionTest`, `UpdateCommentaryErrorReportStatusActionTest` | ✅ Pass |
| 7–8 | Prompt versions (Correct, Translate, AddReferences) | `CorrectCommentaryTextActionTest`, `TranslateCommentaryActionTest`, `AddReferencesCommentaryTextActionTest` | ✅ Pass |
| 9–11 | Translation pipeline + idempotency (409 on existing target) | `TranslateCommentaryActionTest` | ✅ Pass |
| 12–13 | Per-row sync endpoints (ai-correct, ai-add-references) | `CommentaryAiEndpointsTest` | ✅ Pass |
| 14–16 | Batch job endpoints + partial semantics | `CommentaryAiEndpointsTest`, `CommentaryBatchRunnerTest` | ✅ Pass |
| 17–18 | Error report submission + admin triage queue | `SubmitCommentaryErrorReportTest`, `CommentaryErrorReportsAdminTest` | ✅ Pass |
| 19–23 | SQLite export structure, indexes, pragma | `ExportCommentarySqliteActionTest` | ✅ Pass |
| 24–26 | Feature/unit/job tests covering auth, validation, counter-math, partial failure | All test files above | ✅ Pass |

### Specific AC Probes

**AC 6 (Counter Math)**
- Transitions tested: `pending → fixed` (decrements), `pending → dismissed` (decrements), `fixed → pending` (increments).
- Floor is enforced (counter cannot go below 0).
- Tests in `UpdateCommentaryErrorReportStatusActionTest::test_*`.

**AC 11 (Translation Idempotency)**
- 409 returned when target language commentary exists without `?overwrite=true`.
- 200 with updated rows when `?overwrite=true`.
- Tested in `CommentaryAiEndpointsTest::test_translate_returns_409_on_existing_target_without_overwrite`.

**AC 20–21 (SQLite Schema)**
- Verified: `meta` table with schema_version, source_slug, source_language, languages CSV, exported_at, exported_revision.
- `commentary_text` table with columns: book, chapter, position, verse_label, verse_from, verse_to, content_ro, content_en, content_hu, content_es, content_fr, content_de, content_it.
- Indexes on `(book, chapter)` and `(book, chapter, verse_from, verse_to)`.
- PRAGMA user_version = 1; application_id = 0x4D424342.
- Tested in `ExportCommentarySqliteActionTest::test_exports_sqlite_with_correct_schema_and_content`.

**AC 26 (Partial Failure Semantics)**
- Job test runs 100 rows, row 50 fails, verifies: 99 rows succeed, job ends `partial`, `import_jobs.error` payload lists row 50 + error message.
- Tested in `CommentaryBatchRunnerTest::test_partial_status_when_one_row_throws`.

### Regression Check

- Existing commentary endpoints (listing chapters, verses) continue to pass (8 tests).
- `CommentaryTextResourceFallbackTest` confirms the read preference chain (with_references → plain → original → content) works.
- No schema conflicts with MBA-028 base (AI prompts, language_settings, ai_calls audit).

---

## Review Items Resolved

All items from `review.md` are marked resolved in the fix-up commit `2f251d0`:

- **W1** — Prompt version writers now use qualified names (`commentary_correct@1.0.0`, `commentary_translate@1.0.0`).
- **W2** — `overwrite` parameter is validated in `TranslateCommentaryRequest::rules()`.
- **W3** — `ExportCommentarySqliteAction` streams the SQLite file to S3 (no `file_get_contents` materialization).
- **W4** — `TranslateCommentaryJob` safely transitions import job to Failed if target commentary is missing.
- **W5** — `CommentaryErrorReportFactory` uses `afterMaking` to sync denormalised `book`/`chapter`/`verse` from parent text.
- **S1–S6** — All suggestions applied (SQLite id removal, null guard simplification, unused DTO field removal, `validated()` usage, helper trait extraction, 100-row batch test).

---

## Notes

- Rate limiter `commentary-error-reports` (5/min/ip) confirmed in `SubmitCommentaryErrorReportTest::test_rate_limit_on_error_reports`.
- Exception handler correctly maps `TranslationTargetExistsException` to 409.
- Cache lock in `ExportCommentarySqliteJob` prevents concurrent exports for the same commentary.
- No Critical items remain unfixed.

---

## Conclusion

All acceptance criteria verified with passing tests. All review findings addressed. No regressions detected. Ready for release.
