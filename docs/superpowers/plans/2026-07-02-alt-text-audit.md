# Alt Text Audit Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a "Alt Text Audit" admin dashboard that scans the whole Media Library, scores its alt-text health, and fixes each problem in place (AI generate, manual edit, or dismiss).

**Architecture:** A new pure `INSAG_Audit` class holds all classification logic (unit-tested, no WordPress calls). The existing `INSAG_REST_API` gains three routes (`/audit/scan`, `/audit/dismiss`, `/audit/set-alt`) that do the WordPress data access and delegate judging to `INSAG_Audit`. A new React admin page (`src/admin-audit`) drives a paginated scan loop and renders the score, per-check chips, and a problem table. Batch scanning uses transients so duplicate detection has a global view and the running score survives across page requests.

**Tech Stack:** PHP 8.1+ (OOP, WordPress plugin), WordPress REST API, `@wordpress/element` + `@wordpress/components` (React), `@wordpress/scripts` (webpack), PHPUnit 11 + Brain Monkey.

## Global Constraints

- Requires WordPress 6.4+, PHP 8.1+; distributed on WordPress.org (Plugin Check must report 0 errors).
- Class/constant prefix `INSAG_`; option/meta/function prefix `insag_`; REST namespace `insag/v1`; text domain `internick-smart-alt-generator`.
- Scope is alt text only (no general accessibility, no decorative detection, no AI-based quality scoring — those are out).
- Level 2 checks only: `missing`, `empty`, `duplicate`, `too_long` (>125 chars), `placeholder`.
- Per-row actions: Generate (reuse `POST /generate`), Edit+Save (`/audit/set-alt`), Dismiss (`/audit/dismiss`).
- New DOM ids use the clean `insag-` prefix (NOT `ininsag-` — that stray double prefix was the 1.1.2 blank-page bug, already hotfixed in 1.1.3).
- Files must be saved as UTF-8 **without BOM** and with LF endings. Never use PowerShell `Set-Content -Encoding utf8` on plugin files (it adds a BOM on PS 5.1).
- Unit tests follow the existing Brain Monkey pattern: namespace `SAG\Tests`, extend `TestCase`, stub WordPress functions with `Brain\Monkey\Functions\when(...)`.
- Release as version **1.2.0** (minor).

---

## File Structure

- **Create** `includes/class-sag-audit.php` — `INSAG_Audit`: pure classification, duplicate detection, per-page summarize, score. No WordPress calls.
- **Modify** `includes/class-sag-plugin.php` — load the new class in `load_dependencies()`.
- **Modify** `includes/class-sag-rest-api.php` — register and handle the three `/audit/*` routes.
- **Create** `tests/AuditTest.php` — unit tests for `INSAG_Audit`.
- **Create** `tests/AuditEndpointTest.php` — permission tests for the dismiss/set-alt handlers.
- **Modify** `tests/bootstrap.php` — preload `class-sag-audit.php`.
- **Modify** `admin/class-sag-admin.php` — register the audit media page + enqueue its bundle.
- **Create** `admin/views/audit-page.php` — React mount point (`<div id="insag-audit-root">`).
- **Create** `src/admin-audit/index.js` — the React app.
- **Modify** `webpack.config.js` — add the `admin-audit` entry point.
- **Modify** `admin/views/bulk-page.php`, `admin/views/settings-page.php`, `src/admin-bulk/index.js`, `src/admin-settings/index.js` — normalize the leftover `ininsag-*-root` ids to `insag-*-root` (cosmetic cleanup, requires a rebuild).
- **Modify** `internick-smart-alt-generator.php`, `readme.txt` — bump to 1.2.0, changelog, feature copy.

---

## Task 1: `INSAG_Audit` pure classification class

**Files:**
- Create: `includes/class-sag-audit.php`
- Modify: `includes/class-sag-plugin.php` (add `require_once` in `load_dependencies()`, after `class-sag-generator.php` and before `class-sag-rest-api.php`)
- Modify: `tests/bootstrap.php` (add `'class-sag-audit.php'` to the `$includes` array, before `'class-sag-rest-api.php'`)
- Test: `tests/AuditTest.php`

**Interfaces:**
- Produces (consumed by Task 2/3):
  - `INSAG_Audit::MAX_ALT_LEN` (int, 125)
  - `INSAG_Audit::FLAG_MISSING|FLAG_EMPTY|FLAG_TOO_LONG|FLAG_PLACEHOLDER|FLAG_DUPLICATE` (string constants)
  - `INSAG_Audit::flags(): string[]`
  - `INSAG_Audit::normalize(string $alt): string`
  - `INSAG_Audit::is_placeholder(string $alt, string $filename): bool`
  - `INSAG_Audit::classify(?string $alt, string $filename): string[]`
  - `INSAG_Audit::find_duplicates(array $alts): array<string,bool>`
  - `INSAG_Audit::summarize(array $records, array $dupes): array` where each record is `['id'=>int,'alt'=>?string,'filename'=>string,'dismissed'=>bool]` and the return is `['counts'=>array<string,int>, 'healthy'=>int, 'items'=>array<array{id:int,alt:string,flags:string[]}>]`
  - `INSAG_Audit::score(int $healthy, int $total): int`

- [ ] **Step 1: Write the failing test**

Create `tests/AuditTest.php`:

```php
<?php
namespace SAG\Tests;

final class AuditTest extends TestCase {

    public function test_missing_when_alt_is_null() {
        $this->assertSame( array( 'missing' ), \INSAG_Audit::classify( null, 'photo.jpg' ) );
    }

    public function test_empty_when_blank_or_whitespace() {
        $this->assertSame( array( 'empty' ), \INSAG_Audit::classify( '', 'photo.jpg' ) );
        $this->assertSame( array( 'empty' ), \INSAG_Audit::classify( '   ', 'photo.jpg' ) );
    }

    public function test_healthy_alt_has_no_flags() {
        $this->assertSame( array(), \INSAG_Audit::classify( 'A golden retriever running on a beach', 'dog.jpg' ) );
    }

    public function test_too_long_uses_multibyte_length() {
        $this->assertContains( 'too_long', \INSAG_Audit::classify( str_repeat( 'á', 126 ), 'x.jpg' ) );
        $this->assertNotContains( 'too_long', \INSAG_Audit::classify( str_repeat( 'á', 100 ), 'x.jpg' ) );
    }

    public function test_placeholder_matches_filename_and_camera_patterns() {
        $this->assertContains( 'placeholder', \INSAG_Audit::classify( 'IMG_1234', 'IMG_1234.jpg' ) );
        $this->assertContains( 'placeholder', \INSAG_Audit::classify( 'IMG_1234.jpg', 'IMG_1234.jpg' ) );
        foreach ( array( 'DSC0001', 'img_20240101', 'screenshot 2024-01-01', 'untitled', '12345' ) as $alt ) {
            $this->assertContains( 'placeholder', \INSAG_Audit::classify( $alt, 'whatever.png' ), $alt );
        }
    }

    public function test_real_description_is_not_placeholder() {
        $this->assertNotContains( 'placeholder', \INSAG_Audit::classify( 'A red bicycle leaning on a wall', 'IMG_1234.jpg' ) );
    }

    public function test_find_duplicates_flags_repeated_normalized_alts() {
        $dupes = \INSAG_Audit::find_duplicates( array( 'Sunset', 'sunset ', 'Unique', '', '  ' ) );
        $this->assertArrayHasKey( 'sunset', $dupes );
        $this->assertArrayNotHasKey( 'unique', $dupes );
        $this->assertArrayNotHasKey( '', $dupes );
    }

    public function test_summarize_counts_items_and_healthy() {
        $records = array(
            array( 'id' => 1, 'alt' => null, 'filename' => 'a.jpg', 'dismissed' => false ),               // missing
            array( 'id' => 2, 'alt' => 'A clear description here', 'filename' => 'b.jpg', 'dismissed' => false ), // healthy
            array( 'id' => 3, 'alt' => 'Sunset', 'filename' => 'c.jpg', 'dismissed' => false ),            // duplicate (via dupes)
            array( 'id' => 4, 'alt' => 'IMG_9', 'filename' => 'IMG_9.jpg', 'dismissed' => true ),          // placeholder but dismissed -> healthy
        );
        $out = \INSAG_Audit::summarize( $records, array( 'sunset' => true ) );
        $this->assertSame( 1, $out['counts']['missing'] );
        $this->assertSame( 1, $out['counts']['duplicate'] );
        $this->assertSame( 0, $out['counts']['placeholder'] ); // dismissed excluded
        $this->assertSame( 2, $out['healthy'] );               // id2 + dismissed id4
        $this->assertCount( 2, $out['items'] );                // id1 + id3
    }

    public function test_score_percentage() {
        $this->assertSame( 100, \INSAG_Audit::score( 0, 0 ) );
        $this->assertSame( 50, \INSAG_Audit::score( 5, 10 ) );
        $this->assertSame( 82, \INSAG_Audit::score( 82, 100 ) );
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php vendor/phpunit/phpunit/phpunit --no-coverage --filter AuditTest`
Expected: FAIL — `Class "INSAG_Audit" not found`.

- [ ] **Step 3: Create the class**

Create `includes/class-sag-audit.php`:

```php
<?php
/**
 * Audit — pure classification of image alt-text quality.
 *
 * No WordPress calls and no I/O: everything here operates on plain strings so
 * it can be unit-tested in isolation. Data access (querying attachments) lives
 * in the REST controller; this class only judges the values it is handed.
 *
 * @package Smart_Alt_Generator
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class INSAG_Audit {

    /** Alt text longer than this many characters is flagged (WCAG guidance). */
    const MAX_ALT_LEN = 125;

    const FLAG_MISSING     = 'missing';
    const FLAG_EMPTY       = 'empty';
    const FLAG_TOO_LONG    = 'too_long';
    const FLAG_PLACEHOLDER = 'placeholder';
    const FLAG_DUPLICATE   = 'duplicate';

    /** All flag keys in display order. */
    public static function flags() {
        return array(
            self::FLAG_MISSING,
            self::FLAG_EMPTY,
            self::FLAG_DUPLICATE,
            self::FLAG_TOO_LONG,
            self::FLAG_PLACEHOLDER,
        );
    }

    /** Trim + lowercase (multibyte safe) for comparison. */
    public static function normalize( $alt ) {
        return mb_strtolower( trim( (string) $alt ) );
    }

    /**
     * Does this non-empty alt look like an auto/placeholder value rather than a
     * real description (the file name, or a camera/scanner/editor default)?
     */
    public static function is_placeholder( $alt, $filename ) {
        $norm = self::normalize( $alt );
        if ( '' === $norm ) {
            return false;
        }

        $file       = self::normalize( $filename );
        $file_noext  = preg_replace( '/\.[a-z0-9]{1,5}$/', '', $file );
        if ( $norm === $file || $norm === $file_noext ) {
            return true;
        }

        $patterns = array(
            '/^(img|dsc|dscn|image|photo|foto|screenshot|screen shot|scan|untitled|capture)[\s_\-]*\d*$/',
            '/^\d+$/',
        );
        foreach ( $patterns as $re ) {
            if ( preg_match( $re, $norm ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Classify one image's alt against the non-duplicate rules.
     *
     * @param string|null $alt      Raw alt; null when the meta is absent (missing).
     * @param string      $filename Attachment file base name (for placeholder check).
     * @return string[] Flag constants (never 'duplicate'; that is set-level).
     */
    public static function classify( $alt, $filename ) {
        if ( null === $alt ) {
            return array( self::FLAG_MISSING );
        }
        if ( '' === trim( $alt ) ) {
            return array( self::FLAG_EMPTY );
        }

        $flags = array();
        if ( mb_strlen( $alt ) > self::MAX_ALT_LEN ) {
            $flags[] = self::FLAG_TOO_LONG;
        }
        if ( self::is_placeholder( $alt, $filename ) ) {
            $flags[] = self::FLAG_PLACEHOLDER;
        }
        return $flags;
    }

    /**
     * From a flat list of alt strings, return the set of normalized signatures
     * that occur 2+ times (empty alts ignored).
     *
     * @param string[] $alts
     * @return array<string,bool> signature => true
     */
    public static function find_duplicates( array $alts ) {
        $counts = array();
        foreach ( $alts as $alt ) {
            $sig = self::normalize( $alt );
            if ( '' === $sig ) {
                continue;
            }
            $counts[ $sig ] = isset( $counts[ $sig ] ) ? $counts[ $sig ] + 1 : 1;
        }
        $dupes = array();
        foreach ( $counts as $sig => $n ) {
            if ( $n >= 2 ) {
                $dupes[ $sig ] = true;
            }
        }
        return $dupes;
    }

    /**
     * Summarize one page of records: per-check counts, healthy count, and the
     * list of problem items (non-dismissed images that have at least one flag).
     *
     * @param array<int,array{id:int,alt:?string,filename:string,dismissed:bool}> $records
     * @param array<string,bool>                                                  $dupes
     * @return array{counts:array<string,int>,healthy:int,items:array<int,array{id:int,alt:string,flags:string[]}>}
     */
    public static function summarize( array $records, array $dupes ) {
        $counts  = array_fill_keys( self::flags(), 0 );
        $healthy = 0;
        $items   = array();

        foreach ( $records as $r ) {
            $alt   = $r['alt'];
            $flags = self::classify( $alt, $r['filename'] );

            if ( null !== $alt && '' !== trim( $alt ) && isset( $dupes[ self::normalize( $alt ) ] ) ) {
                $flags[] = self::FLAG_DUPLICATE;
            }

            if ( ! empty( $r['dismissed'] ) || empty( $flags ) ) {
                $healthy++;
                continue;
            }

            foreach ( $flags as $f ) {
                $counts[ $f ]++;
            }
            $items[] = array(
                'id'    => (int) $r['id'],
                'alt'   => null === $alt ? '' : $alt,
                'flags' => $flags,
            );
        }

        return array( 'counts' => $counts, 'healthy' => $healthy, 'items' => $items );
    }

    /** Health score 0-100 = percentage of healthy images over total. */
    public static function score( $healthy, $total ) {
        if ( $total <= 0 ) {
            return 100;
        }
        return (int) round( ( $healthy / $total ) * 100 );
    }
}
```

- [ ] **Step 4: Wire the class into the loader and test bootstrap**

In `includes/class-sag-plugin.php`, inside `load_dependencies()`, add after the `class-sag-generator.php` line:

```php
        require_once INSAG_PLUGIN_DIR . 'includes/class-sag-audit.php';
```

In `tests/bootstrap.php`, add `'class-sag-audit.php',` to the `$includes` array, immediately before `'class-sag-rest-api.php',`.

- [ ] **Step 5: Run the test to verify it passes**

Run: `php vendor/phpunit/phpunit/phpunit --no-coverage --filter AuditTest`
Expected: PASS (all AuditTest cases green).

- [ ] **Step 6: Run the full suite (nothing else broke)**

Run: `php vendor/phpunit/phpunit/phpunit --no-coverage`
Expected: OK — 45 tests (37 existing + 8 new).

- [ ] **Step 7: Commit**

```bash
git add includes/class-sag-audit.php includes/class-sag-plugin.php tests/bootstrap.php tests/AuditTest.php
git commit -m "feat(audit): add INSAG_Audit pure classification class"
```

---

## Task 2: REST `/audit/scan` endpoint

**Files:**
- Modify: `includes/class-sag-rest-api.php` (register the route in `register_routes()`; add `handle_audit_scan()`)
- Test: covered by `AuditTest` (pure logic) + manual E2E in Task 5. No new unit test here — the handler is thin data-access glue over the already-tested `INSAG_Audit::summarize()`.

**Interfaces:**
- Consumes: `INSAG_Audit::flags()`, `find_duplicates()`, `summarize()`, `score()` (Task 1).
- Produces (consumed by Task 5): `GET /wp-json/insag/v1/audit/scan?page=N` returns
  `{ page:int, total_pages:int, total:int, counts:{missing,empty,duplicate,too_long,placeholder}, score:int, items:[{id,alt,flags,thumb}] }`.

- [ ] **Step 1: Register the route**

In `includes/class-sag-rest-api.php`, inside `register_routes()`, after the existing `/test` route registration, add:

```php
        register_rest_route(
            self::REST_NAMESPACE,
            '/audit/scan',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'handle_audit_scan' ),
                'permission_callback' => array( $this, 'check_permission' ),
                'args'                => array(
                    'page' => array(
                        'type'              => 'integer',
                        'required'          => false,
                        'sanitize_callback' => 'absint',
                    ),
                ),
            )
        );
```

- [ ] **Step 2: Implement the handler**

In `includes/class-sag-rest-api.php`, add this method to the class (after `handle_generate()`):

```php
    /**
     * Scan one page of image attachments (100 per page). On the first page it
     * (re)builds the global duplicate signature set and resets the running
     * summary; both live in short-lived transients so duplicate detection has a
     * whole-library view and the score survives across paged requests.
     *
     * @param WP_REST_Request $request
     * @return array|WP_Error
     */
    public function handle_audit_scan( $request ) {
        global $wpdb;
        $per_page = 100;
        $page     = max( 1, (int) $request->get_param( 'page' ) );

        if ( 1 === $page ) {
            // One query for every image's alt value -> duplicate signature set.
            $alts = $wpdb->get_col(
                "SELECT pm.meta_value
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm
                   ON pm.post_id = p.ID AND pm.meta_key = '_wp_attachment_image_alt'
                 WHERE p.post_type = 'attachment' AND p.post_mime_type LIKE 'image/%'"
            );
            set_transient( 'insag_audit_dupes', INSAG_Audit::find_duplicates( (array) $alts ), 15 * MINUTE_IN_SECONDS );
            set_transient( 'insag_audit_summary', array(
                'counts'  => array_fill_keys( INSAG_Audit::flags(), 0 ),
                'healthy' => 0,
                'total'   => 0,
            ), 15 * MINUTE_IN_SECONDS );
        }

        $dupes   = get_transient( 'insag_audit_dupes' );
        $running = get_transient( 'insag_audit_summary' );
        if ( ! is_array( $dupes ) ) {
            $dupes = array();
        }
        if ( ! is_array( $running ) ) {
            $running = array( 'counts' => array_fill_keys( INSAG_Audit::flags(), 0 ), 'healthy' => 0, 'total' => 0 );
        }

        $query = new WP_Query( array(
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'inherit',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ) );

        $records = array();
        foreach ( $query->posts as $id ) {
            $id       = (int) $id;
            $has_meta = metadata_exists( 'post', $id, '_wp_attachment_image_alt' );
            $file     = get_attached_file( $id );
            $records[] = array(
                'id'        => $id,
                'alt'       => $has_meta ? (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) : null,
                'filename'  => $file ? wp_basename( $file ) : '',
                'dismissed' => (bool) get_post_meta( $id, '_insag_audit_dismissed', true ),
            );
        }

        $page_result = INSAG_Audit::summarize( $records, $dupes );

        foreach ( $page_result['counts'] as $flag => $n ) {
            $running['counts'][ $flag ] = ( $running['counts'][ $flag ] ?? 0 ) + $n;
        }
        $running['healthy'] += $page_result['healthy'];
        $running['total']   += count( $records );
        set_transient( 'insag_audit_summary', $running, 15 * MINUTE_IN_SECONDS );

        $items = array();
        foreach ( $page_result['items'] as $item ) {
            $item['thumb'] = wp_get_attachment_image_url( $item['id'], 'thumbnail' );
            $items[]       = $item;
        }

        return rest_ensure_response( array(
            'page'        => $page,
            'total_pages' => (int) $query->max_num_pages,
            'total'       => (int) $running['total'],
            'counts'      => $running['counts'],
            'score'       => INSAG_Audit::score( $running['healthy'], $running['total'] ),
            'items'       => $items,
        ) );
    }
```

- [ ] **Step 3: Verify no fatal + suite still green**

Run: `php -l includes/class-sag-rest-api.php` (Expected: `No syntax errors detected`)
Run: `php vendor/phpunit/phpunit/phpunit --no-coverage` (Expected: OK, 45 tests — unchanged).

- [ ] **Step 4: Commit**

```bash
git add includes/class-sag-rest-api.php
git commit -m "feat(audit): add /audit/scan REST endpoint"
```

---

## Task 3: REST `/audit/dismiss` and `/audit/set-alt` endpoints

**Files:**
- Modify: `includes/class-sag-rest-api.php` (two routes + two handlers)
- Test: `tests/AuditEndpointTest.php`

**Interfaces:**
- Produces (consumed by Task 5):
  - `POST /audit/dismiss` body `{ image_id:int, dismissed:bool }` -> `{ image_id, dismissed }` (403 `insag_forbidden` without `edit_post`).
  - `POST /audit/set-alt` body `{ image_id:int, alt:string }` -> `{ image_id, alt }` (403 `insag_forbidden` without `edit_post`).

- [ ] **Step 1: Write the failing test**

Create `tests/AuditEndpointTest.php`:

```php
<?php
namespace SAG\Tests;

use Brain\Monkey\Functions;

final class AuditEndpointTest extends TestCase {

    protected function set_up() {
        parent::set_up();
        Functions\when( '__' )->returnArg( 1 );
        Functions\when( 'rest_ensure_response' )->returnArg( 1 );
        Functions\when( 'delete_transient' )->justReturn( true );
    }

    public function test_dismiss_denies_without_edit_post() {
        Functions\when( 'current_user_can' )->justReturn( false );
        $request = new \WP_REST_Request();
        $request->set_param( 'image_id', 5 );
        $request->set_param( 'dismissed', true );
        $result = ( new \INSAG_REST_API() )->handle_audit_dismiss( $request );
        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'insag_forbidden', $result->get_error_code() );
    }

    public function test_dismiss_sets_meta_when_allowed() {
        Functions\when( 'current_user_can' )->justReturn( true );
        $saved = array();
        Functions\when( 'update_post_meta' )->alias( function ( $id, $key, $val ) use ( &$saved ) {
            $saved[ $key ] = array( $id, $val );
        } );
        Functions\when( 'delete_post_meta' )->justReturn( true );

        $request = new \WP_REST_Request();
        $request->set_param( 'image_id', 5 );
        $request->set_param( 'dismissed', true );
        $result = ( new \INSAG_REST_API() )->handle_audit_dismiss( $request );

        $this->assertTrue( $result['dismissed'] );
        $this->assertSame( array( 5, 1 ), $saved['_insag_audit_dismissed'] );
    }

    public function test_set_alt_denies_without_edit_post() {
        Functions\when( 'current_user_can' )->justReturn( false );
        $request = new \WP_REST_Request();
        $request->set_param( 'image_id', 7 );
        $request->set_param( 'alt', 'hello' );
        $result = ( new \INSAG_REST_API() )->handle_audit_set_alt( $request );
        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'insag_forbidden', $result->get_error_code() );
    }

    public function test_set_alt_saves_sanitized_value() {
        Functions\when( 'current_user_can' )->justReturn( true );
        Functions\when( 'sanitize_text_field' )->returnArg( 1 );
        $saved = array();
        Functions\when( 'update_post_meta' )->alias( function ( $id, $key, $val ) use ( &$saved ) {
            $saved[ $key ] = array( $id, $val );
        } );

        $request = new \WP_REST_Request();
        $request->set_param( 'image_id', 7 );
        $request->set_param( 'alt', 'A cat on a sofa' );
        $result = ( new \INSAG_REST_API() )->handle_audit_set_alt( $request );

        $this->assertSame( 'A cat on a sofa', $result['alt'] );
        $this->assertSame( array( 7, 'A cat on a sofa' ), $saved['_wp_attachment_image_alt'] );
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php vendor/phpunit/phpunit/phpunit --no-coverage --filter AuditEndpointTest`
Expected: FAIL — `Call to undefined method INSAG_REST_API::handle_audit_dismiss()`.

- [ ] **Step 3: Register the two routes**

In `includes/class-sag-rest-api.php`, inside `register_routes()`, after the `/audit/scan` route, add:

```php
        register_rest_route(
            self::REST_NAMESPACE,
            '/audit/dismiss',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'handle_audit_dismiss' ),
                'permission_callback' => array( $this, 'check_permission' ),
                'args'                => array(
                    'image_id'  => array( 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ),
                    'dismissed' => array( 'type' => 'boolean', 'required' => true ),
                ),
            )
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/audit/set-alt',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'handle_audit_set_alt' ),
                'permission_callback' => array( $this, 'check_permission' ),
                'args'                => array(
                    'image_id' => array( 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ),
                    'alt'      => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
                ),
            )
        );
```

- [ ] **Step 4: Implement the two handlers**

In `includes/class-sag-rest-api.php`, add after `handle_audit_scan()`:

```php
    /**
     * Toggle the "dismissed" (reviewed / ignore) flag on an attachment.
     *
     * @param WP_REST_Request $request
     * @return array|WP_Error
     */
    public function handle_audit_dismiss( $request ) {
        $id = (int) $request->get_param( 'image_id' );
        if ( ! current_user_can( 'edit_post', $id ) ) {
            return new WP_Error(
                'insag_forbidden',
                __( 'You are not allowed to edit this attachment.', 'internick-smart-alt-generator' ),
                array( 'status' => 403 )
            );
        }
        $dismissed = (bool) $request->get_param( 'dismissed' );
        if ( $dismissed ) {
            update_post_meta( $id, '_insag_audit_dismissed', 1 );
        } else {
            delete_post_meta( $id, '_insag_audit_dismissed' );
        }
        delete_transient( 'insag_audit_summary' );
        return rest_ensure_response( array( 'image_id' => $id, 'dismissed' => $dismissed ) );
    }

    /**
     * Save manually edited alt text for an attachment.
     *
     * @param WP_REST_Request $request
     * @return array|WP_Error
     */
    public function handle_audit_set_alt( $request ) {
        $id = (int) $request->get_param( 'image_id' );
        if ( ! current_user_can( 'edit_post', $id ) ) {
            return new WP_Error(
                'insag_forbidden',
                __( 'You are not allowed to edit this attachment.', 'internick-smart-alt-generator' ),
                array( 'status' => 403 )
            );
        }
        $alt = sanitize_text_field( (string) $request->get_param( 'alt' ) );
        update_post_meta( $id, '_wp_attachment_image_alt', $alt );
        delete_transient( 'insag_audit_summary' );
        return rest_ensure_response( array( 'image_id' => $id, 'alt' => $alt ) );
    }
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php vendor/phpunit/phpunit/phpunit --no-coverage --filter AuditEndpointTest` (Expected: PASS)
Run: `php vendor/phpunit/phpunit/phpunit --no-coverage` (Expected: OK, 49 tests)

- [ ] **Step 6: Commit**

```bash
git add includes/class-sag-rest-api.php tests/AuditEndpointTest.php
git commit -m "feat(audit): add /audit/dismiss and /audit/set-alt endpoints"
```

---

## Task 4: Admin page + enqueue + webpack entry

**Files:**
- Modify: `admin/class-sag-admin.php` (add `add_media_page`, `render_audit_page()`, enqueue block)
- Create: `admin/views/audit-page.php`
- Modify: `webpack.config.js` (add `admin-audit` entry)
- Test: verified via the browser E2E in Task 5 (no unit test — this is WordPress glue). A dedicated E2E step is included in Task 5.

**Interfaces:**
- Produces: an admin page at **Media -> Alt Text Audit** whose hook is `media_page_insag-audit`, mounting `<div id="insag-audit-root">`, with `window.insagAuditData = { nonce, restBase }` localized for `src/admin-audit/index.js` (Task 5).

- [ ] **Step 1: Register the media page**

In `admin/class-sag-admin.php`, inside `register_menus()`, after the existing `add_media_page( ... 'insag-bulk' ... )` call, add:

```php
        add_media_page(
            __( 'Alt Text Audit', 'internick-smart-alt-generator' ),
            __( 'Alt Text Audit', 'internick-smart-alt-generator' ),
            'upload_files',
            'insag-audit',
            array( $this, 'render_audit_page' )
        );
```

- [ ] **Step 2: Add the render callback**

In `admin/class-sag-admin.php`, after `render_bulk_page()`, add:

```php
    public function render_audit_page() {
        require INSAG_PLUGIN_DIR . 'admin/views/audit-page.php';
    }
```

- [ ] **Step 3: Add the enqueue block**

In `admin/class-sag-admin.php`, inside `enqueue_assets( $hook )`, after the `media_page_insag-bulk` block (and before the classic media-library block), add:

```php
        // Audit page — React bundle.
        if ( 'media_page_insag-audit' === $hook ) {
            $asset_file = INSAG_PLUGIN_DIR . 'build/admin-audit.asset.php';
            if ( ! file_exists( $asset_file ) ) {
                return;
            }
            $asset = require $asset_file;
            wp_enqueue_script(
                'insag-admin-audit',
                INSAG_PLUGIN_URL . 'build/admin-audit.js',
                $asset['dependencies'],
                $asset['version'],
                true
            );
            wp_localize_script( 'insag-admin-audit', 'insagAuditData', array(
                'nonce'    => wp_create_nonce( 'wp_rest' ),
                'restBase' => rest_url( 'insag/v1' ),
            ) );
            return;
        }
```

- [ ] **Step 4: Create the view**

Create `admin/views/audit-page.php`:

```php
<?php
/**
 * Alt Text Audit page — React mount point.
 *
 * @package Smart_Alt_Generator
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}
?>
<div class="wrap">
    <div id="insag-audit-root"></div>
</div>
```

- [ ] **Step 5: Add the webpack entry**

In `webpack.config.js`, add the audit entry so the block reads:

```js
	entry: {
		index: './src/block-editor/index.js',
		'admin-settings': './src/admin-settings/index.js',
		'admin-bulk': './src/admin-bulk/index.js',
		'admin-audit': './src/admin-audit/index.js',
	},
```

- [ ] **Step 6: Verify PHP is valid**

Run: `php -l admin/class-sag-admin.php` (Expected: `No syntax errors detected`)
(The bundle does not exist yet; the page will render an empty root until Task 5 builds it. That is expected here.)

- [ ] **Step 7: Commit**

```bash
git add admin/class-sag-admin.php admin/views/audit-page.php webpack.config.js
git commit -m "feat(audit): register the Alt Text Audit admin page and bundle"
```

---

## Task 5: React app (`src/admin-audit/index.js`) + E2E

**Files:**
- Create: `src/admin-audit/index.js`
- Test: manual browser E2E (steps below)

**Interfaces:**
- Consumes: `window.insagAuditData` (Task 4); `GET /audit/scan?page=N`, `POST /audit/set-alt`, `POST /audit/dismiss` (Tasks 2-3); `POST /generate` (existing).

- [ ] **Step 1: Write the React app**

Create `src/admin-audit/index.js`:

```jsx
import { createRoot, useState, useCallback, useEffect } from '@wordpress/element';
import { Button, TextControl, Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const { restBase = '', nonce = '' } = window.insagAuditData ?? {};
if ( nonce ) {
	apiFetch.use( apiFetch.createNonceMiddleware( nonce ) );
}

const FLAG_LABELS = {
	missing: __( 'Missing', 'internick-smart-alt-generator' ),
	empty: __( 'Empty', 'internick-smart-alt-generator' ),
	duplicate: __( 'Duplicate', 'internick-smart-alt-generator' ),
	too_long: __( 'Too long', 'internick-smart-alt-generator' ),
	placeholder: __( 'Placeholder', 'internick-smart-alt-generator' ),
};
const FLAG_ORDER = [ 'missing', 'empty', 'duplicate', 'too_long', 'placeholder' ];

/** Big number + progress bar summarizing library alt-text health. */
function ScoreCard( { score, total } ) {
	const color = score >= 80 ? '#00a32a' : score >= 50 ? '#dba617' : '#d63638';
	return (
		<div style={ { background: '#fff', border: '1px solid #c3c4c7', borderRadius: '4px', padding: '16px 20px', marginBottom: '16px' } }>
			<div style={ { display: 'flex', alignItems: 'baseline', gap: '8px' } }>
				<span style={ { fontSize: '40px', fontWeight: 700, color, lineHeight: 1 } }>{ score }</span>
				<span style={ { fontSize: '16px', color: '#646970' } }>/ 100</span>
				<span style={ { marginLeft: 'auto', fontSize: '12px', color: '#646970' } }>
					{ total } { __( 'images', 'internick-smart-alt-generator' ) }
				</span>
			</div>
			<div style={ { background: '#e0e0e0', borderRadius: '4px', height: '8px', overflow: 'hidden', marginTop: '10px' } }>
				<div style={ { background: color, height: '100%', width: `${ score }%`, transition: 'width .3s' } } />
			</div>
		</div>
	);
}

/** Clickable per-check counters that filter the table. */
function Chips( { counts, active, onPick } ) {
	return (
		<div style={ { display: 'flex', gap: '8px', flexWrap: 'wrap', marginBottom: '16px' } }>
			{ FLAG_ORDER.map( ( flag ) => {
				const isActive = active === flag;
				return (
					<button
						key={ flag }
						onClick={ () => onPick( isActive ? null : flag ) }
						style={ {
							cursor: 'pointer',
							border: `1px solid ${ isActive ? '#2271b1' : '#c3c4c7' }`,
							background: isActive ? '#2271b1' : '#fff',
							color: isActive ? '#fff' : '#1d2327',
							borderRadius: '4px',
							padding: '6px 12px',
							fontSize: '13px',
						} }
					>
						{ FLAG_LABELS[ flag ] } <strong>{ counts[ flag ] ?? 0 }</strong>
					</button>
				);
			} ) }
		</div>
	);
}

/** One problem row: thumbnail, badges, current alt, and the three actions. */
function Row( { item, onDone } ) {
	const [ alt, setAlt ] = useState( item.alt );
	const [ busy, setBusy ] = useState( false );
	const [ note, setNote ] = useState( '' );

	const generate = useCallback( async () => {
		setBusy( true );
		setNote( '' );
		try {
			const res = await apiFetch( { path: '/insag/v1/generate', method: 'POST', data: { image_id: item.id } } );
			setAlt( res.alt_text );
			setNote( __( 'Generated ✓', 'internick-smart-alt-generator' ) );
			onDone( item.id );
		} catch ( e ) {
			setNote( e?.message || __( 'Failed', 'internick-smart-alt-generator' ) );
		}
		setBusy( false );
	}, [ item.id, onDone ] );

	const save = useCallback( async () => {
		setBusy( true );
		setNote( '' );
		try {
			await apiFetch( { path: '/insag/v1/audit/set-alt', method: 'POST', data: { image_id: item.id, alt } } );
			setNote( __( 'Saved ✓', 'internick-smart-alt-generator' ) );
			onDone( item.id );
		} catch ( e ) {
			setNote( e?.message || __( 'Failed', 'internick-smart-alt-generator' ) );
		}
		setBusy( false );
	}, [ item.id, alt, onDone ] );

	const dismiss = useCallback( async () => {
		setBusy( true );
		try {
			await apiFetch( { path: '/insag/v1/audit/dismiss', method: 'POST', data: { image_id: item.id, dismissed: true } } );
			onDone( item.id );
		} catch ( e ) {
			setNote( e?.message || __( 'Failed', 'internick-smart-alt-generator' ) );
			setBusy( false );
		}
	}, [ item.id, onDone ] );

	return (
		<div style={ { display: 'flex', gap: '12px', padding: '12px', borderBottom: '1px solid #f0f0f1', alignItems: 'flex-start' } }>
			<img
				src={ item.thumb }
				alt=""
				width={ 60 }
				height={ 60 }
				style={ { objectFit: 'cover', borderRadius: '4px', flexShrink: 0, background: '#f0f0f1' } }
			/>
			<div style={ { flex: 1 } }>
				<div style={ { display: 'flex', gap: '4px', marginBottom: '6px', flexWrap: 'wrap' } }>
					{ item.flags.map( ( f ) => (
						<span key={ f } style={ { fontSize: '11px', background: '#fcf0f1', color: '#d63638', border: '1px solid #f0c3c5', borderRadius: '3px', padding: '1px 6px' } }>
							{ FLAG_LABELS[ f ] }
						</span>
					) ) }
					<span style={ { marginLeft: 'auto', fontSize: '10px', color: '#c3c4c7' } }>#{ item.id }</span>
				</div>
				<TextControl
					value={ alt }
					onChange={ setAlt }
					placeholder={ __( 'Alt text…', 'internick-smart-alt-generator' ) }
					__nextHasNoMarginBottom
				/>
				<div style={ { display: 'flex', gap: '8px', alignItems: 'center', marginTop: '6px' } }>
					<Button variant="primary" onClick={ generate } disabled={ busy } isSmall>
						{ __( 'Generate with AI', 'internick-smart-alt-generator' ) }
					</Button>
					<Button variant="secondary" onClick={ save } disabled={ busy } isSmall>
						{ __( 'Save', 'internick-smart-alt-generator' ) }
					</Button>
					<Button variant="tertiary" onClick={ dismiss } disabled={ busy } isSmall>
						{ __( 'Ignore', 'internick-smart-alt-generator' ) }
					</Button>
					{ busy && <Spinner /> }
					{ note && <span style={ { fontSize: '12px', color: '#646970' } }>{ note }</span> }
				</div>
			</div>
		</div>
	);
}

/** Root: runs the paginated scan on mount, renders score, chips, and the table. */
function AuditApp() {
	const [ status, setStatus ] = useState( 'idle' ); // idle | scanning | done
	const [ progress, setProgress ] = useState( { page: 0, totalPages: 0 } );
	const [ score, setScore ] = useState( 100 );
	const [ total, setTotal ] = useState( 0 );
	const [ counts, setCounts ] = useState( {} );
	const [ items, setItems ] = useState( [] );
	const [ filter, setFilter ] = useState( null );
	const [ resolved, setResolved ] = useState( () => new Set() );
	const [ bulkBusy, setBulkBusy ] = useState( false );
	const [ bulkProgress, setBulkProgress ] = useState( { done: 0, total: 0 } );

	const runScan = useCallback( async () => {
		setStatus( 'scanning' );
		setItems( [] );
		setResolved( new Set() );
		let page = 1;
		let totalPages = 1;
		const collected = [];
		do {
			const res = await apiFetch( { path: `/insag/v1/audit/scan?page=${ page }` } );
			totalPages = res.total_pages || 1;
			setProgress( { page, totalPages } );
			setScore( res.score );
			setTotal( res.total );
			setCounts( res.counts );
			collected.push( ...res.items );
			setItems( [ ...collected ] );
			page++;
		} while ( page <= totalPages );
		setStatus( 'done' );
	}, [] );

	useEffect( () => {
		runScan();
	}, [ runScan ] );

	const markResolved = useCallback( ( id ) => {
		setResolved( ( prev ) => new Set( prev ).add( id ) );
	}, [] );

	const visible = items.filter(
		( it ) => ! resolved.has( it.id ) && ( ! filter || it.flags.includes( filter ) )
	);

	// Generate AI alt text for every currently-shown (filtered) image, one at a
	// time with progress. Reuses the existing /generate endpoint; each call is a
	// paid OpenAI request, so we confirm the count first.
	const generateAllShown = useCallback( async () => {
		const targets = visible.slice();
		if ( targets.length === 0 ) {
			return;
		}
		// eslint-disable-next-line no-alert
		if ( ! window.confirm(
			sprintf(
				__( 'Generate AI alt text for %d image(s)? Each one is a paid OpenAI request.', 'internick-smart-alt-generator' ),
				targets.length
			)
		) ) {
			return;
		}
		setBulkBusy( true );
		setBulkProgress( { done: 0, total: targets.length } );
		for ( let i = 0; i < targets.length; i++ ) {
			try {
				await apiFetch( { path: '/insag/v1/generate', method: 'POST', data: { image_id: targets[ i ].id } } );
				markResolved( targets[ i ].id );
			} catch ( e ) {
				// Leave the image in the list on failure so it stays visible.
			}
			setBulkProgress( { done: i + 1, total: targets.length } );
		}
		setBulkBusy( false );
	}, [ visible, markResolved ] );

	return (
		<div style={ { maxWidth: '820px', paddingTop: '16px' } }>
			<div style={ { display: 'flex', alignItems: 'center', gap: '12px' } }>
				<h1 style={ { margin: 0 } }>{ __( 'Alt Text Audit', 'internick-smart-alt-generator' ) }</h1>
				<div style={ { marginLeft: 'auto', display: 'flex', gap: '8px' } }>
					<Button
						variant="primary"
						onClick={ generateAllShown }
						disabled={ bulkBusy || status === 'scanning' || visible.length === 0 }
					>
						{ bulkBusy
							? sprintf( __( 'Generating %1$d/%2$d…', 'internick-smart-alt-generator' ), bulkProgress.done, bulkProgress.total )
							: sprintf( __( 'Generate all shown (%d)', 'internick-smart-alt-generator' ), visible.length ) }
					</Button>
					<Button variant="secondary" onClick={ runScan } disabled={ status === 'scanning' || bulkBusy }>
						{ __( 'Re-scan', 'internick-smart-alt-generator' ) }
					</Button>
				</div>
			</div>

			{ status === 'scanning' && (
				<p style={ { color: '#646970' } }>
					<Spinner /> { __( 'Scanning…', 'internick-smart-alt-generator' ) } { progress.page }/{ progress.totalPages }
				</p>
			) }

			<ScoreCard score={ score } total={ total } />
			<Chips counts={ counts } active={ filter } onPick={ setFilter } />

			<div style={ { background: '#fff', border: '1px solid #c3c4c7', borderRadius: '4px', overflow: 'hidden' } }>
				{ visible.length === 0 ? (
					<p style={ { padding: '20px', textAlign: 'center', color: '#646970' } }>
						{ status === 'done'
							? __( 'No problems here. Nice work!', 'internick-smart-alt-generator' )
							: __( 'Loading…', 'internick-smart-alt-generator' ) }
					</p>
				) : (
					visible.map( ( it ) => <Row key={ it.id } item={ it } onDone={ markResolved } /> )
				) }
			</div>
		</div>
	);
}

const root = document.getElementById( 'insag-audit-root' );
if ( root ) {
	createRoot( root ).render( <AuditApp /> );
}
```

- [ ] **Step 2: Build the bundles**

Run: `npm install` (if `node_modules` is absent) then `npm run build`
Expected: `build/admin-audit.js` and `build/admin-audit.asset.php` are produced with no errors.

- [ ] **Step 3: E2E verify in the WP 7.0 Docker env**

The env from the 1.1.3 hotfix is reusable (`f:/tmp/wp-aatg-docker`, http://localhost:8910/wp-admin, admin/admin123, plugin bind-mounted). Ensure at least a few images exist with varied alt (one missing, two identical, one 130-char, one named-like-file). Then:

```bash
B="/c/Users/nick/.claude/skills/gstack/browse/dist/browse"
$B goto "http://localhost:8910/wp-admin/upload.php?page=insag-audit"
$B wait --load
$B js "!!document.querySelector('script[src*=\"admin-audit.js\"]')"   # expect true
$B js "document.getElementById('insag-audit-root').innerHTML.length"  # expect > 0
$B screenshot /tmp/audit.png
```

Manually confirm: the score card, the five chips with counts, and the problem rows render; clicking a chip filters; **Generate**, **Save**, and **Ignore** on a row each remove it from the list and (after Re-scan) update the score. Also confirm the header **"Generate all shown (N)"** button: filter by a chip, click it, accept the confirm, and watch it process the filtered set with a `Generating x/N…` counter, emptying the list as each one completes.

- [ ] **Step 4: Commit**

```bash
git add src/admin-audit/index.js build/admin-audit.js build/admin-audit.asset.php
git commit -m "feat(audit): add the Alt Text Audit React dashboard"
```

---

## Task 6: Cleanup, version bump, readme, package

**Files:**
- Modify: `admin/views/bulk-page.php`, `admin/views/settings-page.php` (`ininsag-*-root` -> `insag-*-root`)
- Modify: `src/admin-bulk/index.js`, `src/admin-settings/index.js` (`getElementById('ininsag-*-root')` -> `insag-*-root`)
- Modify: `internick-smart-alt-generator.php` (Version + `INSAG_VERSION` -> 1.2.0)
- Modify: `readme.txt` (Stable tag, feature copy, tags, changelog, upgrade notice)

**Interfaces:** none (final packaging).

- [ ] **Step 1: Normalize the leftover `ininsag` ids**

Change `ininsag-bulk-root` -> `insag-bulk-root` in both `admin/views/bulk-page.php` and `src/admin-bulk/index.js`.
Change `ininsag-settings-root` -> `insag-settings-root` in both `admin/views/settings-page.php` and `src/admin-settings/index.js`.
(The enqueue hook guards were already corrected to `insag-*` in 1.1.3; the div id and its `getElementById` only need to agree with each other, so change them together.)

- [ ] **Step 2: Rebuild and confirm no stray `ininsag` remains**

Run: `npm run build`
Run: `grep -rn "ininsag" src/ admin/ build/ || echo "clean"`
Expected: `clean`.

- [ ] **Step 3: Bump the version**

In `internick-smart-alt-generator.php`: `Version: 1.1.3` -> `Version: 1.2.0` and `define( 'INSAG_VERSION', '1.1.3' )` -> `'1.2.0'`.

- [ ] **Step 4: Update readme.txt**

Set `Stable tag: 1.2.0`. Add `alt text audit` and `wcag` to `Tags` (keep 5 total, drop the weakest if needed). Add to the Description feature list a line: `* Alt Text Audit — scan your whole library for missing, empty, duplicate, overly long, or placeholder alt text and fix each one`. Add a changelog block:

```
= 1.2.0 =
* New: Alt Text Audit dashboard (Media -> Alt Text Audit) that scores your
  library's alt text and flags missing, empty, duplicate, too-long, and
  placeholder values, with per-image generate / edit / ignore actions.
```

And an Upgrade Notice:

```
= 1.2.0 =
Adds the Alt Text Audit dashboard to review and fix your whole library's alt text.
```

- [ ] **Step 5: Full test suite + build sanity**

Run: `php vendor/phpunit/phpunit/phpunit --no-coverage` (Expected: OK, 49 tests)
Run: `grep -c "1.2.0" internick-smart-alt-generator.php readme.txt` (Expected: version present in both)

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "chore(release): Alt Text Audit, normalize ids, bump to 1.2.0"
```

- [ ] **Step 7: Package the distribution ZIP (forward-slash, no BOM)**

Stage runtime-only files (`internick-smart-alt-generator.php`, `uninstall.php`, `readme.txt`, `includes/`, `admin/`, `build/`, `languages/`) into a folder named `internick-smart-alt-generator/`, then zip with Python (NOT PowerShell `Compress-Archive`/.NET, which write backslash paths on Windows PS 5.1):

```python
import zipfile, os
stage = r"f:\tmp\wp-aatg-dist\internick-smart-alt-generator"
out   = r"f:\tmp\wp-aatg-dist\internick-smart-alt-generator.zip"
base  = os.path.dirname(stage)
with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as z:
    for root, dirs, files in os.walk(stage):
        for f in sorted(files):
            full = os.path.join(root, f)
            z.write(full, os.path.relpath(full, base).replace(os.sep, '/'))
```

Verify entries use `/` and contain no BOM, then the WordPress.org SVN upload is Nick's manual step (TortoiseSVN, commit from the working-copy **root** so `trunk/` + `tags/1.2.0/` go together).

---

## Success criteria

- Scanning a library of several hundred images completes without a timeout and reports correct counts for all five checks, including duplicates across page boundaries.
- Generate, Save, and Ignore each persist and are reflected in the score after a re-scan.
- `AuditTest` and `AuditEndpointTest` pass alongside the existing suite (49 tests total).
- No `ininsag` remains anywhere; Plugin Check reports 0 errors; the 1.2.0 ZIP uses forward-slash paths and has no BOM.
