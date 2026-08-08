# Release 1.3.0 — Bulk Pagination Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** "Generate All" processes the entire media library (not just 100 images) in internal batches, with a cost confirmation when more than 100 images are pending; review-notice threshold drops to 5; minor i18n/JS fixes ship alongside.

**Architecture:** Today the bulk page receives up to 100 image IDs server-side via `wp_localize_script` (`insagBulkData.imageIds`, query capped at `posts_per_page => 100` in `admin/class-sag-admin.php:94`). There is NO REST endpoint for the bulk scan — we add one (`GET /insag/v1/bulk/scan`) that returns one batch of IDs plus the library-wide total, and the React bulk app switches from the static localized list to a fetch-batch-process loop. The localized data keeps only `total` for the initial render.

**Tech Stack:** PHP 8.1 (WordPress plugin, PHPUnit 11.5 + Brain Monkey), React via `@wordpress/scripts` v30. Spec: `docs/superpowers/specs/2026-08-07-bulk-pagination-design.md`.

## Global Constraints

- Test command: `php vendor/phpunit/phpunit/phpunit --no-coverage` (PHP at `C:\tools\php85`). Suite is currently 70 tests green; it must stay green.
- Build command: `npm run build` (wp-scripts). NEVER edit files under `build/` by hand.
- Text domain: `internick-smart-alt-generator`. Prefix everything `INSAG_` / `insag_`. REST namespace: `insag/v1`.
- No BOM in any PHP file (never write PHP with PowerShell `Set-Content -Encoding utf8`).
- Version stays 1.2.1 until the final release task (Task 7) bumps to 1.3.0.

---

### Task 1: Pure query-args helper for paged "missing alt" scans

The scan query currently lives inline in `INSAG_Admin` (`admin/class-sag-admin.php:90-100`). Extract the args into a pure static method on `INSAG_Media` so both the admin page and the new REST endpoint share it, and so it is unit-testable without WordPress.

**Files:**
- Modify: `includes/class-sag-media.php`
- Test: `tests/MediaTest.php` (create if it does not exist; follow the style of `tests/AuditTest.php` — Brain Monkey `setUp`/`tearDown` come from the shared `tests/TestCase.php` base if present; check how `AuditTest` bootstraps and copy it)

**Interfaces:**
- Produces: `INSAG_Media::missing_alt_query_args( int $page ): array` — WP_Query args array, `posts_per_page => 100`, `paged => max(1, $page)`, `fields => 'ids'`, `no_found_rows => false`, plus the existing attachment/meta_query filters.

- [ ] **Step 1: Write the failing test**

```php
public function test_missing_alt_query_args_first_page() {
    $args = \INSAG_Media::missing_alt_query_args( 1 );
    $this->assertSame( 'attachment', $args['post_type'] );
    $this->assertSame( 100, $args['posts_per_page'] );
    $this->assertSame( 1, $args['paged'] );
    $this->assertSame( 'ids', $args['fields'] );
    $this->assertFalse( $args['no_found_rows'] );
    $this->assertSame( 'OR', $args['meta_query']['relation'] );
}

public function test_missing_alt_query_args_clamps_page_to_one() {
    $this->assertSame( 1, \INSAG_Media::missing_alt_query_args( 0 )['paged'] );
    $this->assertSame( 3, \INSAG_Media::missing_alt_query_args( 3 )['paged'] );
}
```

- [ ] **Step 2: Run to verify it fails** — `php vendor/phpunit/phpunit/phpunit --no-coverage --filter MediaTest`. Expected: error, method not defined.

- [ ] **Step 3: Implement** — add to `INSAG_Media`:

```php
/**
 * WP_Query args for one page (100 items) of images missing alt text.
 * Shared by the bulk admin page and the /bulk/scan REST endpoint.
 *
 * @param int $page 1-based page number.
 * @return array
 */
public static function missing_alt_query_args( $page ) {
    return array(
        'post_type'      => 'attachment',
        'post_mime_type' => 'image',
        'post_status'    => 'inherit',
        'posts_per_page' => 100,
        'paged'          => max( 1, (int) $page ),
        'orderby'        => 'ID',
        'order'          => 'ASC',
        'fields'         => 'ids',
        'no_found_rows'  => false,
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Intentional and unavoidable: finding attachments missing alt text requires a meta_query. Bounded to 100 results per page.
        'meta_query'     => array(
            'relation' => 'OR',
            array( 'key' => '_wp_attachment_image_alt', 'compare' => 'NOT EXISTS' ),
            array( 'key' => '_wp_attachment_image_alt', 'value' => '', 'compare' => '=' ),
        ),
    );
}
```

- [ ] **Step 4: Run tests** — full suite green (`php vendor/phpunit/phpunit/phpunit --no-coverage`).
- [ ] **Step 5: Commit** — `git add includes/class-sag-media.php tests/MediaTest.php && git commit -m "feat: shared paged query args for missing-alt scans"`

---

### Task 2: REST endpoint `GET /insag/v1/bulk/scan`

**Files:**
- Modify: `includes/class-sag-rest-api.php` (route registration is in the same file as the other `register_rest_route` calls; the handler goes next to `handle_audit_scan`, line ~228)
- Test: `tests/RestApiTest.php` if it exists (check first); if the existing suite does not unit-test REST handlers (they need WP_Query), do NOT force it — the handler is thin glue over Task 1's tested args and gets covered by E2E in Task 7.

**Interfaces:**
- Consumes: `INSAG_Media::missing_alt_query_args( $page )` from Task 1.
- Produces: REST response `{ page: int, total_pages: int, total: int, ids: int[] }`. Permission: same callback the bulk/generate flow already uses (`upload_files` — copy the exact `permission_callback` used by the `/generate` route registration in this file). Out-of-range page returns `ids: []` with the same metadata (WP_Query just returns no posts — no special-casing needed).

- [ ] **Step 1: Register the route** — inside the existing `register_routes` method, following the exact style of the `/audit/scan` registration:

```php
register_rest_route( 'insag/v1', '/bulk/scan', array(
    'methods'             => 'GET',
    'callback'            => array( $this, 'handle_bulk_scan' ),
    'permission_callback' => /* copy the /generate route's permission_callback verbatim */,
    'args'                => array(
        'page' => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
    ),
) );
```

- [ ] **Step 2: Implement the handler**

```php
/**
 * One page (100 IDs) of images missing alt text, plus the library-wide total,
 * so the bulk UI can batch through arbitrarily large libraries.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
public function handle_bulk_scan( $request ) {
    $page  = max( 1, (int) $request->get_param( 'page' ) );
    $query = new WP_Query( INSAG_Media::missing_alt_query_args( $page ) );

    return rest_ensure_response( array(
        'page'        => $page,
        'total_pages' => (int) $query->max_num_pages,
        'total'       => (int) $query->found_posts,
        'ids'         => array_map( 'intval', $query->posts ),
    ) );
}
```

- [ ] **Step 3: Sanity checks** — `php -l includes/class-sag-rest-api.php`; full PHPUnit suite still green.
- [ ] **Step 4: Commit** — `git commit -am "feat: paged bulk/scan REST endpoint with library-wide total"`

---

### Task 3: Admin page uses the shared args and localizes `total`

**Files:**
- Modify: `admin/class-sag-admin.php:90-120`

**Interfaces:**
- Consumes: `INSAG_Media::missing_alt_query_args( 1 )`.
- Produces: `window.insagBulkData = { total: int, nonce, restBase }` — `imageIds` is REMOVED (Task 4 stops reading it in the same release, so no compatibility shim is needed; both ship together).

- [ ] **Step 1: Replace the inline query** — the `$bulk_query = new WP_Query( array( ... ) )` block (lines 90-100) becomes:

```php
$bulk_query = new WP_Query( INSAG_Media::missing_alt_query_args( 1 ) );
```

- [ ] **Step 2: Update the localize call** (line 116) — replace `'imageIds' => $image_ids,` with `'total' => (int) $bulk_query->found_posts,` (keep `nonce` and `restBase` untouched). Delete any now-unused `$image_ids` assignment between the query and the localize.
- [ ] **Step 3: Sanity** — `php -l admin/class-sag-admin.php`; suite green.
- [ ] **Step 4: Commit** — `git commit -am "refactor: bulk page localizes total via shared scan args"`

---

### Task 4: React bulk app — batch loop with cost confirmation

**Files:**
- Modify: `src/admin-bulk/index.js`

**Interfaces:**
- Consumes: `window.insagBulkData.total` (Task 3); `GET /insag/v1/bulk/scan?page=1` returning `{ total, ids }` (Task 2); existing `POST /insag/v1/generate` with `{ image_id }`.
- Produces: same UI components (StatBar/ProgressBar/BulkControls/LogList unchanged); `BulkApp` gains the loop.

Behavior (from the spec):
- `total` for StatBar/ProgressBar comes from the localized `insagBulkData.total` (global count, e.g. "34 / 250").
- On "Generate All": if `total > 100`, `window.confirm()` with a translated, `sprintf`-style message before doing anything; cancel → stay `idle`.
- Loop: fetch `/bulk/scan?page=1`, process each returned ID not already attempted, repeat. Always page 1 (processed images leave the set). Track attempted IDs in a `Set` stored in a ref; stop (status `done`) when a fetched page yields no new IDs (covers both "all done" and "only persistent failures remain"). A failed page fetch also ends the run as `done`, with an error line in the log.
- Pause/resume: `pausedRef` checked between images as today; resume continues the current batch from the stored queue position, then keeps looping.

- [ ] **Step 1: Rewrite the loop** — replace the module-level `const { imageIds = [] } = ...` destructuring with `const { total = 0 } = window.insagBulkData ?? {};`, and inside `BulkApp` replace `runFrom`/`handleStart`/`handleResume` with:

```js
const attemptedRef = useRef( new Set() );
const queueRef = useRef( [] ); // remaining IDs of the current batch

const processQueue = useCallback( async () => {
	while ( true ) {
		while ( queueRef.current.length > 0 ) {
			if ( pausedRef.current ) {
				return;
			}
			const id = queueRef.current.shift();
			attemptedRef.current.add( id );
			try {
				const res = await apiFetch( {
					path: '/insag/v1/generate',
					method: 'POST',
					data: { image_id: id },
				} );
				addLog( id, true, res.alt_text );
				setSuccesses( ( s ) => s + 1 );
			} catch ( e ) {
				addLog( id, false, e?.message || __( 'Generation failed.', 'internick-smart-alt-generator' ) );
				setErrors( ( n ) => n + 1 );
			}
			setProcessed( ( p ) => p + 1 );
		}
		let scan;
		try {
			scan = await apiFetch( { path: '/insag/v1/bulk/scan?page=1' } );
		} catch ( e ) {
			addLog( 0, false, __( 'Could not load the next batch. Check your connection and press Generate All to retry.', 'internick-smart-alt-generator' ) );
			setStatus( 'done' );
			return;
		}
		const fresh = ( scan.ids || [] ).filter( ( id ) => ! attemptedRef.current.has( id ) );
		if ( fresh.length === 0 ) {
			setStatus( 'done' );
			return;
		}
		queueRef.current = fresh;
	}
}, [ addLog ] );

const handleStart = useCallback( () => {
	if (
		total > 100 &&
		// translators: %d: number of images that will be sent to the AI provider.
		! window.confirm( sprintf( __( 'This will generate alt text for %d images using your API key. Continue?', 'internick-smart-alt-generator' ), total ) )
	) {
		return;
	}
	pausedRef.current = false;
	attemptedRef.current = new Set();
	queueRef.current = [];
	setSuccesses( 0 );
	setErrors( 0 );
	setProcessed( 0 );
	setStatus( 'running' );
	processQueue();
}, [ processQueue ] );

const handleResume = useCallback( () => {
	pausedRef.current = false;
	setStatus( 'running' );
	processQueue();
}, [ processQueue ] );
```

Also: import `sprintf` from `@wordpress/i18n` (extend the existing `__` import); delete the now-unused `indexRef`; `const total` comes from the localized data as above; the `total === 0` empty-state block and the rest of the render stay unchanged.

- [ ] **Step 2: Build** — `npm run build`. Expected: success, no eslint errors.
- [ ] **Step 3: Grep sanity** — `grep -c imageIds build/admin-bulk*.js src/admin-bulk/index.js` returns 0 matches (old contract fully gone).
- [ ] **Step 4: Commit** — `git add src/admin-bulk/index.js build/ && git commit -m "feat: bulk generator batches through the whole library with cost confirmation"`

---

### Task 5: Review notice — threshold 10 → 5, dynamic copy, response.ok fix

**Files:**
- Modify: `includes/class-sag-review-notice.php:17` (THRESHOLD), `:113` (hardcoded "10+" copy)
- Modify: `admin/js/sag-review-notice.js:29-31`
- Test: `tests/ReviewNoticeTest.php:11,15,35`

**Interfaces:**
- Produces: `INSAG_Review_Notice::THRESHOLD === 5`; notice heading uses the constant via `sprintf`, so tests and copy can never drift again.

- [ ] **Step 1: Update the tests first** — in `tests/ReviewNoticeTest.php`: `test_hidden_below_threshold` uses count `4`; `test_shown_at_threshold_with_no_state` uses `5`; `test_unknown_state_treated_as_never_answered` uses `5`. Run: FAIL (threshold still 10).
- [ ] **Step 2: Implement** — in `class-sag-review-notice.php`: `const THRESHOLD = 5;` and replace the hardcoded heading with:

```php
<strong><?php
    /* translators: %d: minimum number of generated alt texts before this notice appears. */
    echo esc_html( sprintf( __( "You've generated alt text for %d+ images with Smart Alt Generator!", 'internick-smart-alt-generator' ), self::THRESHOLD ) );
?></strong>
```

- [ ] **Step 3: Fix the JS** — in `sag-review-notice.js`, replace the `.finally(...)` chain with:

```js
} ).then( function ( response ) {
    if ( response.ok ) {
        notice.remove();
    }
} ).catch( function () {
    // Network failure: keep the notice so the user can try again.
} );
```

- [ ] **Step 4: Run suite** — green. Note the copy change breaks the existing es_ES/pt_BR translation for that string; the catalogs are refreshed in Task 6.
- [ ] **Step 5: Commit** — `git commit -am "feat: lower review-notice threshold to 5; only dismiss on successful response"`

---

### Task 6: i18n — pt_BR Plural-Forms + refresh catalogs

**Files:**
- Modify: `languages/internick-smart-alt-generator-pt_BR.po` (+ recompiled `.mo`), `languages/internick-smart-alt-generator-es_ES.po` (+ `.mo`), JSON files per handle if the changed strings are JS-side.

- [ ] **Step 1: Plural-Forms** — in the pt_BR `.po` header: `"Plural-Forms: nplurals=2; plural=(n > 1);\n"`.
- [ ] **Step 2: Translate the new/changed strings** — the `sprintf` heading from Task 5 (es: "¡Generaste texto alternativo para más de %d imágenes con Smart Alt Generator!"; pt: "Você gerou texto alternativo para mais de %d imagens com o Smart Alt Generator!"), the cost-confirmation string and batch-error string from Task 4 (JS-side → they live in the JED `.json` files for the `insag-admin-bulk` handle, regenerated with `npx po2json`/`wp i18n make-json` per the existing workflow — check how the current `.json` files were produced in git history, commit `aca3b8a`-era, and repeat it).
- [ ] **Step 3: Recompile `.mo`** — same tool used before (check `languages/` git history; `msgfmt` or `wp i18n`).
- [ ] **Step 4: Commit** — `git commit -am "fix(i18n): pt_BR plural rule; translate 1.3.0 strings"`

---

### Task 7: Release 1.3.0 — bump, .pot, changelog, ZIP, E2E

**Files:**
- Modify: `internick-smart-alt-generator.php` (Version header + `INSAG_VERSION`), `readme.txt` (Stable tag + changelog), `languages/*.pot`, `package.json` version if tracked.

- [ ] **Step 1: Bump** — Version 1.3.0 in the main plugin header, `INSAG_VERSION`, readme `Stable tag`. Changelog entry:

```
= 1.3.0 =
* Bulk Generator now processes your entire media library, in batches of 100, with a progress counter across the whole run.
* A confirmation dialog shows the total number of images (and that your API key will be used) before large runs start.
* Review request now appears after 5 generated alt texts (was 10) and is only dismissed when the server confirms it.
* Brazilian Portuguese plural rule corrected.
```

- [ ] **Step 2: Regenerate `.pot` AFTER the bump** (so `Project-Id-Version` reads 1.3.0 — lesson from 1.2.1), then `npm run build`.
- [ ] **Step 3: E2E in Docker** — `f:/tmp/wp-aatg-docker` → http://localhost:8910/wp-admin (admin/admin123). Seed >100 images without alt via wp-cli (`docker compose exec -T wpcli wp media import ... ` in a loop, or `wp eval` creating attachment posts). Verify: cost dialog appears with the right count; progress advances past 100; pause/resume works mid-run; run ends `done`; review notice appears after 5 generations. (OpenAI key in that WP is expired — either set a valid key or verify the loop mechanics with errors accumulating, which exercises the no-new-IDs termination path.)
- [ ] **Step 4: Full suite + Plugin Check** — PHPUnit green; `docker compose exec -T wpcli wp plugin check internick-smart-alt-generator --format=csv` clean.
- [ ] **Step 5: ZIP** — Python `zipfile` (forward slashes, no BOM) → `f:\tmp\wp-aatg-dist\internick-smart-alt-generator.zip`; verify entries use `/` and version says 1.3.0.
- [ ] **Step 6: Commit + push** — `git commit -am "chore(release): 1.3.0"` and push master.
- [ ] **Step 7 (manual, Nick + TortoiseSVN):** sync `f:\tmp\svn-wc\trunk` with the new runtime, right-drag trunk → tags → "SVN Copy versioned item(s) here" → rename `1.3.0`, commit trunk + tag in ONE commit from the working-copy ROOT. Message: "Release 1.3.0: bulk generation for entire media libraries with batching and cost confirmation, lower review threshold, pt_BR plural fix. Tagging version 1.3.0." Then verify the WP.org API reports 1.3.0.
