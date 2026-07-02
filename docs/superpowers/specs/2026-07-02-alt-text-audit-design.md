# Alt Text Audit — Design Spec

**Plugin:** Internick - Smart Alt Generator
**Target version:** 1.2.0 (minor: new feature, no breaking changes)
**Date:** 2026-07-02
**Status:** Approved for planning

## Goal

Add an "Alt Text Audit" dashboard that scans the whole Media Library, scores the
accessibility of its image alt text, and lets the user fix every problem in place
(AI generation, manual edit, or dismiss). This repositions the plugin from "another
alt-text generator" (a saturated category) to an "AI-powered image accessibility tool",
which is a stronger search hook on WordPress.org and a better portfolio piece.

Scope is deliberately narrowed to **image alt text**, not general site accessibility
(contrast, ARIA, headings). That would be a different product.

## Non-goals (YAGNI)

- No decorative-image detection, redundant-phrase detection, or AI-based quality
  scoring (those were "Level 3"; explicitly out of this MVP).
- No merge of the existing Bulk page into Audit. They stay separate siblings.
- No Pro/paywall wiring. Monetization is a later, separate effort.
- No general accessibility checks beyond alt text.

## User experience

New page **Media -> Alt Text Audit** (`add_media_page`, capability `upload_files`),
a sibling of the existing Bulk page. Built with `@wordpress/components`, matching the
Settings and Bulk pages for consistency.

Layout, top to bottom:

1. **Score card.** A single 0-100 number = percentage of "healthy" images over total
   images, with a progress bar and a plain-language label (for example "82 / 100 —
   good shape"). "Healthy" = has real alt text and no audit flags (dismissed items
   count as healthy).
2. **Breakdown chips.** Five clickable counters, one per check: Missing, Empty,
   Duplicate, Too long, Placeholder. Clicking a chip filters the table to that problem.
3. **Problem table.** One row per problematic image: thumbnail, current alt text,
   problem badge(s), and three per-row actions:
   - **Generate with AI** — reuses the existing `POST /generate` endpoint.
   - **Edit + Save** — inline text field that saves manual alt text.
   - **Dismiss** — marks the item reviewed and removes it from the report.
4. **Generate all shown** button (header) that runs AI generation over the currently
   filtered/visible set, reusing `POST /generate`, with a count and a live progress
   counter, plus a confirmation because each call is a paid request. This lets a whole
   category (for example every missing-alt image) be fixed in one action, while per-row
   Generate stays for fine-grained control.
5. **Re-scan** button to refresh the audit.

## Analysis engine — `INSAG_Audit`

A new class in `includes/class-sag-audit.php` holding pure, unit-testable
classification logic (same style as the rest of the codebase). All checks operate on
metadata and strings, with **no AI calls**.

Rules (Level 2):

1. **missing** — the `_wp_attachment_image_alt` meta does not exist.
2. **empty** — the meta exists but is `''` or whitespace only.
3. **too_long** — alt length > 125 characters, measured with `mb_strlen` (WCAG guidance).
4. **placeholder** — the alt equals the image file's base name, or matches placeholder
   patterns such as `IMG_1234`, `DSC0001`, `screenshot`, `photo`, `image` followed by
   digits. Comparison is normalized (trim + lowercase, extension stripped).
5. **duplicate** — the same normalized alt (trim + lowercase) appears on 2 or more images.

A single image can carry multiple flags. An image is **healthy** when it has non-empty
alt text and no flags. Items with post meta `_insag_audit_dismissed = 1` are excluded
from all checks and counted as healthy.

The class exposes at least:

- `classify( int $image_id, ?string $alt ) : string[]` — per-image flags for the
  non-duplicate rules (missing/empty/too_long/placeholder). The `$alt` argument is
  `null` when the `_wp_attachment_image_alt` meta does not exist (yields **missing**)
  and a string (possibly empty/whitespace, yielding **empty**) when it does. The caller
  determines this with `metadata_exists()` / the raw meta value before calling, keeping
  `classify()` pure and easy to test.
- Duplicate detection is a set-level concern resolved during the batch scan (see below),
  not inside `classify()`.

## Batch scan + cache

Scanning runs in batches over REST so it never times out on large libraries, mirroring
the state-machine approach the Bulk page already uses.

- The React front iterates pages of 100 attachments, shows progress, and accumulates
  results client-side.
- **Duplicate detection needs a global view, resolved server-side.** During the scan the
  server accumulates a signature index of normalized alt values (a map of
  `signature -> [image_ids]`), stored in a short-lived transient keyed to the scan. On the
  final page the server marks every image whose signature count is >= 2 as `duplicate` and
  returns those in the response. The client only renders what the server reports; it does
  not compute duplicates itself. This keeps the count correct across page boundaries with
  a single source of truth.
- The final summary (score + per-check counts) is stored in a transient
  `insag_audit_summary` so returning to the page shows the score instantly without a
  re-scan. The transient is invalidated whenever an alt is generated, edited, or
  dismissed; the Re-scan button always forces a fresh pass.

## REST endpoints (namespace `insag/v1`)

All use strict `permission_callback`s, matching the existing per-attachment `edit_post`
pattern in `class-sag-rest-api.php`.

- `GET /audit/scan?page=N` — capability `upload_files`. Processes one page of image
  attachments and returns `{ page, total_pages, counts, items[] }`, where each item is
  `{ id, thumb, alt, flags[] }`. Maintains/updates the duplicate signature index.
- `POST /audit/dismiss` — args `{ image_id, dismissed }`, capability `edit_post` on the
  id. Sets or clears `_insag_audit_dismissed`. Invalidates `insag_audit_summary`.
- `POST /audit/set-alt` — args `{ image_id, alt }`, capability `edit_post` on the id.
  Saves manual alt text (sanitized). This endpoint does not exist today and is needed
  for the inline Edit action. Invalidates `insag_audit_summary`.
- **Generate reuses** the existing `POST /generate`; no new endpoint for AI generation.

## Integration and packaging

- New webpack entry point `admin-audit` (source under `src/admin-audit`) producing
  `build/admin-audit.js`, enqueued on the Audit page's admin hook. Note: verify the exact
  admin hook suffix at runtime and follow whatever pattern the existing Settings/Bulk
  enqueues use, so the bundle actually loads.
- The existing Bulk page is untouched.
- `readme.txt`: add the audit to the Description feature list, add a `1.2.0` changelog
  entry and Upgrade Notice, and tighten the description/tags around the accessibility
  angle ("WCAG", "accessibility audit") for discoverability. Bump `Stable tag` and the
  version header/constant to 1.2.0.

## Testing

- **PHPUnit for `INSAG_Audit`**, following the existing Brain Monkey pattern (37 green
  tests today): one case per rule plus edge cases — multibyte length (`mb_strlen`),
  placeholder with a file extension, whitespace-only empty, duplicate across pages,
  dismissed item excluded from counts, healthy image produces no flags.
- **Endpoint permission tests** (403 without the capability), following
  `SettingsEndpointTest` / the per-attachment `edit_post` deny test.
- **Manual E2E** in the WP 7.0 Docker environment via the headless browser, as in prior
  releases: run a scan on a seeded library, verify the score and breakdown, then verify
  each per-row action (Generate, Edit+Save, Dismiss) updates the row and the score.

## Success criteria

- Scanning a library of at least several hundred images completes without a timeout and
  reports correct counts for all five checks, including duplicates across page boundaries.
- Each per-row action (Generate, Edit+Save, Dismiss) persists and is reflected in the
  score after a re-scan.
- All new `INSAG_Audit` and endpoint tests pass alongside the existing suite.
- Plugin Check reports 0 errors; the 1.2.0 release is packaged the same way as 1.1.2.
