# Phase 2 — React Admin UI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the PHP/vanilla-JS admin UI with React (`@wordpress/components`): Settings page (Modern Cards) + Bulk page (stats + progress + log), plus a new Test Connection REST endpoint.

**Architecture:** Three new REST routes added to `SAG_REST_API` (GET+POST `/settings`, GET `/test`). Two new React entry points (`src/admin-settings/index.js`, `src/admin-bulk/index.js`) compiled via a custom `webpack.config.js`. PHP views become bare mount points; enqueueing moves to `admin/class-sag-admin.php`. `admin/js/sag-bulk.js` is deleted.

**Tech Stack:** PHP 8.1, WordPress 6.4+, PHPUnit 11.5 + Brain Monkey, React 18, `@wordpress/components`, `@wordpress/scripts` v30, `@wordpress/api-fetch`, `@wordpress/element`

**All commands run from:** `E:/dev/02-wordpress/ai-alt-text-generator`

---

## File Map

| File | Action | Responsibility |
|------|--------|---------------|
| `includes/class-sag-rest-api.php` | Modify | Add `get_settings`, `save_settings`, `test_connection`, `check_admin`, `validate_model`, `settings_args` methods; extend `register_routes` |
| `tests/SettingsEndpointTest.php` | Create | PHPUnit tests for GET/POST `/settings` |
| `tests/TestEndpointTest.php` | Create | PHPUnit tests for GET `/test` |
| `webpack.config.js` | Create | Named entry points for 3 React bundles |
| `package.json` | Modify | Remove positional args from build/start scripts |
| `admin/class-sag-admin.php` | Modify | Enqueue new React bundles; pass `sagSettingsData` + `sagBulkData` via `wp_localize_script`; move `WP_Query` from view to here |
| `admin/views/settings-page.php` | Modify | Replace form with `<div id="sag-settings-root"></div>` |
| `admin/views/bulk-page.php` | Modify | Replace template with `<div id="sag-bulk-root"></div>` |
| `admin/js/sag-bulk.js` | Delete | Replaced by React bundle |
| `admin/css/sag-admin.css` | Modify | Remove selectors for deleted vanilla JS; keep `.sag-generate-btn` |
| `src/admin-settings/index.js` | Create | Settings page React app (6 components) |
| `src/admin-bulk/index.js` | Create | Bulk page React app (5 components) |

---

## Task 1: REST endpoints — GET + POST `/settings`

**Files:**
- Create: `tests/SettingsEndpointTest.php`
- Modify: `includes/class-sag-rest-api.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/SettingsEndpointTest.php`:

```php
<?php
namespace SAG\Tests;

use Brain\Monkey\Functions;

final class SettingsEndpointTest extends TestCase {

    protected function set_up() {
        parent::set_up();
        Functions\when( '__' )->returnArg( 1 );
        Functions\when( 'rest_ensure_response' )->returnArg( 1 );
    }

    public function test_get_settings_returns_all_options() {
        Functions\when( 'get_option' )->alias( function ( $key, $default = false ) {
            return [
                'sag_openai_api_key' => 'sk-test',
                'sag_model'          => 'gpt-4o-mini',
                'sag_language'       => 'auto',
                'sag_auto_generate'  => false,
            ][ $key ] ?? $default;
        } );

        $api      = new \SAG_REST_API();
        $response = $api->get_settings( new \WP_REST_Request() );

        $this->assertSame( 'sk-test', $response['sag_openai_api_key'] );
        $this->assertSame( 'gpt-4o-mini', $response['sag_model'] );
        $this->assertSame( 'auto', $response['sag_language'] );
        $this->assertFalse( $response['sag_auto_generate'] );
    }

    public function test_save_settings_persists_provided_fields() {
        $saved = [];
        Functions\when( 'update_option' )->alias( function ( $key, $value ) use ( &$saved ) {
            $saved[ $key ] = $value;
            return true;
        } );

        $api     = new \SAG_REST_API();
        $request = new \WP_REST_Request();
        $request->set_param( 'sag_model', 'gpt-4o' );
        $request->set_param( 'sag_language', 'Spanish' );

        $response = $api->save_settings( $request );

        $this->assertTrue( $response['saved'] );
        $this->assertSame( 'gpt-4o', $saved['sag_model'] );
        $this->assertSame( 'Spanish', $saved['sag_language'] );
    }

    public function test_save_settings_skips_null_params() {
        $saved = [];
        Functions\when( 'update_option' )->alias( function ( $key, $value ) use ( &$saved ) {
            $saved[ $key ] = $value;
            return true;
        } );

        $request = new \WP_REST_Request();
        $request->set_param( 'sag_language', 'French' );
        ( new \SAG_REST_API() )->save_settings( $request );

        $this->assertArrayHasKey( 'sag_language', $saved );
        $this->assertArrayNotHasKey( 'sag_model', $saved );
        $this->assertArrayNotHasKey( 'sag_openai_api_key', $saved );
    }

    public function test_validate_model_accepts_known_models() {
        $api = new \SAG_REST_API();
        $this->assertTrue( $api->validate_model( 'gpt-4o-mini' ) );
        $this->assertTrue( $api->validate_model( 'gpt-4o' ) );
    }

    public function test_validate_model_rejects_unknown_model() {
        $result = ( new \SAG_REST_API() )->validate_model( 'gpt-5-ultra' );
        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'sag_invalid_model', $result->get_error_code() );
    }

    public function test_check_admin_delegates_to_current_user_can() {
        $api = new \SAG_REST_API();
        Functions\when( 'current_user_can' )->justReturn( true );
        $this->assertTrue( $api->check_admin() );
        Functions\when( 'current_user_can' )->justReturn( false );
        $this->assertFalse( $api->check_admin() );
    }
}
```

- [ ] **Step 2: Run to confirm failures**

```bash
./vendor/bin/phpunit tests/SettingsEndpointTest.php --no-coverage
```

Expected: 6 errors — "Call to undefined method SAG_REST_API::get_settings()" etc.

- [ ] **Step 3: Add methods to `includes/class-sag-rest-api.php`**

After the closing brace of `handle_generate()` and before the final `}` of the class, add:

```php
    /** Only admins may read/write settings. */
    public function check_admin() {
        return current_user_can( 'manage_options' );
    }

    /** Validates model value — returns true or WP_Error. */
    public function validate_model( $value ) {
        $allowed = array( 'gpt-4o-mini', 'gpt-4o' );
        if ( in_array( $value, $allowed, true ) ) {
            return true;
        }
        return new WP_Error(
            'sag_invalid_model',
            __( 'Invalid model.', 'smart-alt-generator' ),
            array( 'status' => 400 )
        );
    }

    /** Returns all plugin options as JSON. */
    public function get_settings( $request ) {
        return rest_ensure_response( array(
            'sag_openai_api_key' => get_option( 'sag_openai_api_key', '' ),
            'sag_model'          => get_option( 'sag_model', 'gpt-4o-mini' ),
            'sag_language'       => get_option( 'sag_language', 'auto' ),
            'sag_auto_generate'  => (bool) get_option( 'sag_auto_generate', false ),
        ) );
    }

    /** Saves whichever option keys were provided in the request. */
    public function save_settings( $request ) {
        $fields = array( 'sag_openai_api_key', 'sag_model', 'sag_language', 'sag_auto_generate' );
        foreach ( $fields as $key ) {
            $value = $request->get_param( $key );
            if ( null !== $value ) {
                update_option( $key, $value );
            }
        }
        return rest_ensure_response( array( 'saved' => true ) );
    }
```

Also extend `register_routes()` — replace the existing method with:

```php
    /** Register all REST routes. Hooked to rest_api_init. */
    public function register_routes() {
        register_rest_route(
            self::REST_NAMESPACE,
            '/generate',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'handle_generate' ),
                'permission_callback' => array( $this, 'check_permission' ),
                'args'                => array(
                    'image_id'  => array(
                        'type'              => 'integer',
                        'required'          => false,
                        'sanitize_callback' => 'absint',
                    ),
                    'image_url' => array(
                        'type'              => 'string',
                        'required'          => false,
                        'sanitize_callback' => 'esc_url_raw',
                    ),
                ),
            )
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/settings',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_settings' ),
                    'permission_callback' => array( $this, 'check_admin' ),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'save_settings' ),
                    'permission_callback' => array( $this, 'check_admin' ),
                    'args'                => array(
                        'sag_openai_api_key' => array(
                            'type'              => 'string',
                            'required'          => false,
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'sag_model'          => array(
                            'type'              => 'string',
                            'required'          => false,
                            'sanitize_callback' => 'sanitize_text_field',
                            'validate_callback' => array( $this, 'validate_model' ),
                        ),
                        'sag_language'       => array(
                            'type'              => 'string',
                            'required'          => false,
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'sag_auto_generate'  => array(
                            'type'     => 'boolean',
                            'required' => false,
                        ),
                    ),
                ),
            )
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/test',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'test_connection' ),
                'permission_callback' => array( $this, 'check_permission' ),
            )
        );
    }
```

- [ ] **Step 4: Run tests — confirm 6 pass**

```bash
./vendor/bin/phpunit tests/SettingsEndpointTest.php --no-coverage
```

Expected: `OK (6 tests, ...)`

- [ ] **Step 5: Run full suite — confirm still 26 pass**

```bash
./vendor/bin/phpunit --no-coverage
```

Expected: `OK (26 tests, 47 assertions)`

- [ ] **Step 6: Commit**

```bash
git add includes/class-sag-rest-api.php tests/SettingsEndpointTest.php
git commit -m "feat: add GET/POST /smart-alt/v1/settings REST endpoints (TDD)"
```

---

## Task 2: REST endpoint — GET `/test`

**Files:**
- Create: `tests/TestEndpointTest.php`
- Modify: `includes/class-sag-rest-api.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/TestEndpointTest.php`:

```php
<?php
namespace SAG\Tests;

use Brain\Monkey\Functions;

final class TestEndpointTest extends TestCase {

    protected function set_up() {
        parent::set_up();
        Functions\when( '__' )->returnArg( 1 );
        Functions\when( 'rest_ensure_response' )->returnArg( 1 );
    }

    public function test_test_connection_returns_error_when_no_api_key() {
        Functions\when( 'get_option' )->justReturn( '' );

        $result = ( new \SAG_REST_API() )->test_connection( new \WP_REST_Request() );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'sag_no_api_key', $result->get_error_code() );
    }

    public function test_test_connection_returns_ok_on_success() {
        Functions\when( 'get_option' )->alias( function ( $key, $default = '' ) {
            return $key === 'sag_openai_api_key' ? 'sk-test' : 'gpt-4o-mini';
        } );
        Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
        Functions\when( 'wp_remote_post' )->justReturn( array( 'response' => array( 'code' => 200 ) ) );
        Functions\when( 'is_wp_error' )->justReturn( false );
        Functions\when( 'wp_remote_retrieve_body' )->justReturn(
            '{"choices":[{"message":{"content":"."}}]}'
        );

        $result = ( new \SAG_REST_API() )->test_connection( new \WP_REST_Request() );

        $this->assertTrue( $result['ok'] );
        $this->assertSame( 'gpt-4o-mini', $result['model'] );
    }

    public function test_test_connection_returns_error_on_openai_api_error() {
        Functions\when( 'get_option' )->alias( function ( $key, $default = '' ) {
            return $key === 'sag_openai_api_key' ? 'sk-bad' : 'gpt-4o-mini';
        } );
        Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
        Functions\when( 'wp_remote_post' )->justReturn( array( 'response' => array( 'code' => 401 ) ) );
        Functions\when( 'is_wp_error' )->justReturn( false );
        Functions\when( 'wp_remote_retrieve_body' )->justReturn(
            '{"error":{"message":"Incorrect API key provided."}}'
        );

        $result = ( new \SAG_REST_API() )->test_connection( new \WP_REST_Request() );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'sag_test_failed', $result->get_error_code() );
        $this->assertStringContainsString( 'Incorrect API key', $result->get_error_message() );
    }

    public function test_test_connection_returns_error_on_http_failure() {
        Functions\when( 'get_option' )->alias( function ( $key, $default = '' ) {
            return $key === 'sag_openai_api_key' ? 'sk-test' : 'gpt-4o-mini';
        } );
        Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
        $wp_error = new \WP_Error( 'http_request_failed', 'cURL error 6: Could not resolve host' );
        Functions\when( 'wp_remote_post' )->justReturn( $wp_error );
        Functions\when( 'is_wp_error' )->justReturn( true );

        $result = ( new \SAG_REST_API() )->test_connection( new \WP_REST_Request() );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'sag_test_failed', $result->get_error_code() );
    }
}
```

- [ ] **Step 2: Run to confirm failures**

```bash
./vendor/bin/phpunit tests/TestEndpointTest.php --no-coverage
```

Expected: errors — "Call to undefined method SAG_REST_API::test_connection()"

- [ ] **Step 3: Add `test_connection` to `includes/class-sag-rest-api.php`**

Add this method to the class (after `save_settings`):

```php
    /**
     * Tests the OpenAI API key with a minimal request (max_tokens: 1).
     * Does not save anything. Uses the currently stored API key.
     *
     * @return array|WP_Error
     */
    public function test_connection( $request ) {
        $api_key = get_option( 'sag_openai_api_key', '' );
        if ( empty( $api_key ) ) {
            return new WP_Error(
                'sag_no_api_key',
                __( 'OpenAI API key is not configured.', 'smart-alt-generator' ),
                array( 'status' => 400 )
            );
        }

        $model    = get_option( 'sag_model', 'gpt-4o-mini' );
        $response = wp_remote_post(
            SAG_OpenAI::ENDPOINT,
            array(
                'timeout' => 15,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode( array(
                    'model'      => $model,
                    'max_tokens' => 1,
                    'messages'   => array(
                        array( 'role' => 'user', 'content' => 'Hi' ),
                    ),
                ) ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'sag_test_failed',
                $response->get_error_message(),
                array( 'status' => 502 )
            );
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( isset( $data['error']['message'] ) ) {
            return new WP_Error(
                'sag_test_failed',
                $data['error']['message'],
                array( 'status' => 400 )
            );
        }

        return rest_ensure_response( array( 'ok' => true, 'model' => $model ) );
    }
```

- [ ] **Step 4: Run tests — confirm 4 pass**

```bash
./vendor/bin/phpunit tests/TestEndpointTest.php --no-coverage
```

Expected: `OK (4 tests, ...)`

- [ ] **Step 5: Run full suite — confirm all pass**

```bash
./vendor/bin/phpunit --no-coverage
```

Expected: `OK (36 tests, ...)` (26 + 6 + 4)

- [ ] **Step 6: Commit**

```bash
git add includes/class-sag-rest-api.php tests/TestEndpointTest.php
git commit -m "feat: add GET /smart-alt/v1/test endpoint (TDD)"
```

---

## Task 3: Build system — webpack.config.js + package.json

**Files:**
- Create: `webpack.config.js`
- Modify: `package.json`

- [ ] **Step 1: Create `webpack.config.js`**

```javascript
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		index: './src/block-editor/index.js',
		'admin-settings': './src/admin-settings/index.js',
		'admin-bulk': './src/admin-bulk/index.js',
	},
};
```

- [ ] **Step 2: Update `package.json` scripts** (remove positional entry args, wp-scripts auto-detects webpack.config.js)

Find:
```json
    "build": "wp-scripts build src/block-editor/index.js --output-path=build",
    "start": "wp-scripts start src/block-editor/index.js --output-path=build"
```

Replace with:
```json
    "build": "wp-scripts build --output-path=build",
    "start": "wp-scripts start --output-path=build"
```

- [ ] **Step 3: Create placeholder entry points so the build doesn't fail**

Create `src/admin-settings/index.js` with a single comment:
```javascript
// Phase 2 — Settings page React app (implemented in Task 5)
```

Create `src/admin-bulk/index.js` with a single comment:
```javascript
// Phase 2 — Bulk page React app (implemented in Task 6)
```

- [ ] **Step 4: Run build — confirm 3 output files**

```bash
npm run build
```

Expected output (among other lines):
```
asset index.js
asset admin-settings.js
asset admin-bulk.js
```

Verify files exist:
```bash
ls build/
```

Expected: `admin-bulk.asset.php`, `admin-bulk.js`, `admin-settings.asset.php`, `admin-settings.js`, `index.asset.php`, `index.js`

- [ ] **Step 5: Commit**

```bash
git add webpack.config.js package.json package-lock.json src/admin-settings/index.js src/admin-bulk/index.js build/
git commit -m "build: add webpack.config.js for 3 React entry points"
```

---

## Task 4: PHP wiring — enqueue, views, CSS, delete sag-bulk.js

**Files:**
- Modify: `admin/class-sag-admin.php`
- Modify: `admin/views/settings-page.php`
- Modify: `admin/views/bulk-page.php`
- Modify: `admin/css/sag-admin.css`
- Delete: `admin/js/sag-bulk.js`

- [ ] **Step 1: Replace `enqueue_assets()` in `admin/class-sag-admin.php`**

Find the existing `enqueue_assets` method and replace it entirely with:

```php
    /** Hooked to admin_enqueue_scripts. Loads React bundles for admin pages. */
    public function enqueue_assets( $hook ) {
        // Settings page — React bundle.
        if ( 'settings_page_sag-settings' === $hook ) {
            $asset_file = SAG_PLUGIN_DIR . 'build/admin-settings.asset.php';
            if ( ! file_exists( $asset_file ) ) {
                return;
            }
            $asset = require $asset_file;
            wp_enqueue_script(
                'sag-admin-settings',
                SAG_PLUGIN_URL . 'build/admin-settings.js',
                $asset['dependencies'],
                $asset['version'],
                true
            );
            wp_localize_script( 'sag-admin-settings', 'sagSettingsData', array(
                'hasConnector' => function_exists( 'wp_ai_client' ),
                'nonce'        => wp_create_nonce( 'wp_rest' ),
                'restBase'     => rest_url( 'smart-alt/v1' ),
            ) );
            return;
        }

        // Bulk page — React bundle.
        if ( 'media_page_sag-bulk' === $hook ) {
            $bulk_query = new WP_Query( array(
                'post_type'      => 'attachment',
                'post_mime_type' => 'image',
                'post_status'    => 'inherit',
                'posts_per_page' => 100,
                'meta_query'     => array(
                    'relation' => 'OR',
                    array( 'key' => '_wp_attachment_image_alt', 'compare' => 'NOT EXISTS' ),
                    array( 'key' => '_wp_attachment_image_alt', 'value' => '', 'compare' => '=' ),
                ),
            ) );
            $image_ids = wp_list_pluck( $bulk_query->posts, 'ID' );

            $asset_file = SAG_PLUGIN_DIR . 'build/admin-bulk.asset.php';
            if ( ! file_exists( $asset_file ) ) {
                return;
            }
            $asset = require $asset_file;
            wp_enqueue_script(
                'sag-admin-bulk',
                SAG_PLUGIN_URL . 'build/admin-bulk.js',
                $asset['dependencies'],
                $asset['version'],
                true
            );
            wp_localize_script( 'sag-admin-bulk', 'sagBulkData', array(
                'imageIds' => $image_ids,
                'nonce'    => wp_create_nonce( 'wp_rest' ),
                'restBase' => rest_url( 'smart-alt/v1' ),
            ) );
            return;
        }

        // Classic media library button — unchanged.
        if ( in_array( $hook, array( 'post.php', 'post-new.php', 'upload.php' ), true ) ) {
            wp_enqueue_script( 'sag-media', SAG_PLUGIN_URL . 'admin/js/sag-media.js', array( 'wp-api-fetch' ), SAG_VERSION, true );
        }
    }
```

- [ ] **Step 2: Replace `admin/views/settings-page.php`**

Full new content:

```php
<?php
/**
 * Settings page — React mount point.
 *
 * @package Smart_Alt_Generator
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}
?>
<div class="wrap">
    <div id="sag-settings-root"></div>
</div>
```

- [ ] **Step 3: Replace `admin/views/bulk-page.php`**

Full new content:

```php
<?php
/**
 * Bulk page — React mount point.
 *
 * @package Smart_Alt_Generator
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}
?>
<div class="wrap">
    <div id="sag-bulk-root"></div>
</div>
```

- [ ] **Step 4: Update `admin/css/sag-admin.css`**

Remove all selectors for the deleted vanilla JS. Keep only what's still used:

```css
/* Classic Media Library button — used by sag-media.js */
.sag-generate-btn { margin-top: 4px; }
```

- [ ] **Step 5: Delete `admin/js/sag-bulk.js`**

```bash
git rm admin/js/sag-bulk.js
```

- [ ] **Step 6: Run PHPUnit — confirm all tests still pass**

```bash
./vendor/bin/phpunit --no-coverage
```

Expected: `OK (36 tests, ...)`

- [ ] **Step 7: Commit**

```bash
git add admin/class-sag-admin.php admin/views/settings-page.php admin/views/bulk-page.php admin/css/sag-admin.css
git commit -m "feat: wire PHP for React admin pages — mount points, enqueue, localize"
```

---

## Task 5: Settings page React app

**Files:**
- Modify: `src/admin-settings/index.js`

- [ ] **Step 1: Write the complete Settings page React app**

Replace the placeholder in `src/admin-settings/index.js` with:

```javascript
import { createRoot, useState, useEffect, useCallback } from '@wordpress/element';
import {
	Card,
	CardHeader,
	CardBody,
	CardFooter,
	Button,
	TextControl,
	SelectControl,
	ToggleControl,
	Spinner,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const { hasConnector = false } = window.sagSettingsData ?? {};

/** WP 7.0+ notice — shown only when AI Connectors are active. */
function ConnectorsNotice() {
	return (
		<div className="notice notice-success inline" style={ { marginTop: 0 } }>
			<p>
				{ __(
					'WordPress AI Connectors detected. Using your configured AI provider.',
					'smart-alt-generator'
				) }
			</p>
		</div>
	);
}

/**
 * "⚡ Test Connection" button inside ProviderCard.
 * Resets when the API key changes.
 */
function TestConnectionButton( { apiKey } ) {
	const [ status, setStatus ] = useState( 'idle' ); // idle | testing | ok | error
	const [ message, setMessage ] = useState( '' );

	useEffect( () => {
		setStatus( 'idle' );
		setMessage( '' );
	}, [ apiKey ] );

	const handleTest = async () => {
		setStatus( 'testing' );
		try {
			await apiFetch( { path: '/smart-alt/v1/test' } );
			setStatus( 'ok' );
			setMessage( __( 'Connection successful', 'smart-alt-generator' ) );
		} catch ( e ) {
			setStatus( 'error' );
			setMessage( e?.message || __( 'Connection failed', 'smart-alt-generator' ) );
		}
	};

	return (
		<div style={ { display: 'flex', alignItems: 'center', gap: '12px', marginTop: '8px' } }>
			<Button
				variant="secondary"
				onClick={ handleTest }
				disabled={ status === 'testing' }
			>
				{ status === 'testing' ? <Spinner /> : '⚡ ' }
				{ __( 'Test Connection', 'smart-alt-generator' ) }
			</Button>
			{ status === 'ok' && (
				<span style={ { color: '#00a32a', fontSize: '13px' } }>
					✓ { message }
				</span>
			) }
			{ status === 'error' && (
				<span style={ { color: '#d63638', fontSize: '13px' } }>
					✗ { message }
				</span>
			) }
		</div>
	);
}

/** AI Provider card — hidden on WP 7.0+ when connectors handle everything. */
function ProviderCard( { settings, onChange } ) {
	if ( hasConnector ) {
		return null;
	}
	return (
		<Card style={ { marginBottom: '16px' } }>
			<CardHeader>
				<strong>{ __( 'AI Provider', 'smart-alt-generator' ) }</strong>
			</CardHeader>
			<CardBody>
				<TextControl
					label={ __( 'OpenAI API Key', 'smart-alt-generator' ) }
					type="password"
					value={ settings.sag_openai_api_key }
					onChange={ ( v ) => onChange( 'sag_openai_api_key', v ) }
					help={ __( 'Get your key at platform.openai.com', 'smart-alt-generator' ) }
					autoComplete="off"
				/>
				<SelectControl
					label={ __( 'Model', 'smart-alt-generator' ) }
					value={ settings.sag_model }
					options={ [
						{ label: 'gpt-4o-mini — Fastest, cheapest (recommended)', value: 'gpt-4o-mini' },
						{ label: 'gpt-4o — Highest quality', value: 'gpt-4o' },
					] }
					onChange={ ( v ) => onChange( 'sag_model', v ) }
				/>
				<TestConnectionButton apiKey={ settings.sag_openai_api_key } />
			</CardBody>
		</Card>
	);
}

/** Generation settings card — auto-generate toggle + language. */
function GenerationCard( { settings, onChange } ) {
	return (
		<Card style={ { marginBottom: '16px' } }>
			<CardHeader>
				<strong>{ __( 'Generation', 'smart-alt-generator' ) }</strong>
			</CardHeader>
			<CardBody>
				<ToggleControl
					label={ __( 'Auto-generate on upload', 'smart-alt-generator' ) }
					help={ __(
						'Automatically generate alt text when a new image is uploaded to the Media Library.',
						'smart-alt-generator'
					) }
					checked={ settings.sag_auto_generate }
					onChange={ ( v ) => onChange( 'sag_auto_generate', v ) }
				/>
				<TextControl
					label={ __( 'Language', 'smart-alt-generator' ) }
					value={ settings.sag_language }
					onChange={ ( v ) => onChange( 'sag_language', v ) }
					help={ __(
						'Use "auto" to match the site language, or enter a language name (e.g. "Spanish").',
						'smart-alt-generator'
					) }
				/>
			</CardBody>
		</Card>
	);
}

/** Bottom footer with Save button and inline success/error notice. */
function SaveFooter( { onSave, saveStatus } ) {
	return (
		<Card>
			<CardFooter justify="flex-end">
				{ saveStatus === 'saved' && (
					<span style={ { color: '#00a32a', marginRight: '12px' } }>
						✓ { __( 'Settings saved', 'smart-alt-generator' ) }
					</span>
				) }
				{ saveStatus === 'error' && (
					<span style={ { color: '#d63638', marginRight: '12px' } }>
						✗ { __( 'Save failed. Please try again.', 'smart-alt-generator' ) }
					</span>
				) }
				<Button
					variant="primary"
					onClick={ onSave }
					disabled={ saveStatus === 'saving' }
				>
					{ saveStatus === 'saving'
						? __( 'Saving…', 'smart-alt-generator' )
						: __( 'Save Settings', 'smart-alt-generator' ) }
				</Button>
			</CardFooter>
		</Card>
	);
}

const DEFAULT_SETTINGS = {
	sag_openai_api_key: '',
	sag_model: 'gpt-4o-mini',
	sag_language: 'auto',
	sag_auto_generate: false,
};

/** Root component — loads settings from REST, handles save. */
function SettingsApp() {
	const [ settings, setSettings ] = useState( DEFAULT_SETTINGS );
	const [ loading, setLoading ] = useState( true );
	const [ saveStatus, setSaveStatus ] = useState( null ); // null | saving | saved | error

	useEffect( () => {
		apiFetch( { path: '/smart-alt/v1/settings' } )
			.then( ( data ) => {
				setSettings( ( prev ) => ( { ...prev, ...data } ) );
				setLoading( false );
			} )
			.catch( () => setLoading( false ) );
	}, [] );

	const handleChange = useCallback( ( key, value ) => {
		setSettings( ( prev ) => ( { ...prev, [ key ]: value } ) );
		setSaveStatus( null );
	}, [] );

	const handleSave = async () => {
		setSaveStatus( 'saving' );
		try {
			await apiFetch( {
				path: '/smart-alt/v1/settings',
				method: 'POST',
				data: settings,
			} );
			setSaveStatus( 'saved' );
			setTimeout( () => setSaveStatus( null ), 3000 );
		} catch {
			setSaveStatus( 'error' );
		}
	};

	if ( loading ) {
		return <Spinner />;
	}

	return (
		<div style={ { maxWidth: '640px', paddingTop: '16px' } }>
			<h1>{ __( 'Smart Alt Generator', 'smart-alt-generator' ) }</h1>
			{ hasConnector && <ConnectorsNotice /> }
			<ProviderCard settings={ settings } onChange={ handleChange } />
			<GenerationCard settings={ settings } onChange={ handleChange } />
			<SaveFooter onSave={ handleSave } saveStatus={ saveStatus } />
		</div>
	);
}

const root = document.getElementById( 'sag-settings-root' );
if ( root ) {
	createRoot( root ).render( <SettingsApp /> );
}
```

- [ ] **Step 2: Build**

```bash
npm run build
```

Expected: no errors. `build/admin-settings.js` updated.

- [ ] **Step 3: Run PHPUnit — confirm all tests still pass**

```bash
./vendor/bin/phpunit --no-coverage
```

Expected: `OK (36 tests, ...)`

- [ ] **Step 4: Manual E2E in Docker WP**

Start Docker:
```bash
cd "f:/tmp/wp-aatg-docker" && MSYS_NO_PATHCONV=1 docker compose up -d
```

Open http://localhost:8910/wp-admin → Settings → AI Alt Text:
- Verify: Cards render (AI Provider + Generation)
- Verify: API key field shows current saved value
- Verify: Change language to "Portuguese" → click Save → green "✓ Settings saved" appears
- Verify: Click "⚡ Test Connection" → shows ✓ or ✗ inline
- Verify: Reload page → "Portuguese" persists

- [ ] **Step 5: Commit**

```bash
git add src/admin-settings/index.js build/
git commit -m "feat: implement Settings page React app (SettingsApp, ProviderCard, GenerationCard, TestConnectionButton, SaveFooter)"
```

---

## Task 6: Bulk page React app

**Files:**
- Modify: `src/admin-bulk/index.js`

- [ ] **Step 1: Write the complete Bulk page React app**

Replace the placeholder in `src/admin-bulk/index.js` with:

```javascript
import { createRoot, useState, useCallback, useRef } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const { imageIds = [] } = window.sagBulkData ?? {};

/** 4 stat boxes: total / completed / errors / remaining. */
function StatBar( { total, successes, errors } ) {
	const remaining = total - successes - errors;
	const stats = [
		{ label: __( 'Total', 'smart-alt-generator' ), value: total, color: '#1d2327' },
		{ label: __( 'Completed', 'smart-alt-generator' ), value: successes, color: '#00a32a' },
		{ label: __( 'Errors', 'smart-alt-generator' ), value: errors, color: '#d63638' },
		{ label: __( 'Remaining', 'smart-alt-generator' ), value: remaining, color: '#2271b1' },
	];
	return (
		<div style={ { display: 'flex', gap: '12px', marginBottom: '16px' } }>
			{ stats.map( ( s ) => (
				<div
					key={ s.label }
					style={ {
						background: '#fff',
						border: '1px solid #c3c4c7',
						borderRadius: '4px',
						padding: '12px 16px',
						flex: 1,
						textAlign: 'center',
					} }
				>
					<div style={ { fontSize: '26px', fontWeight: 700, color: s.color, lineHeight: 1 } }>
						{ s.value }
					</div>
					<div style={ { fontSize: '11px', color: '#646970', textTransform: 'uppercase', letterSpacing: '.3px', marginTop: '4px' } }>
						{ s.label }
					</div>
				</div>
			) ) }
		</div>
	);
}

/** Progress bar + percentage + N/total counter. Pure display component. */
function ProgressBar( { processed, total } ) {
	const pct = total > 0 ? Math.round( ( processed / total ) * 100 ) : 0;
	return (
		<div style={ { background: '#fff', border: '1px solid #c3c4c7', borderRadius: '4px', padding: '12px 16px', marginBottom: '12px' } }>
			<div style={ { background: '#e0e0e0', borderRadius: '4px', height: '10px', overflow: 'hidden' } }>
				<div
					style={ {
						background: '#2271b1',
						height: '100%',
						width: `${ pct }%`,
						borderRadius: '4px',
						transition: 'width .3s',
					} }
				/>
			</div>
			<div style={ { display: 'flex', justifyContent: 'space-between', marginTop: '6px' } }>
				<span style={ { fontSize: '12px', fontWeight: 600, color: '#2271b1' } }>{ pct }%</span>
				<span style={ { fontSize: '11px', color: '#646970' } }>{ processed } / { total }</span>
			</div>
		</div>
	);
}

/** Single button whose label changes based on current status. */
function BulkControls( { status, onStart, onPause, onResume } ) {
	if ( status === 'idle' ) {
		return (
			<Button variant="primary" onClick={ onStart }>
				{ __( 'Generate All', 'smart-alt-generator' ) }
			</Button>
		);
	}
	if ( status === 'running' ) {
		return (
			<Button variant="secondary" onClick={ onPause }>
				{ __( '⏸ Pause', 'smart-alt-generator' ) }
			</Button>
		);
	}
	if ( status === 'paused' ) {
		return (
			<Button variant="primary" onClick={ onResume }>
				{ __( '▶ Resume', 'smart-alt-generator' ) }
			</Button>
		);
	}
	// done
	return (
		<Button variant="secondary" disabled>
			{ __( '✓ All done', 'smart-alt-generator' ) }
		</Button>
	);
}

/** Scrollable result log — most recent item at top. */
function LogList( { log, onClear } ) {
	if ( log.length === 0 ) {
		return null;
	}
	return (
		<div style={ { background: '#fff', border: '1px solid #c3c4c7', borderRadius: '4px', overflow: 'hidden' } }>
			<div style={ { padding: '8px 12px', borderBottom: '1px solid #f0f0f1', display: 'flex', justifyContent: 'space-between', alignItems: 'center' } }>
				<span style={ { fontSize: '11px', fontWeight: 600, color: '#646970', textTransform: 'uppercase', letterSpacing: '.3px' } }>
					{ __( 'Results', 'smart-alt-generator' ) }
				</span>
				<button
					onClick={ onClear }
					style={ { fontSize: '11px', color: '#2271b1', background: 'none', border: 'none', cursor: 'pointer', padding: 0 } }
				>
					{ __( 'Clear log', 'smart-alt-generator' ) }
				</button>
			</div>
			<div style={ { maxHeight: '240px', overflowY: 'auto' } }>
				{ log.map( ( item, i ) => (
					<div
						key={ i }
						style={ { display: 'flex', gap: '8px', padding: '6px 12px', borderBottom: '1px solid #f9f9f9', fontSize: '12px' } }
					>
						<span style={ { color: item.ok ? '#00a32a' : '#d63638', flexShrink: 0 } }>
							{ item.ok ? '✓' : '✗' }
						</span>
						<span style={ { flex: 1, color: item.ok ? '#1d2327' : '#d63638' } }>
							{ item.text }
						</span>
						<span style={ { color: '#c3c4c7', fontSize: '10px' } }>#{ item.id }</span>
					</div>
				) ) }
			</div>
		</div>
	);
}

/** Root component — manages the generation loop state machine. */
function BulkApp() {
	const [ status, setStatus ] = useState( 'idle' ); // idle | running | paused | done
	const [ successes, setSuccesses ] = useState( 0 );
	const [ errors, setErrors ] = useState( 0 );
	const [ processed, setProcessed ] = useState( 0 );
	const [ log, setLog ] = useState( [] );
	const pausedRef = useRef( false );
	const indexRef = useRef( 0 );

	const total = imageIds.length;

	const addLog = useCallback( ( id, ok, text ) => {
		setLog( ( prev ) => [ { id, ok, text }, ...prev ] );
	}, [] );

	const runFrom = useCallback( async ( startIndex ) => {
		for ( let i = startIndex; i < imageIds.length; i++ ) {
			if ( pausedRef.current ) {
				indexRef.current = i;
				return;
			}
			try {
				const res = await apiFetch( {
					path: '/smart-alt/v1/generate',
					method: 'POST',
					data: { image_id: imageIds[ i ] },
				} );
				addLog( imageIds[ i ], true, res.alt_text );
				setSuccesses( ( s ) => s + 1 );
			} catch ( e ) {
				addLog(
					imageIds[ i ],
					false,
					e?.message || __( 'Generation failed.', 'smart-alt-generator' )
				);
				setErrors( ( n ) => n + 1 );
			}
			setProcessed( i + 1 );
		}
		if ( ! pausedRef.current ) {
			setStatus( 'done' );
		}
	}, [ addLog ] );

	const handleStart = useCallback( () => {
		pausedRef.current = false;
		indexRef.current = 0;
		setStatus( 'running' );
		runFrom( 0 );
	}, [ runFrom ] );

	const handlePause = useCallback( () => {
		pausedRef.current = true;
		setStatus( 'paused' );
	}, [] );

	const handleResume = useCallback( () => {
		pausedRef.current = false;
		setStatus( 'running' );
		runFrom( indexRef.current );
	}, [ runFrom ] );

	if ( total === 0 ) {
		return (
			<div style={ { paddingTop: '16px' } }>
				<h1>{ __( 'Bulk Alt Text Generator', 'smart-alt-generator' ) }</h1>
				<p>{ __( 'All your images already have alt text.', 'smart-alt-generator' ) }</p>
			</div>
		);
	}

	return (
		<div style={ { maxWidth: '760px', paddingTop: '16px' } }>
			<h1>{ __( 'Bulk Alt Text Generator', 'smart-alt-generator' ) }</h1>
			<StatBar total={ total } successes={ successes } errors={ errors } />
			{ status !== 'idle' && <ProgressBar processed={ processed } total={ total } /> }
			<div style={ { marginBottom: '16px' } }>
				<BulkControls
					status={ status }
					onStart={ handleStart }
					onPause={ handlePause }
					onResume={ handleResume }
				/>
			</div>
			<LogList log={ log } onClear={ () => setLog( [] ) } />
		</div>
	);
}

const root = document.getElementById( 'sag-bulk-root' );
if ( root ) {
	createRoot( root ).render( <BulkApp /> );
}
```

- [ ] **Step 2: Build**

```bash
npm run build
```

Expected: no errors. `build/admin-bulk.js` updated.

- [ ] **Step 3: Run PHPUnit — confirm all tests still pass**

```bash
./vendor/bin/phpunit --no-coverage
```

Expected: `OK (36 tests, ...)`

- [ ] **Step 4: Manual E2E in Docker WP**

Open http://localhost:8910/wp-admin → Media → Bulk Alt Text:
- Verify: Stats show correct totals (images without alt text)
- Verify: "Generate All" button is visible
- Verify: Click Generate All → progress bar fills, log populates with results
- Verify: Click Pause → generation stops after current image completes
- Verify: Click Resume → continues from where it stopped
- Verify: After last image → button shows "✓ All done" (disabled)
- Verify: "Clear log" button clears the log list
- Verify: If no images missing alt text → "All your images already have alt text." message

- [ ] **Step 5: Commit**

```bash
git add src/admin-bulk/index.js build/
git commit -m "feat: implement Bulk page React app (BulkApp, StatBar, ProgressBar, BulkControls, LogList)"
```

---

## Task 7: Final verification

**Files:** none

- [ ] **Step 1: Run full PHPUnit suite**

```bash
./vendor/bin/phpunit --no-coverage
```

Expected: `OK (36 tests, ...)` — all existing + new tests passing.

- [ ] **Step 2: Grep for any remaining vanilla JS references to old selectors**

```bash
grep -rn "sag-start\|sag-progress\|aatg-start\|aatg-progress" . --include="*.php" --include="*.js" --exclude-dir=.git --exclude-dir=node_modules --exclude-dir=build --exclude-dir=vendor
```

Expected: zero matches.

- [ ] **Step 3: Full manual smoke test in Docker WP**

- Settings page: load → change model → save → reload → confirm persisted
- Settings page: Test Connection → ✓ or clear error
- Bulk page: generate all → progress bar → log
- Gutenberg: open image block → AI Alt Text panel still works (existing block-editor bundle unchanged)
- Classic Media Library: "Generate Alt Text" button still works (sag-media.js unchanged)

- [ ] **Step 4: Commit (if any minor fixes were made in Step 3)**

```bash
git add -p   # stage only intentional changes
git commit -m "fix: phase 2 post-E2E tweaks"
```

(Skip this commit if no changes were needed.)

---

## Post-Phase Checklist

- [ ] 36 PHPUnit tests pass
- [ ] Settings page renders Modern Cards in WP admin
- [ ] Test Connection button works inline (no page reload)
- [ ] Save Settings persists via REST (no page reload)
- [ ] Bulk page Generate/Pause/Resume cycle works
- [ ] Gutenberg block panel unaffected
- [ ] Classic Media Library button unaffected
- [ ] `admin/js/sag-bulk.js` deleted (no phantom JS loading)
