=== KO Gravity Forms Submit Label ===
Contributors: ko
Tags: gravity forms, shortcode, submit button
Requires at least: 6.7
Requires PHP: 7.4
Requires Plugins: gravityforms
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Change the submit label for one Gravity Forms shortcode embed without changing the saved form.

== Description ==

Add submit_text to the standard shortcode:

[gravityform id="9" title="false" description="false" ajax="true" submit_text="Send Request"]

Omit submit_text or set it to an empty string to retain the normal label. Whitespace-only labels also preserve the original output. There is no settings screen.

The plugin edits only that shortcode's rendered markup. It never updates a Gravity Forms form definition, field object, entry, or global per-form label. Other embeds retain their own labels. Blocks and direct gravity_form() calls are not overridden.

Native input and button submit controls retain their element types, IDs, classes, event handlers and other behavior attributes. Image buttons retain their images and receive an updated alternative text; text drawn into an image cannot be changed. Existing explicit accessible names are updated to match the override. Previous, Next, and Save and Continue controls are unchanged.

Labels are plain text, with HTML removed and output escaped. HTML entities in the shortcode are decoded before sanitization. Invalid labels or labels longer than 2,000 bytes are ignored. The string "0" is a valid label.

== Installation ==

1. Install and activate Gravity Forms on WordPress 6.7 or newer with PHP 7.4 or newer.
2. Upload the plugin ZIP through Plugins > Add New > Upload Plugin and activate it.
3. Add submit_text="Your Label" to the desired Gravity Forms shortcode.
4. Check validation, submission and any multipage flow on your installation.

== Frequently Asked Questions ==

= Does this change the form's saved submit button setting? =

No. Removing submit_text or deactivating this plugin restores normal Gravity Forms output. No uninstall cleanup is required because the plugin saves no settings or form data.

= Does AJAX work? =

Postback and legacy iframe AJAX render through the original shortcode again. For the newer Gravity Forms AJAX method introduced in 2.9, a signed hidden input carries the embed label and the plugin updates only the matching submission response's form or page markup. Validation data and confirmation responses are unchanged. No frontend JavaScript is added.

The signed value is cosmetic state, not an authorization token. It has no expiry so cached forms continue to work. Rotating WordPress salts requires clearing cached pages to regenerate it. Third-party rendering that bypasses the standard shortcode and AJAX result hooks is outside this integration.

= Can I embed the same form twice on one page? =

The plugin isolates label output, but Gravity Forms does not support repeating the same form ID on one page. Duplicate the form to obtain separate IDs if both must appear on the same page. Using the same form on separate pages is supported by the label design.

https://docs.gravityforms.com/embedding-one-form-multiple-times-per-page/

= What was tested? =

Isolated automated checks use actual WordPress 6.7.4 and 7.1.3 HTML parsing, sanitization and shortcode hooks on PHP 8.5.11. Fixtures cover input/button/image markup, multiple labels and forms, omitted and empty labels, escaping, behavior attributes, tampered transport, validation response replacement, and multipage response replacement.

The Gravity Forms hook flow was inspected in a local 2.10.3 source snapshot. Current native button markup is covered with fixtures. No installed site version, browser submission, CAPTCHA, payment flow, add-on integration or live AJAX request has been verified. Complete those applicable checks after installation in a testing environment.

== Changelog ==

= 1.0.0 =
* Initial release of the embed-specific submit_text shortcode attribute.
* Preserve native submit markup and the form's saved settings.
* Carry signed label state through newer AJAX validation and page responses.
