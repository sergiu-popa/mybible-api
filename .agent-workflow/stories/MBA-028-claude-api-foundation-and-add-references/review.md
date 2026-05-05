# Code Review — MBA-028-claude-api-foundation-and-add-references

## Summary

The Claude integration foundation lands cleanly: `ClaudeClient` is a tight HTTP wrapper around `/v1/messages` with cache-control on the system block, exponential backoff on 429/5xx, and one `ai_calls` row per outcome. The versioned prompt registry, polymorphic audit log, language-settings CRUD, and `AddReferencesAction` all match the plan. Tests cover the cache-control payload, retry behaviour, validation/stripping of malformed anchors, the version-resolution chain, the sync endpoint at every status code the AC requires, and the batch endpoint's job-dispatch shape. Routing, super-admin gating, the `[a-z]{2}` route pattern guard, and the 502/Retry-After exception mapping are all wired correctly.

A handful of Warnings and Suggestions below — none are blockers but two are worth addressing before APPROVE because they have client-visible or correctness implications.

## Critical

_None._

## Warnings

- [x] **W1 — Upstream error body leaks to clients via 502 envelope.** `app/Domain/AI/Clients/ClaudeClient.php:52-56` builds `$lastErrorMessage = sprintf('HTTP %d from Anthropic: %s', $response->status(), mb_substr((string) $response->body(), 0, 1024))`, then `app/Domain/AI/Actions/AddReferencesAction.php:58-62` propagates that string verbatim into `ClaudeUnavailableException`, and `bootstrap/app.php:165-171` returns `$e->getMessage()` to the caller. That means an Anthropic 401/400 response body (which can include phrases like "invalid x-api-key", quota descriptors, or model gating reasons) is rendered to admin clients. Even though the route is super-admin-gated, this is unnecessary disclosure of upstream diagnostics — and once any admin browser caches the 502 response or it shows up in a screenshot, the leak persists. Fix: keep the full upstream body in the `ai_calls.error_message` row (already happening) but pass a generic message to `ClaudeUnavailableException` ("Upstream AI service is unavailable.") so the 502 body is sanitised. Reference the `ai_call_id` in the JSON envelope if you want operators to trace it.

- [x] **W2 — `DOMDocument::loadHTML` can mangle non-ASCII input.** `app/Domain/AI/Support/AddedReferencesValidator.php:41-50` wraps the AI-returned HTML with `<meta charset="UTF-8">` and loads it via legacy `DOMDocument`. PHP's libxml-backed `loadHTML` is documented to mis-decode UTF-8 even when the meta charset is declared, because it interprets the first byte sequence before the meta element is parsed (this bites Romanian/Hungarian diacritics regularly). The current corpus this story will run against is HTML containing Romanian text like "Vezi Ioan 3:16 unde…" — those `ă/â/ş/ț` characters can come back through this validator double-encoded or stripped. Fix: either pre-encode with `mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8')` before `loadHTML`, or migrate to PHP 8.4's `Dom\HTMLDocument::createFromString($html, LIBXML_NOERROR)` which handles UTF-8 correctly. Add a test with diacritic input to lock the behaviour down.

## Suggestions

- **S1 — Per-prompt model override hook is missing.** Story AC §1 says "the prompt registry can override per use case (e.g. translation may pin `claude-opus-4-7`)", but `app/Domain/AI/Prompts/Prompt.php:14-34` exposes only `NAME`, `VERSION`, `systemPrompt()`, `userMessage()` — no `model()` hook — and `app/Domain/AI/Actions/AddReferencesAction.php:46` hard-codes `model: (string) config('ai.model.default')`. AddReferences happily uses Sonnet so this story doesn't suffer, but MBA-029's translation prompt will need to either add a `public function model(): string` to `Prompt` or thread an explicit model into `ClaudeRequest`. Cheapest now: add `Prompt::model(): ?string` returning `null` by default, and have the Action prefer `$prompt->model() ?? config('ai.model.default')`.

- **S2 — Plan deviation: `set_time_limit(60)` not applied.** `plan.md:59` calls for `set_time_limit(60)` inside the action plus `Http::timeout(...)` on the client. Only the `Http::timeout` half made it (`config/ai.php:28`). For PHP-FPM/nginx setups the per-request timeout is usually higher than 60s, so a hung-but-not-disconnected upstream that returns slowly could exceed the 60s SLA the AC promises. Add `set_time_limit((int) config('ai.request.timeout_seconds', 60))` at the top of `AddReferencesAction::execute()`.

- **S3 — Validator drops `LIBXML_HTML_NOIMPLIED`.** `plan.md:92` recommended `LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD`; the implementation uses `LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING` (`AddedReferencesValidator.php:48`). The current behaviour is fine because the code wraps in a full `<html><body>` and iterates `$body->childNodes`, so `NOIMPLIED` would be redundant. Worth a one-line `// wrap-and-iterate substitutes for LIBXML_HTML_NOIMPLIED` comment for the next reader, or just adopt the flag.

- **S4 — `LanguageSettingFactory::definition()` will collide with seeded rows.** `database/factories/LanguageSettingFactory.php:23-32` randomly picks one of the 7 enum values for the `language` column, which is unique. Migrations seed all 7 rows, so any future caller doing `LanguageSetting::factory()->create()` (without `forLanguage(...)`) will hit a unique-constraint violation. Today no test calls it that way, so it's silent — but the trap is loaded. Either remove the random default and require `forLanguage(...)`, or have the factory `firstOrCreate` against the existing row.

- **S5 — `PublicLanguageSettingResource.language` is constant under the path scope.** `app/Http/Resources/LanguageSettings/PublicLanguageSettingResource.php:25` echoes the `language` URL parameter back. This trips the "constant field" rule in the reviewer guidelines. It is a singular GET-by-key resource so echoing the key is conventional and useful for client identification, so I'd keep it — but worth acknowledging explicitly so future audits don't re-flag.

- **S6 — `AddReferencesBatchController` parses `filters` twice.** Lines 32 and 42 both run `is_array($validated['filters'] ?? null) ? $validated['filters'] : []`. Pull it into a local once.

## Plan / scope alignment

All 28 plan tasks are checked, the tripwire register is unchanged (correct — this story has no body-level ownership checks), and the implementation tracks the plan closely. Two intentional deviations:

- The `Prompt` base does not yet expose a `model()` override (S1). Acceptable for foundation; deferred to MBA-029.
- `set_time_limit(60)` is omitted from the action (S2). Plan asked for both halves of the timeout.

## Tests

- `ClaudeClientTest` covers cache-control payload shape, success-path token logging, 429 retry-then-succeed, and exhausted-retry error row write. Nice tight set.
- `AddReferencesActionTest`, `AddReferencesVersionResolverTest`, `AddedReferencesValidatorTest`, `PromptRegistryTest`, the two endpoint feature tests, and `LanguageSettingsEndpointsTest` collectively hit every test bullet in AC §15-§19.
- `LanguageSettingsSeedTest` asserts all 7 ISO-2 codes after `migrate`.
- One test gap worth filling alongside W2: a validator test using diacritic input (`<p>Vezi <a class="reference" href="JHN.3:16.VDC">Ioan 3:16 — așa</a> a iubit.</p>`) to lock in UTF-8 round-trip behaviour.

## Verdict

**REQUEST CHANGES** — W1 (upstream error leak) and W2 (UTF-8 mangling) need either a fix or an explicit acknowledgement before APPROVE. Suggestions are optional but S1 and S2 are worth tackling now since they keep the foundation honest with the AC.
