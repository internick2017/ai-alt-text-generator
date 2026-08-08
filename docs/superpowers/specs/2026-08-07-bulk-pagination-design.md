# Release 1.3.0 — Bulk pagination for large libraries

**Date:** 2026-08-07
**Status:** Approved by Nick (scope B: recortada, UX opción C: transparente con confirmación de costo)

## Goal

Remove the known 100-image limit of the Bulk Generator so it works on large media
libraries, lower the review-notice threshold to unlock the first reviews, and ship
the pending minor fixes. A release also refreshes the WP.org listing and pushes an
update to existing users (historically the main driver of download spikes).

## Scope

In:
1. Bulk pagination: "Generate All" processes the entire library in internal batches
   of 100, with a cost confirmation dialog when more than 100 images are pending.
2. Review notice threshold: 10 → 5 successful generations.
3. Minor fixes: `response.ok` check in `sag-review-notice.js`; pt_BR
   `Plural-Forms: nplurals=2; plural=(n > 1);`; regenerate the `.pot` AFTER the
   version bump.

Out (deferred to a later release): migrating `admin/js/sag-media.js` to React;
incremental Audit re-scan.

## Design

### 1. Backend — bulk scan endpoint (`includes/class-sag-rest-api.php`)

- The bulk scan endpoint accepts a `page` parameter (integer, default 1, min 1).
- The existing WP_Query keeps `posts_per_page = 100` and adds `paged`.
- The response adds `total` (count of ALL images without alt text in the library,
  from `found_posts`) and `total_pages`, alongside the current page's images.
- A page beyond `total_pages` returns an empty image list with the same `total` /
  `total_pages` metadata (not an error).

### 2. Frontend — bulk page (`src/admin-bulk/index.js`)

- On "Generate All": request page 1 first. If `total > 100`, show a confirmation
  dialog ("This will generate N alt texts using your API key. Continue?") before
  processing anything. 100 or fewer behaves exactly as today (no dialog).
- Batch loop: process every image in the current batch (one at a time, as today),
  then request page 1 again and continue until the returned list is empty.
  Always re-request **page 1**: each processed image leaves the "missing alt"
  set, so the set shifts; re-reading page 1 avoids skipping images. Images that
  fail (error) stay in the set, so the loop terminates when a fetched page
  contains only images already attempted this run — track attempted IDs and stop
  when a page yields no new IDs (prevents an infinite loop on persistent errors).
- StatBar shows global progress ("34 / 250") using `total` from the first scan.
- Errors: per-image errors accumulate in the log as today; a failed page fetch
  ends the run in state "done" with the errors visible. Each image is atomic, so
  nothing is left half-written.

### 3. Review notice threshold (`includes/class-sag-review-notice.php`)

- Threshold constant 10 → 5. Existing later/forever/reviewed dismissal logic
  unchanged. Users currently at 5-9 generations see the notice on their next
  visit to a plugin page.

### 4. Minor fixes

- `admin/js/sag-review-notice.js`: only remove the notice from the DOM when
  `response.ok` is true.
- `languages/*pt_BR.po` (+ recompiled `.mo`): `Plural-Forms` → `(n > 1)`.
- Regenerate `languages/*.pot` after bumping the version so
  `Project-Id-Version` reads 1.3.0 (lesson from 1.2.1).

## Testing

- TDD (PHPUnit + Brain Monkey), suite must stay green (70 tests today):
  - Pagination: `page` respected, `total`/`total_pages` in the response,
    out-of-range page returns empty list + metadata.
  - Review notice: threshold 5 boundary (4 = no notice, 5 = notice).
- Frontend batch loop covered by manual E2E: Docker WP 7.0 (localhost:8910,
  admin/admin123) seeded with >100 images without alt — verify the cost dialog,
  global progress counter, and completion across batches.

## Release procedure (1.3.0)

- Bump version (header + `INSAG_VERSION` + Stable tag), changelog, readme.
- `npm run build` (bundles), regenerate `.pot` post-bump.
- ZIP with Python `zipfile` (forward slashes, no BOM) into `f:\tmp\wp-aatg-dist\`.
- SVN: sync trunk, create `tags/1.3.0` via TortoiseSVN copy, commit trunk + tag
  in ONE commit from the working-copy ROOT (`f:\tmp\svn-wc`). Verify the WP.org
  API reports 1.3.0.
