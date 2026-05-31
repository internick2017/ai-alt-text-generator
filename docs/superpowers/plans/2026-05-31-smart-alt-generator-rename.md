# Smart Alt Generator — Plugin Rename Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rename the WordPress plugin from `ai-alt-text-generator` (prefix `AATG_`) to `smart-alt-generator` (prefix `SAG_`) so it passes WordPress.org directory slug uniqueness check.

**Architecture:** Pure rename — zero functional changes. All 26 PHPUnit tests must pass before and after. Work is split in two groups: (A) includes/ + tests — the part the test suite covers, committed once tests pass; (B) admin/, entry file, JS, assets, metadata — committed in one follow-up commit. GitHub repo renamed last.

**Tech Stack:** PHP 8.1, WordPress 6.4+, PHPUnit 11.5, Brain Monkey, Node.js / @wordpress/scripts, Git

**All commands run from:** `E:/dev/02-wordpress/ai-alt-text-generator`

---

## Group A — PHP includes + Tests

### Task 1: Verify baseline

**Files:** none (read-only verification)

- [ ] **Step 1: Run the full test suite**

```bash
cd "E:/dev/02-wordpress/ai-alt-text-generator"
./vendor/bin/phpunit
```

Expected output: `OK (26 tests, ...)`  
If anything fails here, stop and fix before proceeding.

---

### Task 2: Rename includes/ PHP class files

**Files:** 8 renames in `includes/`

- [ ] **Step 1: Rename all 8 class files with git mv**

```bash
git mv includes/class-aatg-image.php       includes/class-sag-image.php
git mv includes/class-aatg-openai.php      includes/class-sag-openai.php
git mv includes/class-aatg-ai-provider.php includes/class-sag-ai-provider.php
git mv includes/class-aatg-generator.php   includes/class-sag-generator.php
git mv includes/class-aatg-rest-api.php    includes/class-sag-rest-api.php
git mv includes/class-aatg-settings.php    includes/class-sag-settings.php
git mv includes/class-aatg-media.php       includes/class-sag-media.php
git mv includes/class-aatg-plugin.php      includes/class-sag-plugin.php
```

(Tests will break until Tasks 3–5 are done — that is expected.)

---

### Task 3: Update `tests/bootstrap.php`

**Files:**
- Modify: `tests/bootstrap.php`

- [ ] **Step 1: Update the require loop filenames**

Find:
```php
foreach ( array(
    'class-aatg-image.php',
    'class-aatg-openai.php',
    'class-aatg-ai-provider.php',
    'class-aatg-generator.php',
    'class-aatg-rest-api.php',
    'class-aatg-settings.php',
    'class-aatg-media.php',
    'class-aatg-plugin.php',
) as $file ) {
```

Replace with:
```php
foreach ( array(
    'class-sag-image.php',
    'class-sag-openai.php',
    'class-sag-ai-provider.php',
    'class-sag-generator.php',
    'class-sag-rest-api.php',
    'class-sag-settings.php',
    'class-sag-media.php',
    'class-sag-plugin.php',
) as $file ) {
```

---

### Task 4: Update class internals — `includes/class-sag-image.php`

**Files:**
- Modify: `includes/class-sag-image.php`

- [ ] **Step 1: Update @package**

Find: `@package AI_Alt_Text_Generator`  
Replace: `@package Smart_Alt_Generator`

- [ ] **Step 2: Rename the class**

Find: `class AATG_Image {`  
Replace: `class SAG_Image {`

- [ ] **Step 3: Rename WP error codes and text domain in `path_to_data_uri()`**

Find:
```php
        return new WP_Error( 'aatg_image_unreadable', __( 'Image file could not be read.', 'ai-alt-text-generator' ) );
```
Replace:
```php
        return new WP_Error( 'sag_image_unreadable', __( 'Image file could not be read.', 'smart-alt-generator' ) );
```

Find:
```php
            return new WP_Error( 'aatg_image_type', __( 'Unsupported image type.', 'ai-alt-text-generator' ) );
```
Replace:
```php
            return new WP_Error( 'sag_image_type', __( 'Unsupported image type.', 'smart-alt-generator' ) );
```

Find:
```php
            return new WP_Error( 'aatg_image_too_large', __( 'Image is too large to process.', 'ai-alt-text-generator' ) );
```
Replace:
```php
            return new WP_Error( 'sag_image_too_large', __( 'Image is too large to process.', 'smart-alt-generator' ) );
```

Find (second `aatg_image_unreadable`):
```php
        return new WP_Error( 'aatg_image_unreadable', __( 'Image file could not be read.', 'ai-alt-text-generator' ) );
```
Replace:
```php
        return new WP_Error( 'sag_image_unreadable', __( 'Image file could not be read.', 'smart-alt-generator' ) );
```

- [ ] **Step 4: Update text domain in `url_to_data_uri()`**

Find:
```php
            return new WP_Error( 'aatg_image_fetch', __( 'Could not download the image.', 'ai-alt-text-generator' ) );
```
Replace:
```php
            return new WP_Error( 'sag_image_fetch', __( 'Could not download the image.', 'smart-alt-generator' ) );
```

Find:
```php
            return new WP_Error( 'aatg_image_fetch', __( 'Downloaded image was empty.', 'ai-alt-text-generator' ) );
```
Replace:
```php
            return new WP_Error( 'sag_image_fetch', __( 'Downloaded image was empty.', 'smart-alt-generator' ) );
```

---

### Task 5: Update class internals — `includes/class-sag-openai.php`

**Files:**
- Modify: `includes/class-sag-openai.php`

- [ ] **Step 1: Update @package**

Find: `@package AI_Alt_Text_Generator`  
Replace: `@package Smart_Alt_Generator`

- [ ] **Step 2: Rename the class**

Find: `class AATG_OpenAI {`  
Replace: `class SAG_OpenAI {`

- [ ] **Step 3: Update WP error codes and text domain**

Find: `return new WP_Error( 'aatg_openai_error', $data['error']['message'] );`  
Replace: `return new WP_Error( 'sag_openai_error', $data['error']['message'] );`

Find:
```php
            return new WP_Error( 'aatg_empty_response', __( 'OpenAI returned an empty response.', 'ai-alt-text-generator' ) );
```
Replace:
```php
            return new WP_Error( 'sag_empty_response', __( 'OpenAI returned an empty response.', 'smart-alt-generator' ) );
```

- [ ] **Step 4: Update option names in `request()`**

Find: `$api_key = get_option( 'aatg_openai_api_key', '' );`  
Replace: `$api_key = get_option( 'sag_openai_api_key', '' );`

Find:
```php
            return new WP_Error( 'aatg_no_api_key', __( 'OpenAI API key is not configured.', 'ai-alt-text-generator' ) );
```
Replace:
```php
            return new WP_Error( 'sag_no_api_key', __( 'OpenAI API key is not configured.', 'smart-alt-generator' ) );
```

Find: `$model = get_option( 'aatg_model', 'gpt-4o-mini' );`  
Replace: `$model = get_option( 'sag_model', 'gpt-4o-mini' );`

---

### Task 6: Update class internals — `includes/class-sag-ai-provider.php`

**Files:**
- Modify: `includes/class-sag-ai-provider.php`

- [ ] **Step 1: Update @package**

Find: `@package AI_Alt_Text_Generator`  
Replace: `@package Smart_Alt_Generator`

- [ ] **Step 2: Rename the class**

Find: `class AATG_AI_Provider {`  
Replace: `class SAG_AI_Provider {`

- [ ] **Step 3: Update text domain in `build_prompt()`**

Find: `__( 'Use the same language as the website.', 'ai-alt-text-generator' )`  
Replace: `__( 'Use the same language as the website.', 'smart-alt-generator' )`

Find: `__( 'Write the alt text in %s.', 'ai-alt-text-generator' )`  
Replace: `__( 'Write the alt text in %s.', 'smart-alt-generator' )`

Find: `__( 'Generate a concise, descriptive alt text for this image. Maximum 125 characters. %s Return only the alt text, no quotes.', 'ai-alt-text-generator' )`  
Replace: `__( 'Generate a concise, descriptive alt text for this image. Maximum 125 characters. %s Return only the alt text, no quotes.', 'smart-alt-generator' )`

- [ ] **Step 4: Update internal class reference in `generate_via_openai()`**

Find: `$client = new AATG_OpenAI();`  
Replace: `$client = new SAG_OpenAI();`

---

### Task 7: Update class internals — `includes/class-sag-generator.php`

**Files:**
- Modify: `includes/class-sag-generator.php`

- [ ] **Step 1: Update @package**

Find: `@package AI_Alt_Text_Generator`  
Replace: `@package Smart_Alt_Generator`

- [ ] **Step 2: Rename the class**

Find: `class AATG_Generator {`  
Replace: `class SAG_Generator {`

- [ ] **Step 3: Update @var and constructor docblock class refs**

Find: `/** @var object Anything with a generate( $image, $language ) method. */`  
(no change needed — generic)

Find: `/** @var object AATG_Image (or compatible) for data-URI conversion. */`  
Replace: `/** @var object SAG_Image (or compatible) for data-URI conversion. */`

Find:
```php
     * @param object|null $provider Inject a provider; defaults to AATG_AI_Provider.
     * @param object|null $image    Inject an image helper; defaults to AATG_Image.
```
Replace:
```php
     * @param object|null $provider Inject a provider; defaults to SAG_AI_Provider.
     * @param object|null $image    Inject an image helper; defaults to SAG_Image.
```

- [ ] **Step 4: Update constructor defaults**

Find: `$this->provider = $provider ?? new AATG_AI_Provider();`  
Replace: `$this->provider = $provider ?? new SAG_AI_Provider();`

Find: `$this->image    = $image ?? new AATG_Image();`  
Replace: `$this->image    = $image ?? new SAG_Image();`

- [ ] **Step 5: Update WP error code and text domain in `generate_for_image()`**

Find:
```php
            return new WP_Error( 'aatg_invalid_image', __( 'Image not found.', 'ai-alt-text-generator' ) );
```
Replace:
```php
            return new WP_Error( 'sag_invalid_image', __( 'Image not found.', 'smart-alt-generator' ) );
```

- [ ] **Step 6: Update option name in `run_provider()`**

Find: `$language = get_option( 'aatg_language', 'auto' );`  
Replace: `$language = get_option( 'sag_language', 'auto' );`

---

### Task 8: Update class internals — `includes/class-sag-rest-api.php`

**Files:**
- Modify: `includes/class-sag-rest-api.php`

- [ ] **Step 1: Update @package**

Find: `@package AI_Alt_Text_Generator`  
Replace: `@package Smart_Alt_Generator`

- [ ] **Step 2: Rename the class**

Find: `class AATG_REST_API {`  
Replace: `class SAG_REST_API {`

- [ ] **Step 3: Update REST namespace constant**

Find: `const REST_NAMESPACE = 'ai-alt-text/v1';`  
Replace: `const REST_NAMESPACE = 'smart-alt/v1';`

- [ ] **Step 4: Update WP error code and text domain in `handle_generate()`**

Find:
```php
                'aatg_missing_param',
                __( 'Provide image_id or image_url.', 'ai-alt-text-generator' ),
```
Replace:
```php
                'sag_missing_param',
                __( 'Provide image_id or image_url.', 'smart-alt-generator' ),
```

- [ ] **Step 5: Update internal class reference**

Find: `$generator = $this->generator ?? new AATG_Generator();`  
Replace: `$generator = $this->generator ?? new SAG_Generator();`

---

### Task 9: Update class internals — `includes/class-sag-settings.php`

**Files:**
- Modify: `includes/class-sag-settings.php`

- [ ] **Step 1: Update @package**

Find: `@package AI_Alt_Text_Generator`  
Replace: `@package Smart_Alt_Generator`

- [ ] **Step 2: Rename the class**

Find: `class AATG_Settings {`  
Replace: `class SAG_Settings {`

- [ ] **Step 3: Update settings group constant**

Find: `const GROUP = 'aatg_settings';`  
Replace: `const GROUP = 'sag_settings';`

- [ ] **Step 4: Update all option names in `register()`**

Find: `register_setting( self::GROUP, 'aatg_openai_api_key',`  
Replace: `register_setting( self::GROUP, 'sag_openai_api_key',`

Find: `register_setting( self::GROUP, 'aatg_model',`  
Replace: `register_setting( self::GROUP, 'sag_model',`

Find: `register_setting( self::GROUP, 'aatg_language',`  
Replace: `register_setting( self::GROUP, 'sag_language',`

Find: `register_setting( self::GROUP, 'aatg_auto_generate',`  
Replace: `register_setting( self::GROUP, 'sag_auto_generate',`

---

### Task 10: Update class internals — `includes/class-sag-media.php`

**Files:**
- Modify: `includes/class-sag-media.php`

- [ ] **Step 1: Update @package**

Find: `@package AI_Alt_Text_Generator`  
Replace: `@package Smart_Alt_Generator`

- [ ] **Step 2: Rename the class**

Find: `class AATG_Media {`  
Replace: `class SAG_Media {`

- [ ] **Step 3: Update option name in `maybe_auto_generate()`**

Find: `if ( ! get_option( 'aatg_auto_generate', false ) ) {`  
Replace: `if ( ! get_option( 'sag_auto_generate', false ) ) {`

- [ ] **Step 4: Update internal class reference and text domain**

Find: `$generator = $this->generator ?? new AATG_Generator();`  
Replace: `$generator = $this->generator ?? new SAG_Generator();`

Find: `'<button type="button" class="button aatg-generate-btn" data-image-id="%d">%s</button>',`  
Replace: `'<button type="button" class="button sag-generate-btn" data-image-id="%d">%s</button>',`

Find: `esc_html__( 'Generate Alt Text', 'ai-alt-text-generator' )`  
Replace: `esc_html__( 'Generate Alt Text', 'smart-alt-generator' )`

Find: `$form_fields['aatg_generate'] = array(`  
Replace: `$form_fields['sag_generate'] = array(`

Find: `'label' => __( 'AI Alt Text', 'ai-alt-text-generator' ),`  
Replace: `'label' => __( 'AI Alt Text', 'smart-alt-generator' ),`

---

### Task 11: Update class internals — `includes/class-sag-plugin.php`

**Files:**
- Modify: `includes/class-sag-plugin.php`

- [ ] **Step 1: Update @package**

Find: `@package AI_Alt_Text_Generator`  
Replace: `@package Smart_Alt_Generator`

- [ ] **Step 2: Rename the class**

Find: `class AATG_Plugin {`  
Replace: `class SAG_Plugin {`

- [ ] **Step 3: Update the singleton self-reference comment**

Find: `/** @var AATG_Plugin|null The single instance. */`  
Replace: `/** @var SAG_Plugin|null The single instance. */`

- [ ] **Step 4: Update all require_once filenames in `load_dependencies()`**

Find:
```php
        require_once AATG_PLUGIN_DIR . 'includes/class-aatg-image.php';
        require_once AATG_PLUGIN_DIR . 'includes/class-aatg-openai.php';
        require_once AATG_PLUGIN_DIR . 'includes/class-aatg-ai-provider.php';
        require_once AATG_PLUGIN_DIR . 'includes/class-aatg-generator.php';
        require_once AATG_PLUGIN_DIR . 'includes/class-aatg-rest-api.php';
        require_once AATG_PLUGIN_DIR . 'includes/class-aatg-settings.php';
        require_once AATG_PLUGIN_DIR . 'includes/class-aatg-media.php';
        require_once AATG_PLUGIN_DIR . 'admin/class-aatg-admin.php';
```
Replace:
```php
        require_once SAG_PLUGIN_DIR . 'includes/class-sag-image.php';
        require_once SAG_PLUGIN_DIR . 'includes/class-sag-openai.php';
        require_once SAG_PLUGIN_DIR . 'includes/class-sag-ai-provider.php';
        require_once SAG_PLUGIN_DIR . 'includes/class-sag-generator.php';
        require_once SAG_PLUGIN_DIR . 'includes/class-sag-rest-api.php';
        require_once SAG_PLUGIN_DIR . 'includes/class-sag-settings.php';
        require_once SAG_PLUGIN_DIR . 'includes/class-sag-media.php';
        require_once SAG_PLUGIN_DIR . 'admin/class-sag-admin.php';
```

- [ ] **Step 5: Update all class_exists and class instantiations in `register_hooks()`**

Find: `if ( class_exists( 'AATG_REST_API' ) ) {`  
Replace: `if ( class_exists( 'SAG_REST_API' ) ) {`

Find: `$rest = new AATG_REST_API();`  
Replace: `$rest = new SAG_REST_API();`

Find: `if ( class_exists( 'AATG_Settings' ) ) {`  
Replace: `if ( class_exists( 'SAG_Settings' ) ) {`

Find: `$settings = new AATG_Settings();`  
Replace: `$settings = new SAG_Settings();`

Find: `if ( is_admin() && class_exists( 'AATG_Admin' ) ) {`  
Replace: `if ( is_admin() && class_exists( 'SAG_Admin' ) ) {`

Find: `$admin = new AATG_Admin();`  
Replace: `$admin = new SAG_Admin();`

Find: `if ( class_exists( 'AATG_Media' ) ) {`  
Replace: `if ( class_exists( 'SAG_Media' ) ) {`

Find: `$media = new AATG_Media();`  
Replace: `$media = new SAG_Media();`

- [ ] **Step 6: Update option names in `activate()`**

Find:
```php
        add_option( 'aatg_model', 'gpt-4o-mini' );
        add_option( 'aatg_language', 'auto' );
        add_option( 'aatg_auto_generate', false );
```
Replace:
```php
        add_option( 'sag_model', 'gpt-4o-mini' );
        add_option( 'sag_language', 'auto' );
        add_option( 'sag_auto_generate', false );
```

- [ ] **Step 7: Update the load_dependencies guard constant**

Find: `if ( ! defined( 'AATG_PLUGIN_DIR' ) ) {`  
Replace: `if ( ! defined( 'SAG_PLUGIN_DIR' ) ) {`

---

### Task 12: Update `tests/TestCase.php`

**Files:**
- Modify: `tests/TestCase.php`

- [ ] **Step 1: Update namespace**

Find: `namespace AATG\Tests;`  
Replace: `namespace SAG\Tests;`

---

### Task 13: Update all test files

**Files:** `tests/AIProviderTest.php`, `tests/GeneratorTest.php`, `tests/ImageTest.php`, `tests/MediaTest.php`, `tests/OpenAITest.php`, `tests/PluginTest.php`, `tests/RestApiTest.php`, `tests/SettingsTest.php`, `tests/SmokeTest.php`

Each test file needs the same two changes. Apply to every file:

- [ ] **Step 1: For each test file — update namespace declaration**

Find: `namespace AATG\Tests;`  
Replace: `namespace SAG\Tests;`

- [ ] **Step 2: For each test file — update all global class references**

In each file, replace every occurrence of `\AATG_` with `\SAG_`. The affected class names are:
- `\AATG_Plugin` → `\SAG_Plugin`
- `\AATG_REST_API` → `\SAG_REST_API`
- `\AATG_Settings` → `\SAG_Settings`
- `\AATG_Media` → `\SAG_Media`
- `\AATG_Generator` → `\SAG_Generator`
- `\AATG_OpenAI` → `\SAG_OpenAI`
- `\AATG_Image` → `\SAG_Image`
- `\AATG_AI_Provider` → `\SAG_AI_Provider`

- [ ] **Step 3: For each test file — update any WP error codes referenced in assertions**

Search each test file for strings like `'aatg_` and replace with `'sag_`. Example:
- `'aatg_image_unreadable'` → `'sag_image_unreadable'`
- `'aatg_invalid_image'` → `'sag_invalid_image'`
- `'aatg_no_api_key'` → `'sag_no_api_key'`
- `'aatg_missing_param'` → `'sag_missing_param'`
(Check each file — not all test files reference error codes)

---

### Task 14: Run tests — verify Group A

**Files:** none (read-only verification)

- [ ] **Step 1: Run the full test suite**

```bash
./vendor/bin/phpunit
```

Expected: `OK (26 tests, ...)`  
If tests fail, read the error carefully:
- `Class 'SAG_Foo' not found` → a class rename was missed in includes/ or bootstrap
- `namespace SAG\Tests` error → TestCase.php or a test file still has old namespace
- Assertion failures → a WP error code string in a test still uses `aatg_`

- [ ] **Step 2: Commit Group A**

```bash
git add includes/ tests/
git commit -m "refactor(Group-A): rename PHP classes and tests AATG_ → SAG_"
```

---

## Group B — Admin, Entry, JS, Assets, Metadata

### Task 15: Rename + update `admin/class-aatg-admin.php`

**Files:**
- Rename: `admin/class-aatg-admin.php` → `admin/class-sag-admin.php`

- [ ] **Step 1: git mv**

```bash
git mv admin/class-aatg-admin.php admin/class-sag-admin.php
```

- [ ] **Step 2: Update @package**

Find: `@package AI_Alt_Text_Generator`  
Replace: `@package Smart_Alt_Generator`

- [ ] **Step 3: Rename the class**

Find: `class AATG_Admin {`  
Replace: `class SAG_Admin {`

- [ ] **Step 4: Update text domain in menu registrations**

Find: `__( 'AI Alt Text', 'ai-alt-text-generator' ),` (first occurrence — settings page title)  
Replace: `__( 'AI Alt Text', 'smart-alt-generator' ),`

Find: `__( 'AI Alt Text', 'ai-alt-text-generator' ),` (second occurrence — settings menu label)  
Replace: `__( 'AI Alt Text', 'smart-alt-generator' ),`

Find: `__( 'Bulk Alt Text', 'ai-alt-text-generator' ),` (first — bulk page title)  
Replace: `__( 'Bulk Alt Text', 'smart-alt-generator' ),`

Find: `__( 'Bulk Alt Text', 'ai-alt-text-generator' ),` (second — bulk menu label)  
Replace: `__( 'Bulk Alt Text', 'smart-alt-generator' ),`

- [ ] **Step 5: Update menu page slugs**

Find: `'aatg-settings',`  
Replace: `'sag-settings',`

Find: `'aatg-bulk',`  
Replace: `'sag-bulk',`

- [ ] **Step 6: Update the hook check in `enqueue_assets()`**

Find: `if ( 'media_page_aatg-bulk' === $hook ) {`  
Replace: `if ( 'media_page_sag-bulk' === $hook ) {`

- [ ] **Step 7: Update script/style handles and asset filenames**

Find: `wp_enqueue_style( 'aatg-admin', AATG_PLUGIN_URL . 'admin/css/aatg-admin.css',`  
Replace: `wp_enqueue_style( 'sag-admin', SAG_PLUGIN_URL . 'admin/css/sag-admin.css',`

Find: `wp_enqueue_script( 'aatg-bulk', AATG_PLUGIN_URL . 'admin/js/aatg-bulk.js',`  
Replace: `wp_enqueue_script( 'sag-bulk', SAG_PLUGIN_URL . 'admin/js/sag-bulk.js',`

Find: `wp_enqueue_script( 'aatg-media', AATG_PLUGIN_URL . 'admin/js/aatg-media.js',`  
Replace: `wp_enqueue_script( 'sag-media', SAG_PLUGIN_URL . 'admin/js/sag-media.js',`

- [ ] **Step 8: Update block editor script handle and asset path constant**

Find: `'aatg-block-editor',`  
Replace: `'sag-block-editor',`

Find: `$asset_file = AATG_PLUGIN_DIR . 'build/index.asset.php';`  
Replace: `$asset_file = SAG_PLUGIN_DIR . 'build/index.asset.php';`

Find: `AATG_PLUGIN_URL . 'build/index.js',`  
Replace: `SAG_PLUGIN_URL . 'build/index.js',`

Find: `$asset['version'] . '-' . AATG_VERSION,`  
Replace: `$asset['version'] . '-' . SAG_VERSION,`

- [ ] **Step 9: Replace remaining `AATG_VERSION` and `AATG_PLUGIN_URL` in `enqueue_assets()`**

After the edits above, search the file for any remaining `AATG_` references:

```bash
grep -n "AATG_" admin/class-sag-admin.php
```

Replace every remaining occurrence (will be `AATG_VERSION` in the `wp_enqueue_style` and `wp_enqueue_script` calls and `AATG_PLUGIN_URL` if any were missed):
- `AATG_VERSION` → `SAG_VERSION`
- `AATG_PLUGIN_URL` → `SAG_PLUGIN_URL`
- `AATG_PLUGIN_DIR` → `SAG_PLUGIN_DIR`

---

### Task 16: Update admin views

**Files:**
- Modify: `admin/views/settings-page.php`
- Modify: `admin/views/bulk-page.php`

#### settings-page.php

- [ ] **Step 1: Update @package**

Find: `@package AI_Alt_Text_Generator`  
Replace: `@package Smart_Alt_Generator`

- [ ] **Step 2: Update local variable name and function_exists check**

Find: `$aatg_has_connector = function_exists( 'wp_ai_client' );`  
Replace: `$sag_has_connector = function_exists( 'wp_ai_client' );`

Find: `if ( $aatg_has_connector ) :` (both occurrences — opening + the NOT check)  
Replace first: `if ( $sag_has_connector ) :`  
Replace second: `if ( ! $sag_has_connector ) :`

- [ ] **Step 3: Update page title text domain**

Find: `esc_html_e( 'AI Alt Text Generator', 'ai-alt-text-generator' )`  
Replace: `esc_html_e( 'Smart Alt Generator', 'smart-alt-generator' )`

- [ ] **Step 4: Update settings_fields group**

Find: `settings_fields( 'aatg_settings' )`  
Replace: `settings_fields( 'sag_settings' )`

- [ ] **Step 5: Update all option names (id, name, get_option)**

Find: `id="aatg_openai_api_key" name="aatg_openai_api_key"`  
Replace: `id="sag_openai_api_key" name="sag_openai_api_key"`

Find: `get_option( 'aatg_openai_api_key', '' )`  
Replace: `get_option( 'sag_openai_api_key', '' )`

Find: `for="aatg_openai_api_key"`  
Replace: `for="sag_openai_api_key"`

Find: `$aatg_model = get_option( 'aatg_model', 'gpt-4o-mini' );`  
Replace: `$sag_model = get_option( 'sag_model', 'gpt-4o-mini' );`

Find: `id="aatg_model" name="aatg_model"`  
Replace: `id="sag_model" name="sag_model"`

Find: `selected( $aatg_model, 'gpt-4o-mini' )`  
Replace: `selected( $sag_model, 'gpt-4o-mini' )`

Find: `selected( $aatg_model, 'gpt-4o' )`  
Replace: `selected( $sag_model, 'gpt-4o' )`

Find: `for="aatg_model"`  
Replace: `for="sag_model"`

Find: `id="aatg_language" name="aatg_language"`  
Replace: `id="sag_language" name="sag_language"`

Find: `get_option( 'aatg_language', 'auto' )`  
Replace: `get_option( 'sag_language', 'auto' )`

Find: `for="aatg_language"`  
Replace: `for="sag_language"`

Find: `name="aatg_auto_generate"`  
Replace: `name="sag_auto_generate"`

Find: `get_option( 'aatg_auto_generate', false )`  
Replace: `get_option( 'sag_auto_generate', false )`

- [ ] **Step 6: Update remaining text domains**

Replace all remaining `'ai-alt-text-generator'` text domain strings with `'smart-alt-generator'` in this file.

#### bulk-page.php

- [ ] **Step 7: Update @package**

Find: `@package AI_Alt_Text_Generator`  
Replace: `@package Smart_Alt_Generator`

- [ ] **Step 8: Update local variable names**

Find: `$aatg_query = new WP_Query(`  
Replace: `$sag_query = new WP_Query(`

Find: `$aatg_ids = wp_list_pluck( $aatg_query->posts, 'ID' );`  
Replace: `$sag_ids = wp_list_pluck( $sag_query->posts, 'ID' );`

Find all occurrences of `$aatg_ids` → replace with `$sag_ids` (3 occurrences: `count( $aatg_ids )` twice + `! empty( $aatg_ids )`)

Find: `wp_json_encode( $aatg_ids )`  
Replace: `wp_json_encode( $sag_ids )`

- [ ] **Step 9: Update CSS class names and JS element IDs**

Find: `<div class="wrap aatg-bulk">`  
Replace: `<div class="wrap sag-bulk">`

Find: `id="aatg-start"`  
Replace: `id="sag-start"`

Find: `<div class="aatg-progress-wrap"`  
Replace: `<div class="sag-progress-wrap"`

Find: `<div class="aatg-progress-bar"><span id="aatg-progress-fill"></span></div>`  
Replace: `<div class="sag-progress-bar"><span id="sag-progress-fill"></span></div>`

Find: `<p id="aatg-progress-text"></p>`  
Replace: `<p id="sag-progress-text"></p>`

Find: `<ul id="aatg-log" class="aatg-log"></ul>`  
Replace: `<ul id="sag-log" class="sag-log"></ul>`

- [ ] **Step 10: Update text domain strings in bulk-page.php**

Replace all `'ai-alt-text-generator'` with `'smart-alt-generator'` in this file.

---

### Task 17: Update entry file and rename it

**Files:**
- Modify then rename: `ai-alt-text-generator.php` → `smart-alt-generator.php`

- [ ] **Step 1: Update plugin header**

Find:
```php
 * Plugin Name:       AI Alt Text Generator
 * Plugin URI:        https://nickgranados.com/plugins/ai-alt-text-generator
```
Replace:
```php
 * Plugin Name:       Smart Alt Generator
 * Plugin URI:        https://nickgranados.com/plugins/smart-alt-generator
```

Find: `* Text Domain:       ai-alt-text-generator`  
Replace: `* Text Domain:       smart-alt-generator`

Find: `* @package AI_Alt_Text_Generator`  
Replace: `* @package Smart_Alt_Generator`

- [ ] **Step 2: Update constants**

Find:
```php
define( 'AATG_VERSION', '1.1.1' );
define( 'AATG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AATG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AATG_PLUGIN_FILE', __FILE__ );
```
Replace:
```php
define( 'SAG_VERSION', '1.1.1' );
define( 'SAG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SAG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SAG_PLUGIN_FILE', __FILE__ );
```

- [ ] **Step 3: Update require_once and class reference**

Find: `require_once AATG_PLUGIN_DIR . 'includes/class-aatg-plugin.php';`  
Replace: `require_once SAG_PLUGIN_DIR . 'includes/class-sag-plugin.php';`

Find: `register_activation_hook( __FILE__, array( 'AATG_Plugin', 'activate' ) );`  
Replace: `register_activation_hook( __FILE__, array( 'SAG_Plugin', 'activate' ) );`

Find: `register_deactivation_hook( __FILE__, array( 'AATG_Plugin', 'deactivate' ) );`  
Replace: `register_deactivation_hook( __FILE__, array( 'SAG_Plugin', 'deactivate' ) );`

Find: `AATG_Plugin::get_instance();`  
Replace: `SAG_Plugin::get_instance();`

- [ ] **Step 4: Rename the file**

```bash
git mv ai-alt-text-generator.php smart-alt-generator.php
```

---

### Task 18: Update `uninstall.php`

**Files:**
- Modify: `uninstall.php`

- [ ] **Step 1: Update @package**

Find: `@package AI_Alt_Text_Generator`  
Replace: `@package Smart_Alt_Generator`

- [ ] **Step 2: Update option names**

Find:
```php
delete_option( 'aatg_openai_api_key' );
delete_option( 'aatg_model' );
delete_option( 'aatg_language' );
delete_option( 'aatg_auto_generate' );
```
Replace:
```php
delete_option( 'sag_openai_api_key' );
delete_option( 'sag_model' );
delete_option( 'sag_language' );
delete_option( 'sag_auto_generate' );
```

---

### Task 19: Update `src/block-editor/index.js`

**Files:**
- Modify: `src/block-editor/index.js`

- [ ] **Step 1: Update text domain in all `__()` calls**

Replace all occurrences of `'ai-alt-text-generator'` with `'smart-alt-generator'`. Affected lines:
- `__( 'Generation failed.', 'ai-alt-text-generator' )`
- `__( 'AI Alt Text', 'ai-alt-text-generator' )`
- `__( 'Generating…', 'ai-alt-text-generator' )`
- `__( '⚡ Generate with AI', 'ai-alt-text-generator' )`

- [ ] **Step 2: Update REST API path**

Find: `path: '/ai-alt-text/v1/generate',`  
Replace: `path: '/smart-alt/v1/generate',`

- [ ] **Step 3: Update the block editor filter handle**

Find: `'ai-alt-text-generator/with-image-controls',`  
Replace: `'smart-alt-generator/with-image-controls',`

---

### Task 20: Update admin JS files

**Files:**
- Modify: `admin/js/aatg-bulk.js`
- Modify: `admin/js/aatg-media.js`

#### aatg-bulk.js

- [ ] **Step 1: Update REST path**

Find: `path: '/ai-alt-text/v1/generate',`  
Replace: `path: '/smart-alt/v1/generate',`

- [ ] **Step 2: Update DOM element IDs to match new bulk-page.php**

Find: `document.getElementById( 'aatg-start' )`  
Replace: `document.getElementById( 'sag-start' )`

Find: `document.querySelector( '.aatg-progress-wrap' )`  
Replace: `document.querySelector( '.sag-progress-wrap' )`

Find: `document.getElementById( 'aatg-progress-fill' )`  
Replace: `document.getElementById( 'sag-progress-fill' )`

Find: `document.getElementById( 'aatg-progress-text' )`  
Replace: `document.getElementById( 'sag-progress-text' )`

Find: `document.getElementById( 'aatg-log' )`  
Replace: `document.getElementById( 'sag-log' )`

- [ ] **Step 3: Update CSS class names in `addLog()`**

Find: `li.className = ok ? 'aatg-ok' : 'aatg-err';`  
Replace: `li.className = ok ? 'sag-ok' : 'sag-err';`

#### aatg-media.js

- [ ] **Step 4: Update REST path**

Find: `path: '/ai-alt-text/v1/generate',`  
Replace: `path: '/smart-alt/v1/generate',`

- [ ] **Step 5: Update button CSS class selector**

Find: `const btn = event.target.closest( '.aatg-generate-btn' );`  
Replace: `const btn = event.target.closest( '.sag-generate-btn' );`

- [ ] **Step 6: Update message box CSS class**

Find: `box = btn.parentNode.querySelector( '.aatg-message' );`  
Replace: `box = btn.parentNode.querySelector( '.sag-message' );`

Find: `box.className = 'aatg-message';`  
Replace: `box.className = 'sag-message';`

---

### Task 21: Rename admin CSS and JS static files

**Files:**
- Rename: `admin/css/aatg-admin.css` → `admin/css/sag-admin.css`
- Rename: `admin/js/aatg-bulk.js` → `admin/js/sag-bulk.js`
- Rename: `admin/js/aatg-media.js` → `admin/js/sag-media.js`

- [ ] **Step 1: git mv static assets**

```bash
git mv admin/css/aatg-admin.css admin/css/sag-admin.css
git mv admin/js/aatg-bulk.js    admin/js/sag-bulk.js
git mv admin/js/aatg-media.js   admin/js/sag-media.js
```

- [ ] **Step 2: Update CSS class selectors inside `admin/css/sag-admin.css`**

Open the file and replace all occurrences of `aatg-` with `sag-` in selectors (e.g. `.aatg-ok`, `.aatg-err`, `.aatg-log`, `.aatg-bulk`, `.aatg-progress-wrap`, `.aatg-progress-bar`, `.aatg-message`).

---

### Task 22: Rebuild block editor JS

**Files:**
- `build/index.js` and `build/index.asset.php` (auto-generated)

- [ ] **Step 1: Install JS dependencies (if needed)**

```bash
npm install
```

- [ ] **Step 2: Build**

```bash
npm run build
```

Expected: `build/index.js` and `build/index.asset.php` regenerated with updated text domain and REST path from `src/block-editor/index.js`.

- [ ] **Step 3: Run tests to confirm nothing broke**

```bash
./vendor/bin/phpunit
```

Expected: `OK (26 tests, ...)`

---

### Task 23: Update metadata files

**Files:**
- Modify: `readme.txt`
- Modify: `package.json`
- Rename: `languages/ai-alt-text-generator.pot` → `languages/smart-alt-generator.pot`

- [ ] **Step 1: Update readme.txt header**

Find: `=== AI Alt Text Generator ===`  
Replace: `=== Smart Alt Generator ===`

Find: `Stable tag: 1.0.1`  
Replace: `Stable tag: 1.1.1`

(Version should match the plugin header — it was v1.1.1 in the PHP file.)

- [ ] **Step 2: Update package.json name**

Find: `"name": "ai-alt-text-generator",`  
Replace: `"name": "smart-alt-generator",`

- [ ] **Step 3: Rename .pot file**

```bash
git mv languages/ai-alt-text-generator.pot languages/smart-alt-generator.pot
```

- [ ] **Step 4: Update .pot file header**

In `languages/smart-alt-generator.pot`, find:
```
"Project-Id-Version: AI Alt Text Generator 1.0.0\n"
```
Replace:
```
"Project-Id-Version: Smart Alt Generator 1.1.1\n"
```

---

### Task 24: Final verification + commit Group B

- [ ] **Step 1: Verify no `aatg` references remain in source files**

```bash
grep -ri "aatg" . --include="*.php" --include="*.js" --include="*.json" --include="*.txt" --include="*.pot" --exclude-dir=.git --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=build
```

Expected: zero matches. If any remain, fix them before committing.

- [ ] **Step 2: Run tests one final time**

```bash
./vendor/bin/phpunit
```

Expected: `OK (26 tests, ...)`

- [ ] **Step 3: Stage and commit all Group B changes**

```bash
git add admin/ src/ build/ uninstall.php smart-alt-generator.php readme.txt package.json languages/
git commit -m "refactor(Group-B): rename plugin slug, entry file, admin, JS, assets to smart-alt-generator"
```

---

### Task 25: Rename GitHub repo and update remote URL

- [ ] **Step 1: Rename repo on GitHub**

Go to: https://github.com/internick2017/ai-alt-text-generator/settings  
Under "Repository name", change to `smart-alt-generator`, click "Rename".

- [ ] **Step 2: Update remote URL locally**

```bash
git remote set-url origin https://github.com/internick2017/smart-alt-generator.git
```

- [ ] **Step 3: Verify remote**

```bash
git remote -v
```

Expected:
```
origin  https://github.com/internick2017/smart-alt-generator.git (fetch)
origin  https://github.com/internick2017/smart-alt-generator.git (push)
```

- [ ] **Step 4: Push**

```bash
git push origin master
```

---

## Post-Rename Checklist

- [ ] Plugin activates without PHP errors in the Docker WP test site (http://localhost:8910/wp-admin)
- [ ] Settings page loads at Settings → AI Alt Text (slug `sag-settings`)
- [ ] Bulk page loads at Media → Bulk Alt Text (slug `sag-bulk`)
- [ ] Gutenberg block panel "AI Alt Text" appears on image block
- [ ] REST endpoint responds at `/wp-json/smart-alt/v1/generate`
- [ ] Auto-generate option works on image upload
- [ ] Classic Media Library button `sag-generate-btn` works
- [ ] All 26 tests pass
