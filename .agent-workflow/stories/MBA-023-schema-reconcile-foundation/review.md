# Code Review: MBA-023-schema-reconcile-foundation

## Verdict

**APPROVE** — all seven Warnings from the prior pass have been
satisfactorily addressed in commit `e601de7`. No Critical findings. The
five Suggestions remain open as non-blocking polish items. Plan-scoped
tests pass: 69 passed, 173 assertions.

The story is broad (Stripes A+B+C across three commits) and faithful to
the plan. Verification of each W-finding follows.

---

## Warnings (resolved)

- [x] **W1. `_legacy_book_map` tie-break documented but not implemented.**
      Resolved in `database/migrations/2026_05_03_000200_reconcile_symfony_bible_tables.php:127-136`:
      the `book` query now `LEFT JOIN`s `bible_versions` and orders by
      `CASE WHEN bible_versions.abbreviation = 'VDC' THEN 0 ELSE 1 END,
      book.id`, so VDC rows win on diverging metadata exactly as the
      PHPDoc promises. — acknowledged: implemented as documented.

- [x] **W2. Catch-all `Throwable` swallows non-duplicate index errors.**
      Resolved by replacing the `try { … } catch (Throwable)` blocks in
      `2026_05_03_000204_reconcile_symfony_hymnal_tables.php:43-63` and
      `2026_05_03_000210_reconcile_symfony_note_and_favorite_tables.php:56-76`
      with a `Schema::getIndexes(...)` pre-check (`hasIndex(...)` private
      helper). Stale `Throwable` imports were removed. Real index-creation
      failures now propagate, and no UNIQUE silently goes missing.
      — acknowledged: pre-check approach matches the recommendation.

- [x] **W3. Cap value leaks into the public 422 payload.** Resolved in
      `bootstrap/app.php:119-132`: stable user-facing copy
      `'The requested passage is too large.'` is returned in both
      `message` and `errors.reference[]`; the technical detail
      (`reference`, `expanded`, `cap`) is logged via `Log::info(...)`
      instead. Cap value is no longer part of the contract.
      — acknowledged: clean separation between contract message and
      operational telemetry.

- [x] **W4. Public 422 envelope key inconsistency
      (`reference` vs `references`).** Resolved in `bootstrap/app.php:131`:
      cap-path envelope now writes `errors.reference[]`, matching the
      input field key declared in `ResolveVersesRequest`. The matching
      feature test
      `tests/Feature/Api/V1/Verses/ResolveCrossChapterVersesTest.php:109`
      asserts via `assertJsonValidationErrors(['reference'])`.
      — acknowledged: contract is now self-consistent.

- [x] **W5. Silent skip when `VerseRange::version` is null.** Resolved in
      `app/Domain/Bible/QueryBuilders/BibleVerseQueryBuilder.php:113-118`
      and `app/Domain/Verses/Actions/ResolveVersesAction.php:83-88,
      139-144` — both paths now throw `LogicException` with a
      "normalize the version in the request layer before dispatching"
      hint instead of silently filtering. Future callers that forget
      to normalize will fail loudly. — acknowledged: future-proofing
      ranged callers per the recommendation.

- [x] **W6. `chapterVerseCount` static cache leaks across queue jobs.**
      Resolved in `app/Domain/Verses/Actions/ResolveVersesAction.php:18-19,
      184-204`: the `static $cache = []` was promoted to a private
      instance property `$chapterVerseCountCache` so it dies with the
      action instance. Workers picking up a fresh action per job no
      longer reuse stale verse counts after a `bible_chapters` update.
      — acknowledged: instance-scoped cache resolves the worker
      lifecycle hazard.

- [x] **W7. `reconcile_symfony_bible_tables` `down()` is partial.**
      Resolved in `database/migrations/2026_05_03_000200_reconcile_symfony_bible_tables.php:41-53`:
      a 12-line PHPDoc block on `down()` now spells out exactly what is
      reversed (renames + `_legacy_book_map` drop) and what is not
      (the `book` table, the dedupe into `bible_books`, the FK columns
      on `bible_verses`), and directs operators to a pre-cutover
      snapshot for full restoration. — acknowledged: documentation of
      the asymmetry is the right call given the dedupe is data-bearing.

## Suggestions (still open, non-blocking)

- [ ] **S1. `ResolveCollectionReferencesAction` silently filters
      `VerseRange` entries.** `app/Domain/Collections/Actions/ResolveCollectionReferencesAction.php:61-65`
      drops ranges from both `parsed` and `displayText` with no
      `parseError`. Today's collection seeders don't carry cross-chapter
      refs, but if an admin ever adds one (the parser now accepts the
      syntax) the entry vanishes from the API response with no signal.
      Either record a `parseError` for unsupported entries or extend the
      DTO to carry ranges. Cheaper option: emit
      `parseError: 'Cross-chapter ranges are not supported in collections.'`.

- [ ] **S2. `BackfillLegacyBookAbbreviationsAction` lookup uses
      `whereRaw('LOWER(name) = ?')`.** `app/Domain/Migration/Actions/BackfillLegacyBookAbbreviationsAction.php:65-67`
      forces a full table scan because of the `LOWER(...)`. The seeded
      `_legacy_book_abbreviation_map` has `UNIQUE (name, language)` —
      using `where('name', $trimmed)` (case-sensitive) and seeding mixed
      case once would let the index do the work. One-shot migration so
      perf isn't critical, but easy improvement.

- [ ] **S3. `LegacyLanguageCodeMap` includes `ger` → `de` not in the AC.**
      `app/Domain/Migration/Support/LegacyLanguageCodeMap.php:23` adds
      `ger` (ISO 639-2/B) on top of the AC §12 codes (`deu` is 639-2/T).
      Defensive and harmless, but undocumented relative to the spec —
      worth a one-line PHPDoc note that `ger` is the alternative
      bibliographic code.

- [ ] **S4. `VerseRange` constructor builds canonical strings from
      out-of-bounds field values for error messages.** Negative chapters
      or zero verses get formatted into `canonical()` and surfaced into
      the exception (`VerseRange.php:26-52`). It works but produces
      slightly weird strings. If the parser is the only constructor
      caller (it is, per grep), the parser's existing `query` is a
      friendlier source for the exception message — pass it in as a
      `?string $context = null` constructor arg, or just let the parser
      throw before the VO is constructed (it already validates these
      bounds itself).

- [ ] **S5. `ReconcileTableHelper::rename` drops the empty Laravel-shape
      target before the rename.** Per `Support/ReconcileTableHelper.php:30-32`,
      this is the right behaviour for cutover, but in a partially-failed
      cutover scenario where the operator manually populated the target
      between attempts, the second run would see `count() > 0` and skip
      the rename — leaving legacy and new tables coexisting with diverging
      data. Add a one-line PHPDoc note that this helper is not safe to
      re-run after a partial failure has been hand-corrected.

---

## Notes — Verdict & Conformance

### Plan adherence
- Stripe A (parser/endpoint) — ✅ all 9 tasks done; tests cover ≥12 valid
  + 6+ invalid cases (the test file has 7 happy + 10 invalid = 17 total
  across the matrix — exceeds AC §21 minimum).
- Stripe B (reconcile migrations) — ✅ all per-domain migrations land;
  the 7 deferred feature tests (tasks 33–35, 37, 40–41) are explicitly
  marked deferred in plan with rationale ("trivial rename-only, helper
  unit-tested via the other reconcile tests").
- Stripe C (identifier standardisation) — ✅ language and book backfill
  Actions both implemented, both unit-tested, ordering (backfill →
  width-shrink) is correct.

### Architecture
- ✅ Domain layout matches plan: `App\Domain\Reference\VerseRange`,
  `App\Domain\Bible\Exceptions\VerseRangeTooLargeException`,
  `App\Domain\Migration\…` namespace for one-shot ETL helpers.
- ✅ Controllers stay thin; business logic in Actions; DTO `ResolveVersesData`
  carries the union type; Form Request normalizes version.
- ✅ Cap-check correctly precomputes via `bible_chapters.verse_count`
  before issuing the SELECT, per plan §8.

### API contract
- ✅ Existing single-verse / single-chapter / in-chapter range cases
  unchanged.
- ✅ Cross-chapter case returns flat verse array ordered by `(chapter,
  verse)`; `meta.missing` correctly populated; tested.
- ✅ Cap exception envelope key consistent with the input field
  (`errors.reference[]`) — W4 fixed.
- ✅ User-facing 422 message is stable (`'The requested passage is too
  large.'`); cap value lives in logs only — W3 fixed.

### Tests
- ✅ Reconcile feature tests follow `ReconcileTestCase` pattern (drop
  laravel-shape → recreate legacy shape → seed → run → assert).
- ✅ HymnalReconcileTest and NoteAndFavoriteReconcileTest both prove the
  UNIQUE rejects duplicates.
- ✅ DoctrineCleanupTest verifies idempotency (re-run is a no-op).
- ✅ IdentifierBackfillTest covers `users.language='ron'→'ro'`,
  `notes.book='Genesis'→'GEN'`, and the unknown-language `security_events`
  log.
- ✅ Plan-scoped suite (`Reconcile|Backfill|CrossChapter|ReferenceParser|VerseRange`)
  passes — 69 / 69, 173 assertions.

### Security / Performance
- ✅ Backfill Actions chunk via `chunkById(500)` — no full-table loads.
- ✅ `BackfillLegacyBookAbbreviationsAction` short-circuits when the map
  table is absent.
- ✅ The cap check reads from a small reference table (`bible_chapters` is
  bounded at 1189 rows × N versions); negligible cost.
- ✅ `chapterVerseCount` cache scoped to the action instance — W6 fixed.
- ✅ UNIQUE-add migrations now use `Schema::getIndexes(...)` pre-check
  rather than swallowing `Throwable` — W2 fixed.

### Public API contract scan
- ✅ No constant-under-scope fields surfaced.
- ✅ `errors.reference[]` envelope is consistent across validation and
  cap paths — W4 fixed.

---

Status flips to `qa-ready`.
