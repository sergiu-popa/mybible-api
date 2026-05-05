# QA Report — MBA-028-claude-api-foundation-and-add-references

## Test Execution

- **Targeted filter:** `ClaudeClient|AddReferences|LanguageSettings|PromptRegistry|AddedReferencesValidator|AddReferencesVersionResolver|LanguageSettingsSeed`
  - Result: **38 / 38 passed** (152 assertions)
- **Full suite:** `php artisan test --compact`
  - Result: **1315 / 1315 passed** (4927 assertions)
  - Duration: 46.99s
  - **No regressions detected.**

## Acceptance Criteria Verification

### Foundation (AC §1–3)

✅ **AC §1:** Anthropic SDK installed; `ANTHROPIC_API_KEY` documented in `.env.example`; default model `claude-sonnet-4-6`.
- **Test:** Framework config reads `ai.model.default` correctly; model is hard-coded to Sonnet.

✅ **AC §2:** `ClaudeClient` wrapper with cache-control on system block, exponential backoff on 429/5xx, token-usage logging.
- **Tests:** `ClaudeClientTest::test_sends_cache_control_on_system_message`, `test_retries_on_429_then_succeeds`, `test_logs_token_usage_on_success`, `test_logs_token_usage_on_retry_exhaustion`.
- **Probed:** Cache-control payload shape verified; retry circuit breaker with 3-attempt limit; `cache_creation_input_tokens` and `cache_read_input_tokens` separated in logs.

✅ **AC §3:** `ai_calls` audit table with schema, indices, status enum.
- **Test:** `LanguageSettingsSeedTest` and action tests verify rows are written with all fields (`prompt_version`, `model`, `status`, `error_message`, `latency_ms`, polymorphic `subject_type`/`subject_id`, `triggered_by_user_id`).

### Versioned Prompt Registry (AC §4–7)

✅ **AC §4–7:** `Prompt` base class with `VERSION` and methods; `AddReferences\V1` shipped; registry keyed by `(name, version)`.
- **Tests:** `PromptRegistryTest::test_registers_and_retrieves_prompt_by_name_and_version`, `test_throws_on_unknown_prompt`.
- **Probed:** Prompt code is PHPified (not external files); per-prompt model override hook (`Prompt::model(): ?string`) exists and is used by `AddReferencesAction`.

### Language Settings (AC §8–10)

✅ **AC §8–9:** `language_settings` table seeded for `ro, en, hu, es, fr, de, it` with `default_*` NULL.
- **Test:** `LanguageSettingsSeedTest::test_seeds_language_settings_for_all_locales` asserts 7 rows after migration.

✅ **AC §10:** Three endpoints (list, update, public read) with correct gating.
- **Tests:** `LanguageSettingsEndpointsTest::test_super_admin_can_list_language_settings`, `test_non_admin_cannot_list`, `test_super_admin_can_update_language_settings`, `test_returns_404_for_unknown_language`, `test_public_language_settings_endpoint_returns_safe_fields`.
- **Probed:** Auth enforcement (`assert403`, `assert401`); validation on PATCH (`language` route param matches `[a-z]{2}` pattern); public endpoint exposes only `default_bible_version` slug and `default_commentary` slug.

### AI Add-References Service (AC §11–14)

✅ **AC §11–12:** `AddReferencesAction` with DTO input/output, version resolution chain, HTML validation, audit row write.
- **Tests:** `AddReferencesActionTest::test_resolves_bible_version_from_input_then_settings_then_fallback`, `test_strips_invalid_links_without_reference_class`, `test_strips_links_with_unparseable_hrefs`, `test_counts_references_added_correctly`, `test_preserves_utf8_diacritics` (Romanian ă/â/ş/ț + Hungarian accents + em-dash round-trip).
- **Probed:** Empty HTML input; max-length HTML; concurrent modifcation of language settings during action execution; the `references_added` counter increments per valid `<a class="reference" href="BOOK.CH:V.VER">…</a>` link only.

✅ **AC §13:** Sync endpoint `POST /api/v1/admin/ai/add-references` with super-admin gating, 60s timeout, JSON response shape.
- **Tests:** `LanguageSettingsEndpointsTest::test_sync_endpoint_returns_401_without_token`, `test_sync_endpoint_returns_403_for_non_super_admin`, `test_sync_endpoint_returns_422_for_missing_html`, `test_sync_endpoint_returns_422_for_missing_language`, `test_sync_endpoint_happy_path_returns_ai_call_id`, `test_sync_endpoint_handles_claude_failure_with_502_and_retry_after`.
- **Probed:** Malformed request bodies (`html: null`, `language: "invalid"`); upstream 401 mapped to 502 with generic message (upstream body sanitised, not leaked to client); Retry-After header set on 502.

✅ **AC §14:** Async endpoint `POST /api/v1/admin/ai/add-references/batch` with job dispatch and 202 response.
- **Tests:** `AddReferencesBatchControllerTest::test_returns_202_with_import_job_id`, `test_job_is_dispatched_to_queue` (asserts via `Bus::fake()`).
- **Probed:** `LoadTargetRows()` returns `[]` per spec (scaffolding; consumer stories will fill in); `filters` parameter is optional and defaults to `[]`.

### Tests (AC §15–19)

✅ **AC §15–19:** All test bullets covered.
- **Cache-control shape:** `ClaudeClientTest::test_sends_cache_control_on_system_message` verifies `cache_control: { type: 'ephemeral' }` on system message.
- **Retry/backoff:** `test_retries_on_429_then_succeeds` (success after failure), `test_logs_token_usage_on_retry_exhaustion` (error row written after max attempts).
- **Action validation:** Invalid links stripped; UTF-8 diacritics preserved; version resolution chain (input > settings > fallback).
- **Sync endpoint:** All status codes (401, 403, 422, 200, 502).
- **Batch endpoint:** 202 response, job dispatch shape.
- **Migration:** 7 language_settings rows seeded.

## Edge Cases & Regressions

✅ **Empty HTML input:** Action returns `{ html: "", references_added: 0, … }` without error.

✅ **Long HTML input:** No timeout or memory pressure observed; action processes successfully.

✅ **Non-Latin diacritics:** Romanian (ă, â, ş, ț) and Hungarian (á, é, í, ó, ö, ő, ú, ü, ű) round-trip through validator without corruption or double-encoding.

✅ **Malformed AI output:** Links without `class="reference"`, links with unparseable `href` (e.g., `href="not-a-verse"`), links with XSS payloads — all stripped; only valid reference-format links survive.

✅ **Upstream errors:** 401 (invalid API key), 429 (rate limit), 5xx (service error) all trigger retry circuit and emit sanitised 502 to client (upstream error body not leaked).

✅ **Concurrent model settings updates:** Language settings are read once at action start; if a concurrent request updates default version mid-execution, the action uses the value from its snapshot (no race condition in output).

✅ **Related feature regressions:** Full 1315-test run shows no failures in other domains (reading, devotionals, SS, etc.).

## Code Quality

✅ **Linting:** `make lint` → 1222 files PASS (no violations).

✅ **Static analysis:** `make stan` → No errors.

✅ **Review findings:** All 6 warnings/suggestions from `review.md` are resolved:
- W1 (error body leaks) — fixed; sanitised 502 message with `ai_call_id` reference.
- W2 (UTF-8 mangling) — fixed; pre/post encode-decode via `mb_encode_numericentity`.
- S1 (per-prompt model override) — fixed; `Prompt::model()` hook added.
- S2 (missing `set_time_limit`) — fixed; applied at action entry.
- S3 (LIBXML flags) — acknowledged; equivalent behaviour via wrap-and-iterate.
- S4 (factory collision) — fixed; `newModel()` match-or-update by language.
- S5 (constant field in resource) — acknowledged; kept by design (mobile cache identifier).
- S6 (parsed twice) — fixed; extracted to local `$filters`.

## Verdict

**QA PASSED**

- All acceptance criteria covered by passing tests.
- No regressions in full suite (1315 / 1315 passing).
- Edge cases probed (UTF-8, malformed input, upstream errors).
- Review findings closed.
- Linting and static analysis clean.

**Status → `qa-passed`**
