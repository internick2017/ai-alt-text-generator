=== AI Alt Text Generator for Images – Bulk Alt Text & Accessibility Audit ===
Contributors: internick2017
Donate link: https://ko-fi.com/nickgranados
Tags: alt text, alt text generator, image alt text, alt tag, accessibility
Requires at least: 6.4
Tested up to: 7.1
Stable tag: 1.3.2
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate alt text for images with AI. Bulk generate alt text, audit your media library for missing alt tags, and improve accessibility and SEO.

== Description ==

**AI Alt Text Generator for Images** writes descriptive, SEO-friendly alt text for
your WordPress images automatically. Instead of typing an alt tag for every image
by hand, you can generate alt text for one image, for a whole page, or for your
entire media library at once.

Missing alt text hurts twice. It locks out visitors who use screen readers, which
is an accessibility failure under WCAG, and it hides your images from Google Image
search. Most WordPress media libraries have hundreds of images with no alt text at
all, because writing them by hand is slow, repetitive work that nobody enjoys.

This plugin does that work for you.

= Alt Text Audit =

Scan your entire media library and get a health score for its alt text. The audit
flags every image with a problem:

* Missing alt text
* Empty alt attributes
* Duplicate alt text reused across images
* Alt text that is too long for screen readers
* Placeholder junk left behind by other tools

Every flagged image can be fixed right there: generate new alt text with AI, edit
it by hand, or mark it as decorative and ignore it. You can filter the list and
generate alt text for everything currently shown in one action.

= Bulk Alt Text Generator =

Process your whole media library in one run. The bulk generator works through
every image missing alt text in batches of 100, with a live progress counter
across the entire run, pause and resume, and a per-image log so you can see
exactly what was written and what failed. Before a large run starts, a
confirmation dialog tells you how many images will be processed.

= Generate Alt Text Anywhere You Work =

* **Block editor** – an AI Alt Text panel in the Content tab of the image block
* **Media Library** – a generate button right in the attachment details modal
* **Bulk generator** – for the whole library at once
* **On upload** – optionally generate alt text automatically for every new image
* **REST API** – an endpoint for external tools and your own automation

= Works in Your Language =

Unlike most alt text plugins, this one is not English-only. The admin screens, the
editor button and the notices are translated into **Spanish (es_ES)** and
**Brazilian Portuguese (pt_BR)**.

The generated alt text follows your site's language too, so a Spanish site gets
Spanish alt text and a Brazilian site gets Portuguese alt text, without any extra
configuration.

= Bring Your Own AI =

* On **WordPress 7.0 and later**, the plugin uses your site's native AI Connectors
  configuration. No separate API key and no extra account needed.
* On **WordPress 6.x**, the plugin uses your own OpenAI API key. Generation is
  billed to you directly by OpenAI, at roughly $0.001 per image with gpt-4o-mini.

There is no subscription, no per-image fee and no account with us. The plugin is
free and you pay only your own AI provider, if anything.

= Supported Image Formats =

JPEG, PNG and WebP are analysed directly. AVIF images are converted to JPEG
automatically before analysis instead of being rejected.

= Who This Is For =

* Site owners with a large media library and no alt text
* Agencies and freelancers doing accessibility or SEO cleanups for clients
* Bloggers and photographers who publish a lot of images
* Anyone working toward WCAG accessibility compliance

== Installation ==

1. Upload the plugin to `/wp-content/plugins/internick-smart-alt-generator`.
2. Activate it through the Plugins screen.
3. (WP 6.x only) Go to Settings -> AI Alt Text and enter your OpenAI API key.
4. Go to Media -> Bulk Alt Text to process existing images.

== Frequently Asked Questions ==

= Do I need an OpenAI account? =
Only on WordPress 6.x. On 7.0+ the plugin uses your AI Connectors configuration.

= How much does generation cost? =
With gpt-4o-mini, roughly $0.001 per image. There is no fee from this plugin; you
pay your AI provider directly.

= What languages does it support? =
The admin interface is available in English, Spanish (es_ES) and Brazilian
Portuguese (pt_BR). The generated alt text follows your site's language setting.

= Which image formats work? =
JPEG, PNG and WebP are analysed directly. AVIF images are converted to JPEG
automatically before being analysed.

= Can it process my whole media library at once? =
Yes. The bulk generator runs through the entire library in batches of 100, with a
progress counter, pause and resume, and a per-image result log.

= Is there a REST API? =
Yes. The plugin exposes REST endpoints so you can generate alt text and manage
settings from external tools or your own scripts.

= Is the source code available? =
Yes. The full unminified source, including the React sources and build tooling, is
on GitHub. See the Development section below.

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

= 1.3.2 =
* Documentation only: clearer plugin name, an expanded description covering the Alt Text Audit, bulk generation and language support, and more detailed FAQs. No functional changes.

= 1.3.1 =
* Plugin renamed to "AI Alt Text Generator for Images" so it is easier to find in the plugin directory. No functional changes.

= 1.3.0 =
* AVIF images are now supported: they are converted to JPEG before being sent for analysis, instead of being rejected. On servers that cannot read AVIF, the plugin explains the problem and suggests JPEG, PNG or WebP.
* Bulk Generator now processes your entire media library, in batches of 100, with a progress counter across the whole run.
* A confirmation dialog shows the total number of images (and that your API key will be used) before large runs start.
* Review request now appears after 5 generated alt texts (was 10) and is only dismissed when the server confirms it.
* Brazilian Portuguese plural rule corrected.

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

= 1.3.2 =
Documentation update only. Nothing to do on your side.

= 1.3.1 =
Name change only, to make the plugin easier to find. Nothing to do on your side.

= 1.3.0 =
The Bulk Generator now works through your entire media library instead of
stopping after the first 100 images.

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
