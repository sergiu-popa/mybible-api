#!/usr/bin/env bash
# parity-diff.sh — ad-hoc Symfony vs Laravel JSON response differ.
#
# Fires GET requests at both APIs with identical inputs, normalises the
# JSON (sort keys, strip known envelope fields), diffs, and exits
# non-zero on any unexpected divergence. Not run in CI — this is a
# pre-cutover spot-check tool.
#
# Usage:
#   ./scripts/parity-diff.sh <endpoints-file>
#
# <endpoints-file> is a plaintext file, one path per line (relative to
# the API root), e.g.:
#
#   /bible-versions?language=ro
#   /books?language=ro
#   /collections
#
# Env:
#   SYMFONY_BASE_URL   e.g. https://api-old.mybible.eu/api
#   LARAVEL_BASE_URL   e.g. https://api.mybible.eu/api/v1
#   API_KEY            X-API-Key header value
#
# Expected-diff handling: the normaliser renames Symfony envelope keys
# (items → data, pagination → meta) and strips volatile fields
# (timestamps, request_id). Anything surviving that is a real diff.

set -euo pipefail

if [[ $# -ne 1 ]]; then
    echo "usage: $0 <endpoints-file>" >&2
    exit 64
fi

endpoints_file="$1"

: "${SYMFONY_BASE_URL:?SYMFONY_BASE_URL must be set}"
: "${LARAVEL_BASE_URL:?LARAVEL_BASE_URL must be set}"
: "${API_KEY:?API_KEY must be set}"

if ! command -v jq >/dev/null 2>&1; then
    echo "jq is required but not installed." >&2
    exit 69
fi

if ! command -v curl >/dev/null 2>&1; then
    echo "curl is required but not installed." >&2
    exit 69
fi

tmpdir="$(mktemp -d)"
trap 'rm -rf "$tmpdir"' EXIT

# Rename Symfony envelope keys to Laravel's shape, strip volatile
# fields, and sort keys so diff is order-insensitive.
normalise() {
    jq -S '
        . as $root
        | if type == "object" then
            (if has("items") then .data = .items | del(.items) else . end)
            | (if has("pagination") then .meta = .pagination | del(.pagination) else . end)
            | del(.request_id, .generated_at, .timestamp)
          else . end
    '
}

exit_status=0

while IFS= read -r endpoint; do
    # Skip blank lines and comments.
    [[ -z "$endpoint" || "$endpoint" =~ ^# ]] && continue

    symfony_file="$tmpdir/symfony.json"
    laravel_file="$tmpdir/laravel.json"

    curl -fsS -H "X-API-Key: ${API_KEY}" -H 'Accept: application/json' \
        "${SYMFONY_BASE_URL}${endpoint}" | normalise > "$symfony_file" || {
        echo "FAIL  ${endpoint}  (Symfony request failed)"
        exit_status=1
        continue
    }

    curl -fsS -H "X-API-Key: ${API_KEY}" -H 'Accept: application/json' \
        "${LARAVEL_BASE_URL}${endpoint}" | normalise > "$laravel_file" || {
        echo "FAIL  ${endpoint}  (Laravel request failed)"
        exit_status=1
        continue
    }

    if diff -u "$symfony_file" "$laravel_file" > "$tmpdir/diff.out"; then
        echo "OK    ${endpoint}"
    else
        echo "DIFF  ${endpoint}"
        sed 's/^/    /' "$tmpdir/diff.out"
        exit_status=1
    fi
done < "$endpoints_file"

exit "$exit_status"
