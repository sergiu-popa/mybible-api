# Story: MBA-019-misc-api

## Title
Miscellaneous API — news, QR codes, mobile app version check

## Status
`in-review`

## Description
Three low-volume, low-complexity endpoints that don't fit anywhere
else. All read-only, all cacheable, all moving behind
`api-key-or-sanctum` at the same prefix.

Symfony source:
- `NewsController::index()` — platform news feed
- `QrCodeController::byPassage()` — generated QR codes for verses
- `VersionController::index()` — mobile app version / update check

## Acceptance Criteria

### News
1. `GET /api/v1/news?language={iso2}` returns paginated news items,
   newest first. Default 20/page, max 50.
2. Response: `{ data: [{ id, title, summary, content?, published_at,
   image_url?, language }, ...] }`.
3. `Cache-Control: public, max-age=300`.
4. Protected by `api-key-or-sanctum`.

### QR codes
5. `GET /api/v1/qr-codes?reference=GEN.1:1.VDC` returns metadata for a
   QR code addressed by Bible reference.
6. Response: `{ data: { reference, url, image_url } }` where `url` is
   the destination URL the QR encodes (usually a web viewer page) and
   `image_url` is the generated QR PNG on storage.
7. If the server doesn't have a precomputed QR for that reference,
   either generate on demand (Symfony may already do this) or `404`.
   Architect decides based on Symfony behavior.
8. `Cache-Control: public, max-age=86400` — QR content rarely changes.

### Mobile version check
9. `GET /api/v1/mobile/version?platform={ios|android}` returns:
   ```
   {
     "data": {
       "platform": "ios",
       "minimum_supported_version": "3.2.0",
       "latest_version": "3.4.1",
       "update_url": "https://apps.apple.com/...",
       "force_update_below": "3.0.0"
     }
   }
   ```
10. Anonymous access allowed — clients hit this before auth. Apply
    `api-key-or-sanctum`, but `X-Api-Key` is sufficient.
11. `Cache-Control: public, max-age=300`.

### Tests
12. Feature tests: news listing with language filter, QR code by
    reference (happy + missing), version check per platform.
13. Unit tests for Actions.

## Scope

### In Scope
- Three endpoints as listed.
- `News`, `QrCode` models. Mobile version data lives in config
  (`config/mobile.php`) — no model.
- Actions, API Resources, Feature tests.

### Out of Scope
- Admin CRUD for news.
- Dynamic QR generation (defer unless Symfony already does it and
  mobile relies on it).
- Push-notification subscriptions / feature flags (would be a
  separate "remote config" story).

## Technical Notes

### Mobile version config
```php
// config/mobile.php
return [
    'ios' => [
        'minimum_supported_version' => env('MOBILE_IOS_MIN', '3.0.0'),
        'latest_version' => env('MOBILE_IOS_LATEST', '3.4.1'),
        'update_url' => env('MOBILE_IOS_URL'),
        'force_update_below' => env('MOBILE_IOS_FORCE', '3.0.0'),
    ],
    'android' => [/* ... */],
];
```
Storing in config (env-driven) is fine for this low-change data. If
product wants self-serve editing later, migrate to a DB-backed
remote-config table.

### QR generation
If Symfony generates QRs on the fly (`endroid/qr-code` style), porting
the generation is straightforward (`bacon/bacon-qr-code` is the Laravel
equivalent). If QRs are pre-rendered and stored, we only serve URLs.
Confirm behavior via Symfony source before committing to a library.

## Dependencies
- **MBA-005** (auth).
- **MBA-006** (for QR reference validation).

## Open Questions for Architect
1. **QR generation or serve-only.** Inspect Symfony; pick the lower-
   complexity path.
2. **News soft-delete.** Admin might unpublish. Does Symfony have a
   `published_at NULL / future` filter? Mirror whatever it does.
3. **Force update behavior.** Does the mobile client expect the
   response shape above (field names `force_update_below`)? Align
   with the existing client contract — this is one place where
   backward-compat matters because clients use it to decide whether
   to block usage.
