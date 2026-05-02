# Code Review: MBA-023-schema-reconcile-foundation

## Verdict

**REQUEST CHANGES** — no Critical findings, but several Warnings need
either engineer action or acknowledgment before APPROVE. Plan-scoped tests
all pass (69 passed, 173 assertions).

The work is broad (Stripes A+B+C in two commits) and faithful to the plan.
Findings below are concentrated around (1) defensive paths in the migration
helpers that swallow real errors, (2) divergence between the Bible
reconcile migration's documented tie-break and what it actually does, and
(3) a couple of public-API messaging choices worth a second look before
prod cutover.

---

## Warnings

- [x] **W1. `_legacy_book_map` tie-break documented but not implemented.**
      `database/migrations/2026_05_03_000200_reconcile_symfony_bible_tables.php:24-26`
      states "the row whose Bible's abbreviation is `VDC` wins; otherwise
      the lowest legacy `book.id`". The implementation in
      `reconcileBookTables()` (line 115-143) iterates legacy `book` rows in
      `id` order and inserts the first occurrence per USFM abbreviation —
      VDC is never preferred. Either implement the documented tie-break
      (join `bible.abbreviation` and sort `(bible.abbreviation = 'VDC') DESC,
      book.id ASC`) or amend the PHPDoc to match reality (lowest
      `book.id` wins regardless of bible). Risk-2: the `_legacy_book_map`
      is consumed by MBA-031 ETL — quiet metadata-loss is hard to detect
      after cutover.

- [x] **W2. Catch-all `Throwable` swallows non-duplicate index errors.**
      `database/migrations/2026_05_03_000204_reconcile_symfony_hymnal_tables.php:44-50`
      and `..._note_and_favorite_tables.php:57-63` wrap the `unique(...)`
      add in `try { ... } catch (Throwable) { /* already exists */ }`. Any
      other failure (lock-wait timeout, syntax error, MySQL connection
      drop) is silently swallowed and the migration reports success while
      the UNIQUE never lands — exactly the regression the AC §6–10 set out
      to prevent. Detect "duplicate key name" by inspecting
      `Schema::getIndexes($table)` (or matching MySQL error code 1061)
      before `unique()` instead of catching everything.

- [x] **W3. `bootstrap/app.php` leaks the cap value into the public 422
      payload.** Lines 118-123 render `VerseRangeTooLargeException` with
      `'errors' => ['references' => [$e->getMessage()]]`. The exception
      message is `'Verse range "MAT.19:1-20:34.VDC" expands to 600 verses
      (cap: 500).'` — exposing the cap to consumers means changing it
      becomes a contract change. Stable user-facing copy ("The requested
      passage is too large.") with the technical detail kept in logs is
      the safer default. Story AC §17/§22 doesn't pin the message text,
      so this is a one-line fix.

- [x] **W4. Public 422 envelope key is `references` (plural) for the cap
      path but `reference` (singular) for normal validation errors.**
      `app/Http/Requests/Verses/ResolveVersesRequest.php:31` declares the
      input field `reference`; `bootstrap/app.php:121` writes
      `errors.references[]`. Plan §2 deliberately kept the singular query
      param, but the cap envelope still uses the AC §17 plural. Pick one
      and use it consistently — clients reading `errors.reference` for
      validation failures will silently miss the cap error. Recommended:
      use `errors.reference` for both (singular matches the input key).

- [x] **W5. `BibleVerseQueryBuilder::lookupVerseRange` is not reachable
      with `version === null`, but the silent skip in `lookupReferences`
      (line 113-115) and the `null` short-circuit in `expandRange`
      (`ResolveVersesAction.php:128-130`) means a future caller that
      forgets to normalize will get *zero verses* back instead of an
      error. The ranges also won't show up in `meta.missing` because
      `expectedTuples` skips them. Either tighten the `VerseRange`
      constructor to require a non-null `version`, or assert (throw) in
      `lookupVerseRange` when version is null instead of silently
      filtering in `lookupReferences`. The current callers are correct;
      the warning is about future-proofing the contract.

- [x] **W6. `ResolveVersesAction::chapterVerseCount` static cache leaks
      across queue jobs.** `app/Domain/Verses/Actions/ResolveVersesAction.php:170-191`
      uses a `static $cache = []` keyed on `book|chapter`. Fine for FPM
      (one process per request) but Laravel queue workers reuse a process
      across many jobs — if `bible_chapters.verse_count` is updated, a
      worker keeps the stale value indefinitely. The cap-check now relies
      on this value for correctness (not just missing-set computation).
      Move the cache to an instance property (so it dies with the action
      instance), or use `Cache::driver('array')` keyed on the request, or
      drop it entirely (the model is small).

- [x] **W7. `reconcile_symfony_bible_tables` `down()` is partial.** Lines
      41-52 only un-rename `verses → verse` and `bible_versions → bible`,
      and drop `_legacy_book_map`. The `up()` also dedup-populates
      `bible_books` (rows added cannot be reverted) and drops the legacy
      `book` table. Either document this asymmetry in the PHPDoc (so a
      future operator knows `migrate:rollback` won't fully restore the
      legacy shape) or implement a full rollback. Same shape applies to
      `drop_doctrine_artefacts.php` `down()` (no-op by design — already
      documented; OK).

## Suggestions

- [ ] **S1. `ResolveCollectionReferencesAction` silently filters out
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
- ⚠ Cap exception envelope key inconsistency — see W4.

### Tests
- ✅ Reconcile feature tests follow `ReconcileTestCase` pattern (drop
  laravel-shape → recreate legacy shape → seed → run → assert).
- ✅ HymnalReconcileTest and NoteAndFavoriteReconcileTest both prove the
  UNIQUE rejects duplicates.
- ✅ DoctrineCleanupTest verifies idempotency (re-run is a no-op).
- ✅ IdentifierBackfillTest covers `users.language='ron'→'ro'`,
  `notes.book='Genesis'→'GEN'`, and the unknown-language `security_events`
  log.

### Security / Performance
- ✅ Backfill Actions chunk via `chunkById(500)` — no full-table loads.
- ✅ `BackfillLegacyBookAbbreviationsAction` short-circuits when the map
  table is absent.
- ✅ The cap check reads from a small reference table (`bible_chapters` is
  bounded at 1189 rows × N versions); negligible cost.
- ⚠ See W6 (static cache leaks across worker jobs).

### Public API contract scan
- ✅ No constant-under-scope fields surfaced (no `status` after a
  `where('status', …)`, etc.).
- ⚠ The `errors.references[]` vs `errors.reference[]` key inconsistency
  (W4) is the one contract concern.

---

Once W1–W7 are addressed (or each ticked with `— acknowledged: <reason>`),
status flips to `qa-ready`.
