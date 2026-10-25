# Sikorix Login reCAPTCHA

Adds Google reCAPTCHA v2 to the WordPress login page.

## Requirements

- WordPress 5.8+
- PHP 7.4+
- A WordPress user with the **Administrator** role (required to see and configure the plugin)
- Google reCAPTCHA v2 **site key (ID)** and **Secret Key**

## Installation

1. Copy this folder into `wp-content/plugins/`, or install the zip from `./build.sh`.
2. As an **Administrator**, activate **Sikorix Login reCAPTCHA** in **Plugins**.
3. Go to **Settings → Sikorix Login reCAPTCHA**.
4. Under **Google reCAPTCHA v2**, paste your **ID** and **Secret Key**.
5. Save changes.

## Behavior

- Only users with the **Administrator** role can see this plugin in the Plugins list or open its settings. Non-Administrators cannot view or modify the settings.
- Values for the settings fields should be created in Google's reCAPTCHA admin console.
- The reCAPTCHA element will not appear on the WordPress login page if any value is missing.
- When both values are saved, the reCAPTCHA widget appears on the login form.
- Login is blocked unless Google returns a successful reCAPTCHA response for an allowed hostname.

## Project Structure

```
sikorix-login-recaptcha/
├── sikorix-login-recaptcha.php  # plugin bootstrap
├── includes/
│   ├── class-slr-settings.php  # admin settings page
│   └── class-slr-login.php     # login widget + verification
├── build.sh                    # builds installable plugin zip
├── uninstall.php               # removes options on plugin delete
├── license.txt                 # GPLv2 or later
├── readme.txt                  # WordPress.org plugin readme
└── README.md
```

## Build

Run `./build.sh` to create an installable WordPress plugin zip file.

## Uninstall

When you delete the plugin, it removes everything it stored—including its settings—so nothing from the plugin remains on the site (including on every site in a multisite network).

## Third-party service

The plugin is an interface to [Google reCAPTCHA](https://developers.google.com/recaptcha):

- [Google Terms of Service](https://policies.google.com/terms)
- [Google Privacy Policy](https://policies.google.com/privacy)
- Credential console: [https://www.google.com/recaptcha/admin](https://www.google.com/recaptcha/admin)

When keys are saved, the login page loads `https://www.google.com/recaptcha/api.js`, and the server verifies tokens at `https://www.google.com/recaptcha/api/siteverify` (may include the visitor IP). No data is sent to Sikora Collective.

## License and trademarks

This plugin is licensed under GPLv2 or later. See license.txt.

reCAPTCHA® is a trademark of Google LLC. This plugin is not affiliated with or endorsed by Google.
