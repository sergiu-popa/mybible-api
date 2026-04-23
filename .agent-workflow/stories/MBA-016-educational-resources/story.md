# Story: MBA-016-educational-resources

## Title
Educational resources — categorized content library

## Status
`qa-ready`

## Description
Clients surface a library of educational resources (articles, videos,
PDFs) organized by category. Read-only — admin authors elsewhere.

Symfony source:
- `ResourceController::categories()` — list categories
- `ResourceController::byCategory()` — list resources for a category
- `ResourceController::show()` — resource detail (addressed by UUID)

Maps to `resource_category`, `resource` tables.

## Acceptance Criteria

### Categories
1. `GET /api/v1/resource-categories?language={iso2}` returns paginated
   categories.
   - Response: `{ data: [{ id, name, description?, language,
     resource_count }, ...] }`.
   - Default 50/page, max 100.
2. Protected by `api-key-or-sanctum`.
3. `Cache-Control: public, max-age=3600`.

### Resources by category
4. `GET /api/v1/resource-categories/{category}/resources` returns
   paginated resources within the category.
   - Supports `?type={article|video|pdf|audio}` filter.
   - Response: `{ data: [{ uuid, type, title, summary?, thumbnail_url?,
     published_at }, ...] }`.
   - 25 per page, max 100.
5. Sorted newest first by `published_at`.

### Resource detail
6. `GET /api/v1/resources/{resource:uuid}` returns full detail:
   `{ data: { uuid, type, title, summary, content, thumbnail_url?,
   media_url?, author?, published_at, category: { id, name } } }`.
7. Route-model binding uses the `uuid` column (NOT the integer id).
   Unknown uuid ⇒ `404`.
8. Protected by `api-key-or-sanctum`.

### Tests
9. Feature tests: category listing, resource listing with type filter,
   resource detail by uuid, 404 paths, pagination defaults respected.
10. Unit tests for Actions.

## Scope

### In Scope
- Three endpoints.
- `ResourceCategory` + `Resource` Eloquent models.
- Actions, API Resources, Feature tests.
- UUID route-model binding setup on `Resource`.

### Out of Scope
- Upload / admin authoring.
- User progress tracking on resources (e.g. "I watched this video") —
  not in Symfony; defer.
- Rating or review system.
- Media file streaming — we return URLs only, clients fetch media
  directly from wherever it is hosted.

## Technical Notes

### Resource types enum
Model as a PHP 8.1 enum (`article`, `video`, `pdf`, `audio`). Use
`Rule::enum()` in the Form Request for the `type` filter validation.

### UUID route-model binding
In `App\Models\Resource`:
```
public function getRouteKeyName(): string
{
    return 'uuid';
}
```
Keep an integer `id` primary key for FK performance. The `uuid` is
exposed but not the id.

### Media URL construction
If `media_url` is a relative path in the DB (e.g. `resources/xyz.pdf`),
the Resource class constructs the absolute URL via the configured disk
(`Storage::disk('s3')->url(...)`). Never leak storage paths raw.

## Dependencies
- **MBA-005** (auth).

## Open Questions for Architect
1. **Media storage disk.** Is it S3, local, or something else? If the
   Symfony app has `uploads/resources/...` paths, confirm the Laravel
   disk wiring before this story runs.
2. **UUID already set in DB?** The Symfony schema should have it
   (inventory says resource has `uuid`). If not, this story adds a
   migration to backfill.
3. **Language on resource.** Per-resource language column or inherited
   from the category? Confirm via schema.
