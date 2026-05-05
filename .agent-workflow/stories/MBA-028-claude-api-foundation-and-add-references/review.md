# Code Review — MBA-028-claude-api-foundation-and-add-references

## Summary

Re-review after the engineer's fix commit `965902b` (`Address review.md W1, W2 + S1, S2, S4, S6`). The Claude integration foundation lands cleanly: `ClaudeClient` is a tight HTTP wrapper around `/v1/messages` with cache-control on the system block, exponential backoff on 429/5xx, and one `ai_calls` row per outcome. The versioned prompt registry, polymorphic audit log, language-settings CRUD, and `AddReferencesAction` all match the plan. Tests cover the cache-control payload, retry behaviour, validation/stripping of malformed anchors (now including a UTF-8 diacritic round-trip), the version-resolution chain, the sync endpoint at every status code the AC requires, and the batch endpoint's job-dispatch shape. Routing, super-admin gating, the `[a-z]{2}` route pattern guard, and the 502/Retry-After exception mapping are all wired correctly.

Verification this pass: `make test-api filter='ClaudeClient|AddReferences|LanguageSettings|PromptRegistry|AddedReferencesValidator|AddReferencesVersionResolver|LanguageSettingsSeed'` → 38 passed / 152 assertions; `make stan` → No errors; `make lint` → 1222 files PASS.

## Critical

_None._

## Warnings

- [x] **W1 — Upstream error body leaks to clients via 502 envelope.** `app/Domain/AI/Clients/ClaudeClient.php:52-56` builds `$lastErrorMessage = sprintf('HTTP %d from Anthropic: %s', $response->status(), mb_substr((string) $response->body(), 0, 1024))`, then `app/Domain/AI/Actions/AddReferencesAction.php:58-62` propagates that string verbatim into `ClaudeUnavailableException`, and `bootstrap/app.php:165-171` returns `$e->getMessage()` to the caller. That means an Anthropic 401/400 response body (which can include phrases like "invalid x-api-key", quota descriptors, or model gating reasons) is rendered to admin clients. Even though the route is super-admin-gated, this is unnecessary disclosure of upstream diagnostics — and once any admin browser caches the 502 response or it shows up in a screenshot, the leak persists. Fix: keep the full upstream body in the `ai_calls.error_message` row (already happening) but pass a generic message to `ClaudeUnavailableException` ("Upstream AI service is unavailable.") so the 502 body is sanitised. Reference the `ai_call_id` in the JSON envelope if you want operators to trace it. — **resolved in 965902b**: `ClaudeUnavailableException::PUBLIC_MESSAGE` is the only outward-facing string; full upstream body still lives in `ai_calls.error_message`; exception handler now appends `ai_call_id` to the 502 envelope when present (`bootstrap/app.php:165-176`).

- [x] **W2 — `DOMDocument::loadHTML` can mangle non-ASCII input.** `app/Domain/AI/Support/AddedReferencesValidator.php:41-50` wraps the AI-returned HTML with `<meta charset="UTF-8">` and loads it via legacy `DOMDocument`. PHP's libxml-backed `loadHTML` is documented to mis-decode UTF-8 even when the meta charset is declared, because it interprets the first byte sequence before the meta element is parsed (this bites Romanian/Hungarian diacritics regularly). The current corpus this story will run against is HTML containing Romanian text like "Vezi Ioan 3:16 unde…" — those `ă/â/ş/ț` characters can come back through this validator double-encoded or stripped. Fix: either pre-encode with `mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8')` before `loadHTML`, or migrate to PHP 8.4's `Dom\HTMLDocument::createFromString($html, LIBXML_NOERROR)` which handles UTF-8 correctly. Add a test with diacritic input to lock the behaviour down. — **resolved in 965902b**: pre-encode/post-decode via `mb_encode_numericentity` / `mb_decode_numericentity` (`AddedReferencesValidator.php:43-51,95-100`); `test_preserves_utf8_diacritics` covers Romanian + Hungarian diacritics + em-dash round-trip.

## Suggestions

- [x] **S1 — Per-prompt model override hook is missing.** Story AC §1 says "the prompt registry can override per use case (e.g. translation may pin `claude-opus-4-7`)", but `app/Domain/AI/Prompts/Prompt.php:14-34` exposes only `NAME`, `VERSION`, `systemPrompt()`, `userMessage()` — no `model()` hook — and `app/Domain/AI/Actions/AddReferencesAction.php:46` hard-codes `model: (string) config('ai.model.default')`. AddReferences happily uses Sonnet so this story doesn't suffer, but MBA-029's translation prompt will need to either add a `public function model(): string` to `Prompt` or thread an explicit model into `ClaudeRequest`. Cheapest now: add `Prompt::model(): ?string` returning `null` by default, and have the Action prefer `$prompt->model() ?? config('ai.model.default')`. — **resolved**: `Prompt::model(): ?string` (default `null`) added at `Prompt.php:41-44`; the action prefers it (`AddReferencesAction.php:50`).

- [x] **S2 — Plan deviation: `set_time_limit(60)` not applied.** `plan.md:59` calls for `set_time_limit(60)` inside the action plus `Http::timeout(...)` on the client. Only the `Http::timeout` half made it (`config/ai.php:28`). For PHP-FPM/nginx setups the per-request timeout is usually higher than 60s, so a hung-but-not-disconnected upstream that returns slowly could exceed the 60s SLA the AC promises. Add `set_time_limit((int) config('ai.request.timeout_seconds', 60))` at the top of `AddReferencesAction::execute()`. — **resolved**: `@set_time_limit((int) config('ai.request.timeout_seconds', 60))` at `AddReferencesAction.php:38`. The `@` suppression is appropriate here (PHP can return false in safe-mode-like environments and we don't want a warning to bubble).

- [x] **S3 — Validator drops `LIBXML_HTML_NOIMPLIED`.** `plan.md:92` recommended `LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD`; the implementation uses `LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING` (`AddedReferencesValidator.php:48`). The current behaviour is fine because the code wraps in a full `<html><body>` and iterates `$body->childNodes`, so `NOIMPLIED` would be redundant. Worth a one-line `// wrap-and-iterate substitutes for LIBXML_HTML_NOIMPLIED` comment for the next reader, or just adopt the flag. — acknowledged: deliberate plan deviation, behaviour is equivalent and now exercised by the diacritic test added for W2.

- [x] **S4 — `LanguageSettingFactory::definition()` will collide with seeded rows.** `database/factories/LanguageSettingFactory.php:23-32` randomly picks one of the 7 enum values for the `language` column, which is unique. Migrations seed all 7 rows, so any future caller doing `LanguageSetting::factory()->create()` (without `forLanguage(...)`) will hit a unique-constraint violation. Today no test calls it that way, so it's silent — but the trap is loaded. Either remove the random default and require `forLanguage(...)`, or have the factory `firstOrCreate` against the existing row. — **resolved**: `LanguageSettingFactory::newModel()` match-or-update by `language` (`LanguageSettingFactory.php:47-60`). Note for future reviewers: this is a slightly unconventional override (the parent factory's `store()` will later call `save()` again on the returned model), but it's safe — the second save is a no-op when no attributes are dirty. If a test ever depends on a freshly-instantiated unsaved model from the factory it will need `make()` not `create()`.

- [x] **S5 — `PublicLanguageSettingResource.language` is constant under the path scope.** `app/Http/Resources/LanguageSettings/PublicLanguageSettingResource.php:25` echoes the `language` URL parameter back. This trips the "constant field" rule in the reviewer guidelines. It is a singular GET-by-key resource so echoing the key is conventional and useful for client identification, so I'd keep it — but worth acknowledging explicitly so future audits don't re-flag. — acknowledged: kept by design; mobile clients identify the row by language code in their cache, so echoing it is load-bearing for them.

- [x] **S6 — `AddReferencesBatchController` parses `filters` twice.** Lines 32 and 42 both run `is_array($validated['filters'] ?? null) ? $validated['filters'] : []`. Pull it into a local once. — **resolved**: `$filters` extracted to a local at `AddReferencesBatchController.php:22` and reused in both call sites.

## Plan / scope alignment

All 28 plan tasks are checked, the tripwire register is unchanged (correct — this story has no body-level ownership checks), and the implementation tracks the plan closely after the fix commit.

`AddReferencesBatchJob::loadTargetRows()` returning `[]` is intentional scaffolding — story §14 (in-scope) explicitly says "Async batch endpoint scaffolding (job class, route, response)" and the per-subject row enumeration lands with consumer stories (MBA-029 commentary AI workflow). The seam is in place; the consumer fills it in.

## Tests

- `ClaudeClientTest` covers cache-control payload shape, success-path token logging, 429 retry-then-succeed, and exhausted-retry error row write.
- `AddReferencesActionTest`, `AddReferencesVersionResolverTest`, `AddedReferencesValidatorTest` (now incl. UTF-8 diacritics), `PromptRegistryTest`, the two endpoint feature tests, and `LanguageSettingsEndpointsTest` collectively hit every test bullet in AC §15-§19.
- `LanguageSettingsSeedTest` asserts all 7 ISO-2 codes after `migrate`.
- 38 / 38 pass on this filter; full suite gates (`stan`, `lint`) clean.

## Verdict

**APPROVE** — every Warning is closed (W1, W2 fixed and verified), every Suggestion is either resolved or explicitly acknowledged. Status → `qa-ready`.
