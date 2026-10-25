=== Sikorix Login reCAPTCHA ===
Contributors: sikoracollective
Tags: recaptcha, login, security, captcha, spam
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds Google reCAPTCHA v2 to the WordPress login page

== Description ==

Sikorix Login reCAPTCHA protects the WordPress login form with Google reCAPTCHA v2 ("I'm not a robot" Checkbox).

This plugin is an interface to Google's reCAPTCHA service. You must create your own reCAPTCHA v2 site credentials in Google's admin console and save them in the plugin settings. Google's script and verification API are used only after those credentials are saved.

Features:

* Adds the reCAPTCHA checkbox to the login page
* Verifies the response with Google during login authentication
* Settings page (**Settings → Sikorix Login reCAPTCHA**) for your site key (ID) and Secret Key
* Leaves login unchanged until both values are configured (avoids accidental lockouts)
* On delete, removes everything it stored—including settings—so nothing from the plugin remains

Create credentials here: https://www.google.com/recaptcha/admin

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory, or install the zip through **Plugins → Add New**.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Settings → Sikorix Login reCAPTCHA**.
4. Enter your Google reCAPTCHA v2 site key (ID) and Secret Key from https://www.google.com/recaptcha/admin
5. Save changes and open the login page to confirm the widget appears.

== Frequently Asked Questions ==

= Which reCAPTCHA version is supported? =

Google reCAPTCHA v2 ("I'm not a robot" Checkbox).

= What happens if I activate the plugin without values? =

Nothing changes on the login page until both the ID and Secret Key are saved. Google's script is not loaded until then.

= Where do I get my ID and Secret Key? =

From Google's reCAPTCHA admin console: https://www.google.com/recaptcha/admin

= What data is shared with Google? =

When configured, the login page loads Google's reCAPTCHA script. On login, your server verifies the response token with Google and may include the visitor IP address. See Google's Terms and Privacy Policy in the Third-party service section.

= What happens when I delete the plugin? =

Everything the plugin stored is removed—including its settings—so nothing from the plugin remains on the site.

== Changelog ==

= 1.4.0 =
* Renamed the plugin to Sikorix Login reCAPTCHA.
* Updated file, slug, and text domain to sikorix-login-recaptcha.

= 1.3.0 =
* Improved reCAPTCHA token handling and hostname matching.
* Added detailed verify-path debug logging and clearer failure messages when debugging.
* Loads Google api.js through WordPress script APIs.
* Added license file and WordPress.org guideline documentation (service, privacy, trademarks).

= 1.2.0 =
* Renamed the plugin to Sikora Login reCAPTCHA.
* Updated file, slug, and internal identifiers to sikora-login-recaptcha / slr.
* Migrates settings from the previous option name on upgrade.
* Documents Google reCAPTCHA as a third-party service and privacy practices for WordPress.org guidelines.

= 1.1.0 =
* Hardened reCAPTCHA verification with hostname checks.
* Stopped autoloading settings that include the secret key.
* Improved login-page layout and settings UI.
* Added debug logging for Google request failures and hostname mismatches.

= 1.0.0 =
* Initial release.

== Third-party service ==

This plugin relies on Google reCAPTCHA (a Google service):

* Terms of Service: https://policies.google.com/terms
* Google Privacy Policy: https://policies.google.com/privacy
* reCAPTCHA documentation: https://developers.google.com/recaptcha

When configured, the plugin loads `https://www.google.com/recaptcha/api.js` on the login page and sends verification requests to `https://www.google.com/recaptcha/api/siteverify` from your server.

Privacy:

* No analytics or telemetry is sent by this plugin to Sikora Collective.
* When reCAPTCHA is configured and shown, Google may process interaction data according to Google's policies (see links above).
* During login verification, this plugin may send the reCAPTCHA response token and, when available, the visitor IP address (`REMOTE_ADDR`) to Google's siteverify endpoint, together with your secret key.
* Keys are stored in the WordPress options table and are not autoloaded.

== License and trademarks ==

This plugin is licensed under GPLv2 or later. See license.txt.

reCAPTCHA® is a trademark of Google LLC. This plugin is not affiliated with or endorsed by Google.
