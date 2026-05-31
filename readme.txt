=== Smart Alt Generator ===
Contributors: internick2017
Tags: alt text, accessibility, seo, ai, openai
Requires at least: 6.4
Tested up to: 7.0
Stable tag: 1.1.1
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically generate descriptive alt text for images using AI. Supports WordPress 7.0 AI Connectors and OpenAI for older versions.

== Description ==

AI Alt Text Generator uses artificial intelligence to create descriptive,
SEO-friendly alt text for your WordPress images — improving accessibility
(WCAG) and search rankings.

Features:

* Native WordPress 7.0 AI Connectors support — no separate API key needed
* Bulk generator — process every image missing alt text at once
* Auto-generate on upload
* REST API endpoint for external tools and automation
* Generates alt text in your site's language

On WordPress 6.x the plugin uses your OpenAI API key. On WordPress 7.0+ it
uses your configured AI Connectors automatically.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/smart-alt-generator`.
2. Activate it through the Plugins screen.
3. (WP 6.x only) Go to Settings -> AI Alt Text and enter your OpenAI API key.
4. Go to Media -> Bulk Alt Text to process existing images.

== Frequently Asked Questions ==

= Do I need an OpenAI account? =
Only on WordPress 6.x. On 7.0+ the plugin uses your AI Connectors configuration.

= How much does generation cost? =
With gpt-4o-mini, roughly $0.001 per image.

== Changelog ==

= 1.0.1 =
* Images are now sent to the AI as base64 data URIs instead of public URLs, so
  generation works on localhost, behind authentication, and on firewalled sites.
* The Media Library button now shows the result (or the error message) inline.

= 1.0.0 =
* Initial release: settings, media button, bulk generator, REST API.

== Upgrade Notice ==

= 1.0.1 =
Improves reliability: images are sent directly to the AI, working on local and
private sites. Errors are now shown clearly in the Media Library.

= 1.0.0 =
Initial release.
