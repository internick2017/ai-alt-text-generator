# Review Notice + Audit Screenshot + i18n (es_ES, pt_BR) — Design Spec

**Plugin:** Internick - Smart Alt Generator
**Target version:** 1.2.1 (patch/minor: growth features, no breaking changes)
**Date:** 2026-07-03
**Status:** Approved for planning

## Goal

Unblock the plugin's growth bottleneck (0 reviews on WordPress.org) and improve the
listing, with three items:

1. A non-intrusive review request notice shown after 10 successful generations.
2. A third WP.org screenshot showcasing the Alt Text Audit dashboard.
3. Working es_ES and pt_BR translations, including the wiring the plugin currently lacks.

## 1. Review notice

### Architecture

New file `includes/class-sag-review-notice.php` with class `INSAG_Review_Notice`,
following the `INSAG_Audit` philosophy: decision logic is pure and unit-testable;
WordPress calls stay at the edges (hooks, `get_current_screen()`).

### State (wp_options)

| Option | Type | Meaning |
|---|---|---|
| `insag_generation_count` | int | Total successful generations, ever. |
| `insag_review_state` | string | `''` (never answered) \| `snoozed` \| `dismissed` \| `reviewed` |
| `insag_review_snooze_until` | int (timestamp) | Only meaningful while state is `snoozed`. |

Options are registered with `autoload` disabled where WordPress allows it
(`update_option( ..., false )` on first write) since they are admin-only.

### Counter

`INSAG_Generator::generate_for_image()` increments `insag_generation_count` by 1
only when generation succeeds (not on `WP_Error`). This is the single choke point:
REST `/generate`, the bulk generator, the audit page actions, and auto-generate on
upload all pass through it.

### Display rule — `should_show()` (pure method)

Show the notice only when ALL of:

- `insag_generation_count >= 10` (threshold is a class constant).
- `insag_review_state` is not `dismissed` and not `reviewed`.
- If state is `snoozed`, `now > insag_review_snooze_until`.

The pure method receives count/state/snooze/now as arguments. The screen check
(current screen is one of the three plugin pages: Settings, Bulk, Audit) lives in
the `admin_notices` hook callback, outside the pure class. The notice never renders
anywhere else in wp-admin.

### Notice UI and actions

Standard WP notice markup (`notice notice-info`), rendered only on the three plugin
screens, with a thank-you tone:

> "You've generated alt text for 10+ images with Smart Alt Generator! If it's saving
> you time, a review on WordPress.org would help a lot. 🙏"

Three actions, each hitting `POST /insag/v1/review/dismiss` (permission:
`manage_options`, standard REST nonce) with `action`:

| Button | `action` | Effect |
|---|---|---|
| "Leave a review ⭐" | `reviewed` | Opens `https://wordpress.org/support/plugin/internick-smart-alt-generator/reviews/#new-post` in a new tab; state → `reviewed` (never shows again). |
| "Maybe later" | `later` | State → `snoozed`, `insag_review_snooze_until` = now + 30 days. |
| "I already did / Don't show again" | `forever` | State → `dismissed` (never shows again). |

A small inline script (enqueued only when the notice renders) wires the buttons to
the REST call and removes the notice from the DOM on success. All notice strings are
translatable.

`uninstall.php` cleans up the three new options.

## 2. Screenshot-3 (Alt Text Audit)

- Environment: the WP 7.0 testing stack (`f:/tmp/wp-aatg-docker`, localhost:8910).
  Seed the library via WP-CLI if needed so the dashboard shows a realistic,
  compelling state: intermediate score and at least one visible row for each check
  type (missing, duplicate, too_long, placeholder).
- Capture with the browse skill at 1280px-wide desktop viewport: score header,
  per-issue counters, and several table rows with the Generate / Edit / Ignore
  buttons visible. No personal data (test images only).
- Destinations:
  1. `.wordpress-org/screenshot-3.png` in the Git repo.
  2. `assets/screenshot-3.png` in the SVN working copy (`f:\tmp\svn-wc\assets\`).
- `readme.txt` Screenshots section gains:
  `3. Alt Text Audit — score your media library's alt text, see every issue flagged, and fix each one with AI, manual edit, or ignore.`

## 3. Translations (es_ES, pt_BR)

### Missing wiring (must be added)

1. `load_plugin_textdomain( 'internick-smart-alt-generator', false, <plugin>/languages )`
   on init — PHP strings.
2. `wp_set_script_translations( $handle, 'internick-smart-alt-generator', <languages path> )`
   for each of the four React script handles (settings, bulk, audit, editor) — JS strings.
   Any React string not already wrapped in `__()` from `@wordpress/i18n` gets wrapped
   as part of this work (in scope).

### Files (in `languages/`)

- Regenerate the `.pot` first with `wp i18n make-pot` (current one predates the Audit
  feature).
- `internick-smart-alt-generator-es_ES.po` / `.mo` and `internick-smart-alt-generator-pt_BR.po` / `.mo`
  — translations authored by Claude: neutral/Spain Spanish for es_ES, Brazilian
  Portuguese for pt_BR. Includes the new review-notice strings.
- Per-script JSON files via `wp i18n make-json` (the format
  `wp_set_script_translations()` consumes).

### Verification

In the Docker site, switch the site language to Español and Português do Brasil and
visually confirm Settings, Bulk, and Audit render translated (validates the JSON
wiring, the most fragile part).

### Out of scope

translate.wordpress.org language packs (community process; bundled translations work
immediately and do not conflict).

## Testing

Unit (PHPUnit + Brain Monkey, on top of the existing 50 green tests):

- `should_show()`: below threshold; dismissed; reviewed; snoozed still active;
  snoozed expired; happy path.
- Counter: increments on success; does NOT increment when the provider returns
  `WP_Error`.
- `/review/dismiss`: rejects without `manage_options`; validates `action` against
  the three allowed values; persists the right state/timestamp.

E2E (Docker):

- Set `insag_generation_count = 10` via WP-CLI; notice appears on the three plugin
  pages and NOT on the general Dashboard.
- Exercise all three buttons (reviewed opens WP.org and the notice never returns;
  snooze hides it; forever kills it).
- Language switch verification (above) and the screenshot capture in the same session.

## Versioning and release

- Bump 1.2.0 → 1.2.1 (plugin header + `INSAG_VERSION`), readme changelog entry,
  `Stable tag: 1.2.1`.
- ZIP via the Python zipfile script (forward slashes, zero BOM) to `f:\tmp\wp-aatg-dist\`.
- Git: TDD atomic commits on branch `feat/v1.2.1`, merge to master, push to GitHub.
- SVN (Nick, TortoiseSVN, single commit from the working-copy ROOT): update `trunk/`,
  create `tags/1.2.1` (right-drag trunk → tags → "SVN Copy versioned item(s) here" →
  rename), and add `assets/screenshot-3.png`.
- Verify WP.org API reports 1.2.1.
