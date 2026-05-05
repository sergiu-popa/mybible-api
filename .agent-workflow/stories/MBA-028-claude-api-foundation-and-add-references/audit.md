# Audit — MBA-028-claude-api-foundation-and-add-references

## Summary

Holistic post-QA pass over the Claude foundation, the versioned prompt
registry, the `ai_calls` audit log, the language-settings CRUD, and the
`AddReferences` service plus its sync + batch endpoints. Architecture is
clean (Domain/Action/DTO split), the HTTP wrapper is tight, and prior
review/QA closed the high-impact issues (W1 upstream-leak, W2 UTF-8
mangling, S1 model override, S2 set_time_limit, S4 factory collision, S6
double-parse). One **defense-in-depth gap** in the validator was missed
on the previous passes — a kept anchor preserved any extra attributes the
AI might attach (`onclick`, `style`, `target`, …). Three small follow-ups
also tidied (audit-row attribution from batch calls, dead config entry,
brittle latency math).

Verification after fixes: `make test-api filter='ClaudeClient|AddReferences|LanguageSettings|PromptRegistry|AddedReferencesValidator|AddReferencesVersionResolver|LanguageSettingsSeed'` → **39 / 39 passed (158 assertions)**; full suite `make test-api` → **1316 / 1316 passed (4933 assertions)**, 65.4s; `make stan` → clean; `make lint` → 1222 files PASS.

## Issues

| # | Issue | Location | Severity | Status | Resolution |
|---|---|---|---|---|---|
| 1 | Validator preserves any non-permitted attributes on kept reference anchors. A misbehaving / prompt-injected AI emitting `<a class="reference" href="JHN.3:16.VDC" onclick="…" style="…" target="…">` would round-trip the malicious attributes into the persisted output. | `app/Domain/AI/Support/AddedReferencesValidator.php:80-87` | Warning | **Fixed** | Added `ALLOWED_ATTRIBUTES = ['class', 'href']` allowlist + `stripDisallowedAttributes()` invoked after the class/href check passes. Test `test_strips_disallowed_attributes_from_kept_anchors` locks the behaviour down. |
| 2 | `AddReferencesBatchJob` calls the action without `triggeredByUserId`, so every audit row written for a batch run has `triggered_by_user_id = NULL` even though `import_jobs.user_id` knows who initiated the import — breaking the `(triggered_by_user_id, created_at)` cost-by-user roll-up the table was indexed for. | `app/Application/Jobs/AddReferencesBatchJob.php:67-78` | Suggestion | **Fixed** | Read `$importJob->user_id` once at the top of the loop and pass it into every `AddReferencesInput`. |
| 3 | `config/ai.php` declared `backoff_ms = [500, 2000, 5000]` but the loop consumes at most `max_attempts - 1 = 2` entries with the default `max_attempts = 3` — the third entry is dead. Future readers expect the array length to mean something. | `config/ai.php:35` | Suggestion | **Fixed** | Trimmed to `[500, 2000]` and updated the in-comment explanation to say "between attempts 1→2 and 2→3". |
| 4 | `ClaudeClient::latencyMs()` mixed `Carbon::now()->getTimestampMs()` with `microtime(true)`. Math worked, but Carbon test-now freezes (`Carbon::setTestNow(...)`) would distort recorded latencies, and the operator-precedence-dependent `microtime(true) - $start->getTimestampMs() / 1000` was hard to read. Two `created_at => Carbon::now()` writes were also redundant — the model's `$timestamps = true` (with `UPDATED_AT = null`) auto-fills `created_at` already. | `app/Domain/AI/Clients/ClaudeClient.php:37,181,219,237` | Suggestion | **Fixed** | Switched the start anchor to a single `microtime(true)` float; dropped the redundant Carbon import and the two redundant `created_at` writes. |
| 5 | Validator only inspects `<a>` tags. The system prompt forbids the AI from introducing new tags, but a hostile or hallucinating model could emit `<script>`, `<iframe>`, `<style>`, etc. and they would survive serialisation untouched. The current consumer (this story's sync endpoint, super-admin gated) doesn't persist the output, so the impact is bounded; once MBA-029's commentary AI workflow lands, this output gets stored and rendered to public users. | `app/Domain/AI/Support/AddedReferencesValidator.php` (file-scope) | Suggestion | **Deferred** | Defer to MBA-029, which is the first consumer that persists AddReferences output. Track as a follow-up: introduce a tag-allowlist filter (or run the output through an HTML purifier) at the persist boundary. Issue #1 above already neutralises the most realistic injection path (event handlers on kept anchors). |
| 6 | `ai_calls.subject_type` is a free-form string (`'commentary_text'`, `'devotional'`, `'sabbath_school_segment_content'`). Laravel's polymorphic resolver expects either FQCNs or `Relation::morphMap` aliases; none of these strings are in the morph map. Calling `$call->subject` would currently fail. Not a current bug — no code reads `subject()` yet — but the seam is loaded for the consumer story. | `app/Domain/AI/Models/AiCall.php:67-70`, `app/Providers/AppServiceProvider.php:157-161` | Suggestion | **Deferred** | Defer to MBA-029. When commentary AI workflow consumes `subject_type = 'commentary_text'`, register the morph map alias at the same time so the polymorphic relation resolves. Audit-log queries today key off the string column directly without traversing the relation, so this remains internal until then. |
| 7 | `AddReferencesBatchJob::loadTargetRows()` and `writeBack()` are no-op stubs. Code review and QA both confirm this matches the story's "scaffolding only" scope (§14: "Async batch endpoint scaffolding (job class, route, response)"). | `app/Application/Jobs/AddReferencesBatchJob.php:113-125` | Suggestion | **Skipped** | Skipped — this is intentional per-story scope. Consumer stories (MBA-029) fill in the per-subject row enumeration. The seam is in place. |
| 8 | `services.anthropic.api_key` defaults to an empty string when env is unset. A missing key only surfaces as a 401 from Anthropic which the client maps to a 502 — a config-validation early-fail at boot would fail faster, but the cost (a custom validator) outweighs the benefit for a single key. | `config/services.php`, `app/Domain/AI/Clients/ClaudeClient.php:104` | Suggestion | **Skipped** | Skipped — current behaviour produces a clear 502 with a sanitised message and the full upstream body in `ai_calls.error_message`. Preferred over a boot-time guard that would block local dev where the key is intentionally unset. |

## Verdict

**PASS** — no Critical issues; the lone Warning is resolved with a regression test; every Suggestion is Fixed, Deferred-with-pointer, or Skipped-with-reason. Status → `done`.
