=== Internick - Smart Alt Generator ===
Contributors: internick2017
Tags: alt text, accessibility, wcag, seo, ai
Requires at least: 6.4
Tested up to: 7.0
Stable tag: 1.2.1
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically generate descriptive alt text for images using AI. Supports WordPress 7.0 AI Connectors and OpenAI for older versions.

== Description ==

Internick - Smart Alt Generator uses artificial intelligence to create descriptive,
SEO-friendly alt text for your WordPress images — improving accessibility
(WCAG) and search rankings.

Features:

* Alt Text Audit: scan your whole library for missing, empty, duplicate, overly long, or placeholder alt text, with a health score and per-image generate, edit, or ignore actions
* Native WordPress 7.0 AI Connectors support — no separate API key needed
* Bulk generator — process every image missing alt text at once
* Auto-generate on upload
* REST API endpoint for external tools and automation
* Generates alt text in your site's language

On WordPress 6.x the plugin uses your OpenAI API key. On WordPress 7.0+ it
uses your configured AI Connectors automatically.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/internick-smart-alt-generator`.
2. Activate it through the Plugins screen.
3. (WP 6.x only) Go to Settings -> AI Alt Text and enter your OpenAI API key.
4. Go to Media -> Bulk Alt Text to process existing images.

== Frequently Asked Questions ==

= Do I need an OpenAI account? =
Only on WordPress 6.x. On 7.0+ the plugin uses your AI Connectors configuration.

= How much does generation cost? =
With gpt-4o-mini, roughly $0.001 per image.

== Screenshots ==

1. Settings — configure your AI provider, model, language, and auto-generate on upload.
2. Bulk generator — process every image missing alt text with live progress and a per-image result log.
3. Alt Text Audit — score your media library's alt text, see every issue flagged, and fix each one with AI, manual edit, or ignore.

== External services ==

This plugin connects to the OpenAI API to generate alt text for your images. It is
required for AI generation on WordPress 6.x sites that do not have the native
WordPress 7.0 AI Connectors configured.

When you click "Generate" (in the block editor, the Media Library, or the Bulk
generator) or "Test Connection" on the settings page, the plugin sends the image
(as a base64 data URI) and a short text instruction to OpenAI's Chat Completions
endpoint (https://api.openai.com/v1/chat/completions), authenticated with the API
key you configure. The "Test Connection" button sends only a minimal "Hi" message
with no image, to verify the key works. Nothing is sent unless you trigger one of
these actions.

This service is provided by OpenAI. By using the OpenAI path you agree to their terms:

* Terms of Use: https://openai.com/policies/terms-of-use
* Privacy Policy: https://openai.com/policies/privacy-policy

On WordPress 7.0+ with AI Connectors configured, generation uses your site's
configured AI provider through WordPress core instead, and this plugin makes no
direct external calls.

== Development ==

The full, unminified source code — including the React/JavaScript sources under
`src/` and the build tooling — is publicly available at:

https://github.com/internick2017/smart-alt-generator

Build the compiled assets in `build/` with `npm install && npm run build`.

== Changelog ==

= 1.2.1 =
* New: the plugin now speaks Spanish (es_ES) and Brazilian Portuguese (pt_BR) — admin pages, editor button, and notices.
* New: a small, dismissible request for a review after 10 successful generations (only on the plugin's own screens, never elsewhere).

= 1.2.0 =
* New: Alt Text Audit dashboard (Media -> Alt Text Audit) that scores your
  library's alt text and flags missing, empty, duplicate, too-long, and
  placeholder values, with per-image generate / edit / ignore actions and a
  filter-aware "Generate all shown" bulk action.

= 1.1.3 =
* Fixed the Settings and Bulk Alt Text admin screens showing up blank: the script
  loader checked the wrong admin-page hook, so the React interface never loaded.

= 1.1.2 =
* Hardened REST API permissions: per-attachment capability check on generation,
  and the connection test is now restricted to administrators.
* Documented the OpenAI external service and the public source repository.
* Renamed internal code prefixes for uniqueness (fresh install — no migration needed).

= 1.1.1 =
* React-powered Settings page with live Test Connection button.
* React-powered Bulk Generator with pause/resume and per-image result log.
* Fixed WP 7.0 AI Connectors integration: uses the correct AiClient API
  (wp_ai_client() never existed; replaced with AiClient::prompt() fluent API).
* Plugin renamed to Internick - Smart Alt Generator (slug: internick-smart-alt-generator).

= 1.1.0 =
* AI Alt Text panel moved to the Content tab in the Gutenberg block editor.
* Block editor script loading fixed for WordPress < 6.6.
* REST API endpoints for settings (GET/POST /smart-alt/v1/settings) and
  connection test (GET /smart-alt/v1/test).
* Alt text field now fills correctly in the Media Library modal.

= 1.0.1 =
* Images are now sent to the AI as base64 data URIs instead of public URLs, so
  generation works on localhost, behind authentication, and on firewalled sites.
* The Media Library button now shows the result (or the error message) inline.

= 1.0.0 =
* Initial release: settings, media button, bulk generator, REST API.

== Upgrade Notice ==

= 1.2.0 =
Adds the Alt Text Audit dashboard to review and fix your whole library's alt text.

= 1.1.3 =
Fixes the Settings and Bulk Alt Text screens showing up blank.

= 1.1.1 =
Adds a modern React admin UI, pause/resume bulk generation, and fixes the
WordPress 7.0 AI Connectors integration.

= 1.1.0 =
Improves Gutenberg block editor panel and adds REST API settings endpoints.

= 1.0.1 =
Improves reliability: images are sent directly to the AI, working on local and
private sites. Errors are now shown clearly in the Media Library.

= 1.0.0 =
Initial release.
