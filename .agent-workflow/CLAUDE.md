# CLAUDE.md — MyBible API Project Agent Configuration

This file **overrides** the global agent and guideline files in `~/.claude/` for
this repository. If anything here conflicts with a global rule, the project
wins.

## Project

- **Name:** MyBible API
- **Type:** JSON-only HTTP API (no UI, no Blade, no Livewire, no Tailwind, no Vite)
- **PHP version:** 8.4
- **Laravel version:** 13.x
- **Auth:** Laravel Sanctum bearer tokens + `api-key-or-sanctum` middleware for dual-auth public routes. Hash driver: Argon2id (memory 65536, time 4, threads 1).

## Story Location

All agent workflow stories live in `.agent-workflow/stories/` and are committed
to this repository.

## Story ID Prefix

Use `MBA` for story IDs in this project (e.g., `MBA-001-create-verse`).

## Domain Map

<!-- Kept empty intentionally until the first domain is introduced. Populate as domains emerge. -->

| Domain | Purpose |
|---|---|
| _(none yet)_ | |

---

## 1. Hard Overrides of Global Rules

The global agents and `laravel-beyond-crud-guidelines.md` assume a **TALL stack**
(Livewire + Blade + Tailwind). This project has none of that. The following
rules from those files are **explicitly inapplicable** here and must be
ignored:

- **Blade / views / `resources/views/`** — there are no views. `resources/`
  does not exist.
- **Livewire 4 conventions** — `#[Validate]`, `#[Locked]`, `#[On()]`,
  property hooks on components, `wire:*` directives, `wire:ref`, slots,
  `$this->dispatch()`, lazy loading, multi-file component convention,
  `#[Modelable]`. None of these apply.
- **`resources/views/livewire/` path** referenced in the guidelines.
- **`Livewire::test()`** for testing — use HTTP feature tests instead.
- **Auditor "Livewire 4 Compliance" dimension** — score it N/A.
- **Architect "Define the Livewire components" step** — skip.
- **Code Reviewer "no inline PHP in Blade", "multi-file Livewire"** checks —
  skip.

The rest of Beyond CRUD (Domain layer, Actions, DTOs, QueryBuilders, Events,
States, final classes, strict types, explicit return types, no magic strings,
no `else`) **still applies**.

---

## 2. HTTP Layer Conventions (replacing the Livewire section)

### Routing

- All API routes live in `routes/api.php` under the `/api/v1` prefix group.
- Health check: `GET /up` (framework default).
- Prefer resource routes (`Route::apiResource(...)`). Use named routes.

### Controllers

- Namespace: `App\Http\Controllers\Api\V1\…`
- One controller per resource (or invokable single-action controllers for
  non-CRUD endpoints).
- Controllers do **not** contain business logic. They:
  1. Receive a Form Request (validation runs automatically).
  2. Call a Domain Action, passing a DTO built via `Data::from($request->validated())`.
  3. Return an API Resource or `response()->json(...)`.

### Validation

- Use **Form Requests** (`App\Http\Requests\…`). Never `$request->validate()`
  inline.
- Authorization goes in the Form Request's `authorize()` method or in a Policy
  — not in the controller body.

### Response Shaping

- Use **Eloquent API Resources** (`App\Http\Resources\…`) for all resource
  responses. Never return Eloquent models directly.
- Collections return `{ data: [...], meta: {...}, links: {...} }` (the default
  paginated resource shape).
- Error envelope: `{ "message": "...", "errors": { "field": ["..."] } }` —
  this is enforced by the exception handler in `bootstrap/app.php`.

### Exception Handling

- All exceptions render as JSON (wired in `bootstrap/app.php` via
  `shouldRenderJsonWhen(fn () => true)`).
- Do not add try/catch to controllers for framework exceptions — let the
  handler format them.

### Middleware → Downstream Data Passing

- When a middleware resolves per-request data that controllers, Form
  Requests, or Resources need to read (e.g. a resolved `Language`, an
  authenticated `ApiClient`), attach it to `$request->attributes` under a
  named `public const ATTRIBUTE_KEY` on the middleware class.
- Do **not** use `app()->instance(...)` or other container bindings for
  this. Container-scoped state leaks request-scoped data into a
  process-scoped container, and makes a downstream reader fail hard
  (`BindingResolutionException`) if the middleware is ever omitted from a
  route.
- Downstream readers must use
  `$request->attributes->get(MiddlewareClass::ATTRIBUTE_KEY, $default)`
  so a missing middleware degrades to a sane default instead of throwing
  at render time.
- Precedents in this repo: `ResolveRequestLanguage` (attaches the
  resolved `Language` under `ATTRIBUTE_KEY`) and `EnsureValidApiKey`
  (attaches the matched api-key client name under `api_client`).

### Testing (replacing the Livewire testing guidance)

| Layer | Tool | What to Test |
|---|---|---|
| Actions | PHPUnit unit tests | Business logic in isolation |
| QueryBuilders | PHPUnit with `RefreshDatabase` | Query correctness |
| Form Requests | PHPUnit unit tests | Validation rules, authorization |
| API Resources | PHPUnit unit tests | Response shape |
| HTTP endpoints | Feature tests (`$this->getJson`, `postJson`, `putJson`, `deleteJson`) | Routing, auth, status codes, JSON shape |

- Test naming: `test_it_creates_a_verse()`, `test_it_returns_404_for_unknown_verse()`.
- Assert JSON structure with `assertJsonStructure` and specific values with
  `assertJsonPath` — not `assertSee`/`assertDontSee` (those are HTML).
- For auth-protected endpoints (once auth is added), use
  `Sanctum::actingAs($user)` or equivalent.

#### Test Helpers

Shared test helpers live under `tests/Concerns/` as traits. When the same
setUp boilerplate appears in a second feature test, extract it here
rather than copying it a third time.

| Trait | Purpose |
|---|---|
| `Tests\Concerns\WithApiKeyClient` | Configures the `api_keys.*` config and provides `apiKeyHeaders()` for feature tests hitting api-key-protected routes. Call `$this->setUpApiKeyClient()` from `setUp()`. |

---

## 3. Directory Layout (this project)

```
app/
├── Domain/                     # Business logic (Beyond CRUD, no framework deps)
│   └── <Domain>/
│       ├── Models/
│       ├── Actions/
│       ├── DataTransferObjects/
│       ├── QueryBuilders/
│       ├── Collections/
│       ├── Events/
│       ├── Listeners/
│       ├── Exceptions/
│       ├── Rules/
│       └── States/
├── Http/
│   ├── Controllers/Api/V1/
│   ├── Requests/
│   └── Resources/
├── Application/                # Jobs, commands, subscribers
├── Providers/
└── Support/                    # Framework glue (middleware, helpers)

routes/
├── api.php                     # All API routes
└── console.php
```

There is **no** `resources/views/` or `resources/js/` or `resources/css/`.

---

## 4. Agent-Specific Overrides

### Architect (`~/.claude/agents/architect.md`)

When producing `architecture.md`, replace the "Livewire components" section
with **"HTTP endpoints"**:

| Section | Contents |
|---|---|
| Overview | 2–3 sentence summary |
| Domain changes | Models, migrations, relations |
| Actions & DTOs | List with purpose and signature |
| Events & Listeners | Triggers and side effects |
| **HTTP endpoints** | Method, path, controller, request class, resource class, auth requirement |
| Risks & open questions | — |

`tasks.md` format unchanged, but Livewire/Blade tasks become controller,
Form Request, and API Resource tasks.

### Engineer (`~/.claude/agents/engineer.md`)

Ignore the entire **"Livewire 4"** subsection. Instead, for every
user-facing feature, the Engineer produces:

1. Migration + Model (with QueryBuilder/Collection if needed).
2. DTO (`readonly class`) under `App\Domain\<Domain>\DataTransferObjects`.
3. Action under `App\Domain\<Domain>\Actions`.
4. Form Request under `App\Http\Requests\…`.
5. API Resource under `App\Http\Resources\…`.
6. Controller under `App\Http\Controllers\Api\V1\…`.
7. Route in `routes/api.php` under the `v1` prefix group.
8. Feature test that exercises the HTTP endpoint end-to-end.
9. Unit tests for the Action.

Additional rules:

- Never embed environment-dependent costs (bcrypt rounds, cache TTLs,
  token expirations, retry intervals) as frozen literals when the same
  cost is configurable elsewhere. Derive from `config(...)` or compute
  once and memoize so the value tracks the configured source.

### Code Reviewer (`~/.claude/agents/code-reviewer.md`)

Drop the Livewire checks. Add these instead:

- Controllers contain no business logic (delegate to Actions).
- No Eloquent models returned directly from controllers (wrap in Resource).
- No inline `$request->validate()` (use Form Request).
- Authorization enforced via Form Request `authorize()` or Policy.
- Feature tests assert JSON structure + status codes, not HTML.
- Exception paths return the JSON error envelope, not Blade or redirects.
- When a package's config file is published into `config/`, unused
  package defaults (stateful domains, guards, drivers, middleware lists)
  must be trimmed or nulled out to match the project's posture. Flag any
  published config that retains defaults the project cannot hit.

### QA (`~/.claude/agents/qa.md`)

Replace `Livewire::test()` steps with HTTP feature tests. Manual testing
steps should be expressed as `curl` or HTTP request examples, not browser
clicks.

### Auditor (`~/.claude/agents/auditor.md`)

- Replace the **"Livewire 4 Compliance"** dimension with **"API Design"**:
  - Correct HTTP verbs and status codes (201 on create, 204 on delete, etc.).
  - Proper use of Form Requests and Resources.
  - Consistent JSON error envelope.
  - Versioning respected (`/api/v1`).
  - Idempotency where applicable.
- Keep the other five dimensions as-is.

### Improver (`~/.claude/agents/improver.md`)

Workflow improvement proposals for this repo should target **this file**
first, not the global agent files.

---

## 5. Environment

All commands run inside the `mybible-api-app` Docker container — see
`CLAUDE.md` at the repo root and `Makefile`. Do not run `php artisan`,
`vendor/bin/pint`, or `vendor/bin/phpstan` directly on the host.

```bash
# Run all tests
make test

# Filter a specific test
make test filter=test_it_creates_a_verse

# Code style
make lint-fix

# Static analysis
make stan

# Full gate (lint + stan + test)
make check
```

---

## 6. Project-Specific Rules

- Never scaffold Livewire, Blade, Tailwind, Vite, or npm assets — this
  project is API-only.
- Never add auth middleware (`auth:sanctum`, `auth:web`) until the auth
  story is decided. If a route needs to be protected, stub it with a TODO.
- Never return HTML or plain-text responses. Every response is JSON.
- Every new endpoint must have at least one feature test.

---

## 7. Deferred Extractions Tripwire

Conscious duplication deferrals tracked across stories. Each entry names the
pattern, the current copy-count, the extraction threshold, and the locations
involved. **Architect** consults this register when planning; **Auditor**
checks it on pass; **Improver** updates counts on story close. When a count
reaches its threshold, the next story extracts before adding a new copy.

| Pattern | Copies | Locations | Extract at | Notes |
|---|---|---|---|---|
| Owner-`authorize()` block (Form Request checks `subscription->user_id === request->user()->id`) | 4 | `CompleteReadingPlanSubscriptionDayRequest` (MBA-003); `RescheduleReadingPlanSubscriptionRequest`, `FinishReadingPlanSubscriptionRequest`, `AbandonReadingPlanSubscriptionRequest` (MBA-004) | 5 | Extract to a trait or base request when the 5th owner-gated endpoint lands. |
| `withProgressCounts()` helper on lifecycle Actions | 2 | `FinishReadingPlanSubscriptionAction`, `AbandonReadingPlanSubscriptionAction` (MBA-004) | 3 | Extract to a shared trait/service when a third lifecycle Action (resume, restart, transfer) lands. |
| Sabbath School post-cutover schema cleanup queue (MBA-025) | n/a — note | `sabbath_school_highlights` (drop `passage`; flip `segment_content_id`/`start_position`/`end_position`/`color` to NOT NULL; restore FK), `sabbath_school_answers` (flip `segment_content_id` NOT NULL; add FK), `sabbath_school_questions` table drop, `sabbath_school_segments` (drop legacy `day` and `passages`), `sabbath_school_lessons` (drop legacy `week_start`/`week_end`), `sabbath_school_favorites` (replace functional COALESCE unique with a true partial index when DB supports it). | n/a | Sequenced by MBA-031 (data ETL) + MBA-032 (NOT NULL flips, drops). Auditor & Improver pick this up so a future sweep does not re-introduce the legacy surfaces. |
| Retired surface (MBA-025): `POST /api/v1/admin/sabbath-school/segments/{segment}/questions/reorder` removed; replaced by `…/segments/{segment}/contents/reorder`. | n/a — note | Sabbath School admin | n/a | Watch future stories so a parallel question-reorder endpoint is not reintroduced. |
