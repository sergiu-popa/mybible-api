# Parity Checklist — Symfony → Laravel Cutover

One row per endpoint migrated across MBA-005..MBA-019. `Sign-off` is a
checkbox toggled by the on-call engineer during pre-flight. Any unchecked
row at T-1h blocks cutover.

## Expected shape diffs (do not block)

The parity-diff script (`scripts/parity-diff.sh`) normalises JSON with
`jq -S` and strips these known-different envelope fields before diffing:

- **Pagination envelope.** Laravel returns `{ data, meta, links }`;
  Symfony returned `{ items, pagination }`. Shape diff accepted; content
  must match after mapping `items → data`.
- **Error envelope.** Laravel returns `{ message, errors }`; Symfony
  returned `{ error, violations }`. Shape diff accepted.
- **Timestamps.** Laravel emits ISO-8601 with `+00:00`; Symfony emitted
  with `Z`. Normalised in the diff script.
- **Null casing.** Laravel returns `null`; Symfony sometimes omitted the
  field. Diff script injects missing-as-null before comparing.

Any diff outside this list is a blocker.

## Endpoints

| # | Method | Symfony URL | Laravel URL | Story | Feature test | Sign-off |
|---|---|---|---|---|---|---|
| 1 | POST | `/api/auth/register` | `/api/v1/auth/register` | MBA-005 | `tests/Feature/Api/V1/Auth/RegisterUserTest.php` | [ ] |
| 2 | POST | `/api/auth/login` | `/api/v1/auth/login` | MBA-005 | `tests/Feature/Api/V1/Auth/LoginUserTest.php` | [ ] |
| 3 | POST | `/api/auth/logout` | `/api/v1/auth/logout` | MBA-005 | `tests/Feature/Api/V1/Auth/LogoutUserTest.php` | [ ] |
| 4 | GET | `/api/auth/me` | `/api/v1/auth/me` | MBA-005 | `tests/Feature/Api/V1/Auth/MeTest.php` | [ ] |
| 5 | POST | `/api/auth/forgot-password` | `/api/v1/auth/forgot-password` | MBA-005 | `tests/Feature/Api/V1/Auth/ForgotPasswordTest.php` | [ ] |
| 6 | POST | `/api/auth/reset-password` | `/api/v1/auth/reset-password` | MBA-005 | `tests/Feature/Api/V1/Auth/ResetPasswordTest.php` | [ ] |
| 7 | GET | `/api/reference/parse` | resolved via `App\Domain\Reference\Reference` | MBA-006 | `tests/Unit/Domain/Reference/ReferenceParserTest.php` | [ ] |
| 8 | GET | `/api/bible-versions` | `/api/v1/bible-versions` | MBA-007 | `tests/Feature/Api/V1/Bible/ListBibleVersionsTest.php` | [ ] |
| 9 | GET | `/api/bible-versions/{abbr}/export` | `/api/v1/bible-versions/{version:abbreviation}/export` | MBA-007 | `tests/Feature/Api/V1/Bible/ExportBibleVersionTest.php` | [ ] |
| 10 | GET | `/api/books` | `/api/v1/books` | MBA-007 | `tests/Feature/Api/V1/Bible/ListBooksTest.php` | [ ] |
| 11 | GET | `/api/books/{abbr}/chapters` | `/api/v1/books/{book:abbreviation}/chapters` | MBA-007 | `tests/Feature/Api/V1/Bible/ListChaptersTest.php` | [ ] |
| 12 | GET | `/api/verses` | `/api/v1/verses` | MBA-008 | `tests/Feature/Api/V1/Verses/ListVersesTest.php` | [ ] |
| 13 | GET | `/api/daily-verse` | `/api/v1/daily-verse` | MBA-008 | `tests/Feature/Api/V1/Verses/DailyVerseTest.php` | [ ] |
| 14 | GET | `/api/favorites` | `/api/v1/favorites` | MBA-010 | `tests/Feature/Api/V1/Favorites/ListFavoritesTest.php` | [ ] |
| 15 | POST | `/api/favorites` | `/api/v1/favorites` | MBA-010 | `tests/Feature/Api/V1/Favorites/StoreFavoriteTest.php` | [ ] |
| 16 | PATCH | `/api/favorites/{id}` | `/api/v1/favorites/{favorite}` | MBA-010 | `tests/Feature/Api/V1/Favorites/UpdateFavoriteTest.php` | [ ] |
| 17 | DELETE | `/api/favorites/{id}` | `/api/v1/favorites/{favorite}` | MBA-010 | `tests/Feature/Api/V1/Favorites/DeleteFavoriteTest.php` | [ ] |
| 18 | GET | `/api/favorite-categories` | `/api/v1/favorite-categories` | MBA-010 | `tests/Feature/Api/V1/Favorites/ListFavoriteCategoriesTest.php` | [ ] |
| 19 | POST | `/api/favorite-categories` | `/api/v1/favorite-categories` | MBA-010 | `tests/Feature/Api/V1/Favorites/StoreFavoriteCategoryTest.php` | [ ] |
| 20 | PATCH | `/api/favorite-categories/{id}` | `/api/v1/favorite-categories/{category}` | MBA-010 | `tests/Feature/Api/V1/Favorites/UpdateFavoriteCategoryTest.php` | [ ] |
| 21 | DELETE | `/api/favorite-categories/{id}` | `/api/v1/favorite-categories/{category}` | MBA-010 | `tests/Feature/Api/V1/Favorites/DeleteFavoriteCategoryTest.php` | [ ] |
| 22 | GET | `/api/notes` | `/api/v1/notes` | MBA-011 | `tests/Feature/Api/V1/Notes/ListNotesTest.php` | [ ] |
| 23 | POST | `/api/notes` | `/api/v1/notes` | MBA-011 | `tests/Feature/Api/V1/Notes/StoreNoteTest.php` | [ ] |
| 24 | PATCH | `/api/notes/{id}` | `/api/v1/notes/{note}` | MBA-011 | `tests/Feature/Api/V1/Notes/UpdateNoteTest.php` | [ ] |
| 25 | DELETE | `/api/notes/{id}` | `/api/v1/notes/{note}` | MBA-011 | `tests/Feature/Api/V1/Notes/DeleteNoteTest.php` | [ ] |
| 26 | GET | `/api/devotionals` | `/api/v1/devotionals` | MBA-012 | `tests/Feature/Api/V1/Devotional/ShowDevotionalTest.php` | [ ] |
| 27 | GET | `/api/devotionals/archive` | `/api/v1/devotionals/archive` | MBA-012 | `tests/Feature/Api/V1/Devotional/DevotionalArchiveTest.php` | [ ] |
| 28 | GET | `/api/devotional-favorites` | `/api/v1/devotional-favorites` | MBA-012 | `tests/Feature/Api/V1/Devotional/ListDevotionalFavoritesTest.php` | [ ] |
| 29 | POST | `/api/devotional-favorites/toggle` | `/api/v1/devotional-favorites/toggle` | MBA-012 | `tests/Feature/Api/V1/Devotional/ToggleDevotionalFavoriteTest.php` | [ ] |
| 30 | GET | `/api/hymnal-books` | `/api/v1/hymnal-books` | MBA-013 | `tests/Feature/Api/V1/Hymnal/ListHymnalBooksTest.php` | [ ] |
| 31 | GET | `/api/hymnal-books/{slug}/songs` | `/api/v1/hymnal-books/{book:slug}/songs` | MBA-013 | `tests/Feature/Api/V1/Hymnal/ListHymnalSongsTest.php` | [ ] |
| 32 | GET | `/api/hymnal-songs/{id}` | `/api/v1/hymnal-songs/{song}` | MBA-013 | `tests/Feature/Api/V1/Hymnal/ShowHymnalSongTest.php` | [ ] |
| 33 | GET | `/api/hymnal-favorites` | `/api/v1/hymnal-favorites` | MBA-013 | `tests/Feature/Api/V1/Hymnal/ListHymnalFavoritesTest.php` | [ ] |
| 34 | POST | `/api/hymnal-favorites/toggle` | `/api/v1/hymnal-favorites/toggle` | MBA-013 | `tests/Feature/Api/V1/Hymnal/ToggleHymnalFavoriteTest.php` | [ ] |
| 35 | GET | `/api/collections` | `/api/v1/collections` | MBA-014 | `tests/Feature/Api/V1/Collections/ListCollectionsTest.php` | [ ] |
| 36 | GET | `/api/collections/{slug}` | `/api/v1/collections/{topic}` | MBA-014 | `tests/Feature/Api/V1/Collections/ShowCollectionTest.php` | [ ] |
| 37 | GET | `/api/olympiad/themes` | `/api/v1/olympiad/themes` | MBA-015 | `tests/Feature/Api/V1/Olympiad/ListOlympiadThemesTest.php` | [ ] |
| 38 | GET | `/api/olympiad/themes/{book}/{chapters}` | `/api/v1/olympiad/themes/{book}/{chapters}` | MBA-015 | `tests/Feature/Api/V1/Olympiad/ShowOlympiadThemeTest.php` | [ ] |
| 39 | GET | `/api/reading-plans` | `/api/v1/reading-plans` | MBA-016 | `tests/Feature/Api/V1/ReadingPlans/ListReadingPlansTest.php` | [ ] |
| 40 | GET | `/api/reading-plans/{slug}` | `/api/v1/reading-plans/{plan:slug}` | MBA-016 | `tests/Feature/Api/V1/ReadingPlans/ShowReadingPlanTest.php` | [ ] |
| 41 | POST | `/api/reading-plans/{slug}/subscriptions` | `/api/v1/reading-plans/{plan:slug}/subscriptions` | MBA-016 | `tests/Feature/Api/V1/ReadingPlans/StoreReadingPlanSubscriptionTest.php` | [ ] |
| 42 | POST | `/api/reading-plan-subscriptions/{id}/days/{day}/complete` | `/api/v1/reading-plan-subscriptions/{subscription}/days/{day}/complete` | MBA-016 | `tests/Feature/Api/V1/ReadingPlans/CompleteReadingPlanSubscriptionDayTest.php` | [ ] |
| 43 | POST | `/api/reading-plan-subscriptions/{id}/finish` | `/api/v1/reading-plan-subscriptions/{subscription}/finish` | MBA-016 | `tests/Feature/Api/V1/ReadingPlans/FinishReadingPlanSubscriptionTest.php` | [ ] |
| 44 | POST | `/api/reading-plan-subscriptions/{id}/abandon` | `/api/v1/reading-plan-subscriptions/{subscription}/abandon` | MBA-016 | `tests/Feature/Api/V1/ReadingPlans/AbandonReadingPlanSubscriptionTest.php` | [ ] |
| 45 | PATCH | `/api/reading-plan-subscriptions/{id}/start-date` | `/api/v1/reading-plan-subscriptions/{subscription}/start-date` | MBA-016 | `tests/Feature/Api/V1/ReadingPlans/RescheduleReadingPlanSubscriptionTest.php` | [ ] |
| 46 | GET | `/api/sabbath-school/*` (educational resources) | (MBA-017 TBD) | MBA-017 | `tests/Feature/Api/V1/SabbathSchool/*` | [ ] |
| 47 | GET | `/api/profile` | `/api/v1/profile` | MBA-018 | `tests/Feature/Api/V1/Profile/*` | [ ] |
| 48 | GET | `/api/*` (misc) | `/api/v1/*` (misc) | MBA-019 | `tests/Feature/Api/V1/*` | [ ] |

## Pre-flight DB divergence check

Row counts on prod vs. staging after a Laravel-only write test. Values must
match exactly at T-1h.

| Table | Prod count | Staging count | Match |
|---|---|---|---|
| `users` | _tbd_ | _tbd_ | [ ] |
| `bible_versions` | _tbd_ | _tbd_ | [ ] |
| `bible_verses` | _tbd_ | _tbd_ | [ ] |
| `notes` | _tbd_ | _tbd_ | [ ] |
| `favorites` | _tbd_ | _tbd_ | [ ] |
| `devotionals` | _tbd_ | _tbd_ | [ ] |
| `hymnal_songs` | _tbd_ | _tbd_ | [ ] |
| `collection_topics` | _tbd_ | _tbd_ | [ ] |
| `reading_plan_subscriptions` | _tbd_ | _tbd_ | [ ] |

Fill in at T-1h during pre-flight. Any mismatch aborts the cutover.
