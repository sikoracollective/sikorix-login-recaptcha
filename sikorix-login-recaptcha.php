<?php
/**
 * Plugin Name:       Sikorix Login reCAPTCHA
 * Description:       Adds Google reCAPTCHA v2 to the WordPress login page.
 * Version:           1.4.1
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Sikora Collective
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sikorix-login-recaptcha
 *
 * reCAPTCHA is a trademark of Google LLC. This plugin is not affiliated with Google.
 *
 * @package SLR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // prevent direct access
}

define( 'SLR_VERSION', '1.4.1' );                          // plugin version
define( 'SLR_PLUGIN_FILE', __FILE__ );                     // main plugin file path
define( 'SLR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );   // plugin directory path
define( 'SLR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );    // plugin directory url

require_once SLR_PLUGIN_DIR . 'includes/class-slr-settings.php';
require_once SLR_PLUGIN_DIR . 'includes/class-slr-login.php';

/**
 * Bootstrap settings and login integrations after plugins load.
 *
 * @return void
 */
function slr_init() {
	$settings = new SLR_Settings();
	$settings->register();

	$login = new SLR_Login( $settings );
	$login->register();
}
add_action( 'plugins_loaded', 'slr_init' );
