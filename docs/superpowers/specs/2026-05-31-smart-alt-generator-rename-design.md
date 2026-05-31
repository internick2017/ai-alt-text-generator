# Rename Design: `ai-alt-text-generator` → `smart-alt-generator`

**Date:** 2026-05-31  
**Status:** Approved  
**Scope:** Full plugin rename — slug, text domain, PHP prefix, DB options, REST namespace, JS handles, GitHub repo.

---

## Context

The plugin `ai-alt-text-generator` (v1.1.1) conflicts with an existing plugin on WordPress.org (`ai-alt-text-generator` v2.1.2 by a different author). A slug rename is required before submitting to the WordPress.org directory.

The new slug `smart-alt-generator` was chosen for being clean, generic, and unlikely to conflict.

---

## Rename Map

### Identity / WP.org

| Field | Before | After |
|-------|--------|-------|
| Plugin folder | `ai-alt-text-generator/` | `smart-alt-generator/` |
| Entry file | `ai-alt-text-generator.php` | `smart-alt-generator.php` |
| Plugin Name (header) | `AI Alt Text Generator` | `Smart Alt Generator` |
| Text Domain | `ai-alt-text-generator` | `smart-alt-generator` |
| Plugin URI | `.../plugins/ai-alt-text-generator` | `.../plugins/smart-alt-generator` |
| `readme.txt` header | `=== AI Alt Text Generator ===` | `=== Smart Alt Generator ===` |
| Languages file | `ai-alt-text-generator.pot` | `smart-alt-generator.pot` |

### PHP — Constants & Classes

| Before | After |
|--------|-------|
| `AATG_VERSION` | `SAG_VERSION` |
| `AATG_PLUGIN_DIR` | `SAG_PLUGIN_DIR` |
| `AATG_PLUGIN_URL` | `SAG_PLUGIN_URL` |
| `AATG_PLUGIN_FILE` | `SAG_PLUGIN_FILE` |
| `AATG_Plugin` | `SAG_Plugin` |
| `AATG_Settings` | `SAG_Settings` |
| `AATG_Admin` | `SAG_Admin` |
| `AATG_REST_API` | `SAG_REST_API` |
| `AATG_Media` | `SAG_Media` |
| `AATG_Generator` | `SAG_Generator` |
| `AATG_OpenAI` | `SAG_OpenAI` |
| `AATG_Image` | `SAG_Image` |
| `AATG_AI_Provider` | `SAG_AI_Provider` |
| `@package AI_Alt_Text_Generator` | `@package Smart_Alt_Generator` |

### WordPress Options (DB)

| Before | After |
|--------|-------|
| `aatg_openai_api_key` | `sag_openai_api_key` |
| `aatg_model` | `sag_model` |
| `aatg_language` | `sag_language` |
| `aatg_auto_generate` | `sag_auto_generate` |

No migration needed — no production installs exist. Fresh activation creates options with new names.

### WordPress Handles & Slugs

| Before | After |
|--------|-------|
| Menu slug `aatg-settings` | `sag-settings` |
| Menu slug `aatg-bulk` | `sag-bulk` |
| Hook check `media_page_aatg-bulk` | `media_page_sag-bulk` |
| Script handle `aatg-admin` | `sag-admin` |
| Script handle `aatg-bulk` | `sag-bulk` |
| Script handle `aatg-media` | `sag-media` |
| Script handle `aatg-block-editor` | `sag-block-editor` |
| Style handle `aatg-admin` | `sag-admin` |
| Settings group `aatg_settings` | `sag_settings` |
| WP error code `aatg_missing_param` | `sag_missing_param` |

### REST API

| Before | After |
|--------|-------|
| PHP `REST_NAMESPACE` | `'smart-alt/v1'` |
| JS `apiFetch` path | `'/smart-alt/v1/generate'` |

### Block Editor (JS)

| Before | After |
|--------|-------|
| Filter handle | `smart-alt-generator/with-image-controls` |
| HOC name | `withAltTextGenerator` (keep — internal) |
| i18n text domain in `__()` | `'smart-alt-generator'` |

### package.json

| Before | After |
|--------|-------|
| `"name"` | `"smart-alt-generator"` |

### GitHub

- Repo renamed from `ai-alt-text-generator` → `smart-alt-generator` (done manually on GitHub.com)
- Remote URL updated locally after rename

---

## Files Changed

| File | Changes |
|------|---------|
| `ai-alt-text-generator.php` → `smart-alt-generator.php` | Rename + update header + constants + text domain |
| `includes/class-aatg-plugin.php` → `class-sag-plugin.php` | Rename + class + options + class_exists checks |
| `includes/class-aatg-settings.php` → `class-sag-settings.php` | Rename + class + option names + settings group |
| `admin/class-aatg-admin.php` → `admin/class-sag-admin.php` | Rename + class + menu slugs + script handles + hook check |
| `includes/class-aatg-rest-api.php` → `includes/class-sag-rest-api.php` | Rename + class + REST namespace |
| `includes/class-aatg-media.php` → `includes/class-sag-media.php` | Rename + class |
| `includes/class-aatg-generator.php` → `includes/class-sag-generator.php` | Rename + class |
| `includes/class-aatg-openai.php` → `includes/class-sag-openai.php` | Rename + class |
| `includes/class-aatg-image.php` → `includes/class-sag-image.php` | Rename + class |
| `includes/class-aatg-ai-provider.php` → `includes/class-sag-ai-provider.php` | Rename + class |
| `admin/css/aatg-admin.css` → `admin/css/sag-admin.css` | Rename file only |
| `admin/js/aatg-bulk.js` | Rename file only |
| `admin/js/aatg-media.js` | Rename file only |
| `src/block-editor/index.js` | Update text domain + REST path + filter handle |
| `uninstall.php` | Update option names + class references |
| `readme.txt` | Update plugin name header |
| `languages/ai-alt-text-generator.pot` → `smart-alt-generator.pot` | Rename + update header |
| `package.json` | Update `"name"` field |
| All test files (`tests/*.php`) | Update class references: `AATG_*` → `SAG_*` |
| `tests/bootstrap.php` | Update require_once paths for renamed class files |

---

## Execution Order

1. Rename PHP class files (`includes/` and `admin/`)
2. Update entry file (`smart-alt-generator.php`) — references all class files
3. Update all PHP class internals (constants, class names, option names, handles)
4. Update JS files (`src/block-editor/index.js`, `admin/js/*.js`)
5. Rename static assets (`css/`, `js/` files)
6. Update test files and bootstrap
7. Rename entry file itself
8. Update `readme.txt`, `package.json`, `.pot` file
9. Rebuild JS (`npm run build`)
10. Run test suite — all 26 tests must pass
11. Rename GitHub repo + update remote URL
12. Commit with message `refactor: rename plugin to smart-alt-generator (SAG_)`

---

## Success Criteria

- `./vendor/bin/phpunit` passes all 26 tests with zero failures
- Admin pages load correctly (Settings + Bulk)
- Gutenberg block panel appears and generates alt text via REST
- No references to `aatg`, `AATG`, or `ai-alt-text-generator` remain in source files (except git history)
- GitHub remote points to renamed repo
