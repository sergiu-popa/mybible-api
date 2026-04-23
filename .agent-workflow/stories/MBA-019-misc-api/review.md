# Code Review: MBA-019-misc-api

**Commit reviewed:** `f175b08` — _[MBA-019] Add news, QR codes, and mobile version endpoints_
**Branch:** `mba-019`
**Verdict:** **APPROVE** → `qa-ready`

## Scope

Three read-only endpoints wired under `/api/v1` with cache-friendly posture:

- `GET /api/v1/news` — paginated, language-filtered, newest-first, 300 s cache + etag.
- `GET /api/v1/qr-codes?reference=…` — canonical-reference lookup, 404 on miss, 86 400 s cache + etag.
- `GET /api/v1/mobile/version?platform=ios|android` — reads `config/mobile.php`, 300 s cache.

All three sit behind `api-key-or-sanctum`. New surface: `News` + `QrCode` Eloquent models, `qr` filesystem disk, Form Requests, Resources, invokable controllers, factories, migrations (`Schema::hasTable()` guarded), env-driven `config/mobile.php`. 14 unit tests + 20 feature tests. Lint clean, PHPStan clean, full filter suite (58 tests) green.

## Plan adherence

Every task in `plan.md` (1–18) is checked and reflected in the diff:

- `news` + `qr_codes` migrations match the column shapes and indexes in the plan (composite `(language, published_at)`, `unique(reference)`).
- `NewsQueryBuilder::published()` uses `whereNotNull('published_at')` + `<= now()` per the resolved question on unpublish semantics.
- `ShowQrCodeRequest` reuses `ValidReference` (single-reference enforced by that rule) and stashes the parsed `Reference` via the project's attribute-bag pattern (CLAUDE.md §2). No double-parse.
- `config/mobile.php` field names are verbatim per AC 9 and plan's locked-contract note.
- The `qr` disk is added to `config/filesystems.php` with a public URL base; matches the plan's MBA-020 caveat.

No unjustified deviations.

## Findings

### Critical
_None._

### Warning
_None._

### Suggestion

1. `app/Http/Requests/News/ListNewsRequest.php:55-62` — `resolvedLanguage()` reads `$this->query('language')` rather than `$this->validated('language')`. The rules already constrain the value to `Language::cases()`, and the Form Request auto-validates on injection, so `Language::from()` is safe, but going through `validated()` would make the contract self-evident and would also normalise away an empty-string edge case without the explicit `!== ''` check. Non-blocking.

2. `app/Http/Requests/QrCode/ShowQrCodeRequest.php:52-59` — when `ValidReference` parsed a reference without a version (i.e. the client omitted the `.VDC` suffix), `canonicalReference()` falls back to the raw input rather than a canonical form. Given current Symfony-backed data seeds only `VDC` entries, a versionless client query will deterministically 404 at the QrCode lookup. That's acceptable for now; if a later story allows bare references, replace the fallback with an explicit 422 at the rule level or teach `ReferenceFormatter` a default-version mode. Non-blocking.

3. `app/Http/Requests/News/ListNewsRequest.php:38-47` — `perPage()` clamps out-of-range values instead of leaning on the `min:1 / max:50` rules. In the HTTP path validation runs first (so 9999 → 422, as the test confirms), which means the clamp only kicks in when callers instantiate the request directly without validating. The behaviour is fine; the dual defence is just a minor duplication. Non-blocking.

4. `app/Http/Resources/News/NewsResource.php:33-40` — `resolveImageUrl()` uses `Storage::disk('public')` rather than a dedicated `news` disk. Consistent with the rest of the project (no `news` disk planned), but worth revisiting if news ever grows a distinct storage tier. Non-blocking.

5. `app/Http/Resources/Mobile/MobileVersionResource.php:24-33` — `@phpstan-ignore`-free but the docblock could note that `resource` is an `array<string, mixed>` rather than leaving it to the local assignment comment. Purely stylistic.

## Guidelines check

- Controllers are thin, delegate query building to QueryBuilders, and return Resources. No inline validation. ✓
- Form Requests own validation and authorisation (all three `authorize(): true` since `api-key-or-sanctum` is doing the real gating). ✓
- Exception paths (404 on QR miss, 422 on bad reference, 401 on missing creds) flow through the JSON exception handler — tests assert. ✓
- `Schema::hasTable()` guards on migrations match the MBA-007 precedent for shared-DB coexistence. ✓
- Attribute-bag pattern (`ValidReference::PARSED_ATTRIBUTE_KEY`, `ResolveRequestLanguage::ATTRIBUTE_KEY`) used instead of container state. ✓
- No Eloquent model returned directly; all responses are Resources. ✓
- Public API contract: no constant-under-scope fields leaked into responses. ✓
- Tripwire register: still zero Actions/owner-gates added. `cache.headers` copy-count now 6/8 per the plan's tally — below the extraction threshold. ✓

## Tests & tooling

- `make test --filter='News|QrCode|MobileVersion'` — 58 passed, 150 assertions.
- `vendor/bin/pint --test --format agent` — pass.
- `vendor/bin/phpstan analyse` — no errors.

## Verdict

**APPROVE.** No Critical, no Warnings. Suggestions above are for future polish, not required before QA. Move status → `qa-ready`.
