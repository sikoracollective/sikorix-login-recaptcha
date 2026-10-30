<?php
/**
 * Admin settings for Google reCAPTCHA keys.
 *
 * Stores the Site Key / ID and secret key under the `slr_settings` option and
 * exposes them on Sikorix → Login reCAPTCHA.
 *
 * @package SLR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // prevent direct access
}

/**
 * Handles plugin settings registration, sanitization, and admin UI.
 */
class SLR_Settings {

	/**
	 * Settings API option group.
	 *
	 * @var string
	 */
	const OPTION_GROUP = 'slr_settings_group';

	/**
	 * Option name storing site_key and secret_key.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'slr_settings';

	/**
	 * Legacy option name from the previous plugin slug.
	 *
	 * @var string
	 */
	const LEGACY_OPTION_NAME = 'sgrwl_settings';

	/**
	 * Top-level Sikorix admin menu slug.
	 *
	 * @var string
	 */
	const PARENT_SLUG = 'sikorix';

	/**
	 * Settings page slug under Sikorix.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'slr-settings';

	/**
	 * Hook settings page and option registration into WordPress.
	 *
	 * @return void
	 */
	public function register() {
		$this->maybe_migrate_legacy_option();

		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'maybe_disable_option_autoload' ) );
		add_action( 'add_option_' . self::OPTION_NAME, array( $this, 'disable_option_autoload' ) );
		add_action( 'update_option_' . self::OPTION_NAME, array( $this, 'disable_option_autoload' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SLR_PLUGIN_FILE ), array( $this, 'add_plugin_action_links' ) );
	}

	/**
	 * Add a Settings link on the Plugins screen.
	 *
	 * @param string[] $links Existing action links.
	 * @return string[]
	 */
	public function add_plugin_action_links( $links ) {
		$url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );

		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( $url ),
				esc_html__( 'Settings', 'sikorix-login-recaptcha' )
			)
		);

		return $links;
	}

	/**
	 * Copy settings from the previous option name when upgrading.
	 *
	 * @return void
	 */
	private function maybe_migrate_legacy_option() {
		if ( false !== get_option( self::OPTION_NAME, false ) ) {
			return;
		}

		$legacy = get_option( self::LEGACY_OPTION_NAME, false );
		if ( false === $legacy || ! is_array( $legacy ) ) {
			return;
		}

		add_option( self::OPTION_NAME, $legacy, '', false );
		delete_option( self::LEGACY_OPTION_NAME );
	}

	/**
	 * Get a single setting value from the stored option array.
	 *
	 * @param string $key     Setting key (`site_key` or `secret_key`).
	 * @param string $default Default when the key is missing.
	 * @return string
	 */
	public function get( $key, $default = '' ) {
		$options = get_option( self::OPTION_NAME, array() );

		return isset( $options[ $key ] ) ? (string) $options[ $key ] : $default;
	}

	/**
	 * Whether both the Site Key / ID and Secret Key are present.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return '' !== $this->get( 'site_key' ) && '' !== $this->get( 'secret_key' );
	}

	/**
	 * Add the plugin settings page under Sikorix → Login reCAPTCHA.
	 *
	 * @return void
	 */
	public function add_settings_page() {
		global $admin_page_hooks;

		$created_parent = false;
		if ( ! isset( $admin_page_hooks[ self::PARENT_SLUG ] ) ) {
			add_menu_page(
				__( 'Sikorix', 'sikorix-login-recaptcha' ),
				__( 'Sikorix', 'sikorix-login-recaptcha' ),
				'manage_options',
				self::PARENT_SLUG,
				array( $this, 'redirect_to_settings_page' ),
				'dashicons-shield',
				58
			);
			$created_parent = true;
		}

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Sikorix Login reCAPTCHA', 'sikorix-login-recaptcha' ),
			__( 'Login reCAPTCHA', 'sikorix-login-recaptcha' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);

		// drop WP's auto-added parent duplicate so only Login reCAPTCHA shows
		if ( $created_parent ) {
			remove_submenu_page( self::PARENT_SLUG, self::PARENT_SLUG );
		}
	}

	/**
	 * Send top-level Sikorix clicks to this plugin's settings page.
	 *
	 * @return void
	 */
	public function redirect_to_settings_page() {
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
		exit;
	}

	/**
	 * Register the option, section, and fields with the Settings API.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(
					'site_key'   => '',
					'secret_key' => '',
				),
				'autoload'          => false, // keep secret out of alloptions
			)
		);

		add_settings_section(
			'slr_main_section',
			__( 'Google reCAPTCHA v2', 'sikorix-login-recaptcha' ),
			array( $this, 'render_section_description' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'slr_site_key',
			__( 'Site Key / ID', 'sikorix-login-recaptcha' ),
			array( $this, 'render_site_key_field' ),
			self::PAGE_SLUG,
			'slr_main_section'
		);

		add_settings_field(
			'slr_secret_key',
			__( 'Secret Key', 'sikorix-login-recaptcha' ),
			array( $this, 'render_secret_key_field' ),
			self::PAGE_SLUG,
			'slr_main_section'
		);
	}

	/**
	 * Sanitize and trim submitted settings values.
	 *
	 * @param array $input Raw settings input.
	 * @return array{
	 *     site_key: string,
	 *     secret_key: string
	 * }
	 */
	public function sanitize_settings( $input ) {
		$output = array(
			'site_key'   => '',
			'secret_key' => '',
		);

		foreach ( array_keys( $output ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$output[ $key ] = trim( sanitize_text_field( $input[ $key ] ) );
			}
		}

		return $output;
	}

	/**
	 * Convert an existing autoloaded option row to non-autoload if needed.
	 *
	 * @return void
	 */
	public function maybe_disable_option_autoload() {
		if ( false === get_option( self::OPTION_NAME, false ) ) {
			return;
		}

		$alloptions = wp_load_alloptions();
		if ( ! isset( $alloptions[ self::OPTION_NAME ] ) ) {
			return; // already excluded from autoload
		}

		$this->disable_option_autoload();
	}

	/**
	 * Force the settings option not to autoload.
	 *
	 * @return void
	 */
	public function disable_option_autoload() {
		if ( function_exists( 'wp_set_option_autoload' ) ) {
			wp_set_option_autoload( self::OPTION_NAME, false );
			return;
		}

		// WP < 6.4: recreate the option with autoload disabled via Settings API helpers
		$value = get_option( self::OPTION_NAME, null );
		if ( null === $value ) {
			return;
		}

		delete_option( self::OPTION_NAME );
		add_option( self::OPTION_NAME, $value, '', 'no' );
	}

	/**
	 * Output the Google reCAPTCHA v2 section help bullets.
	 *
	 * @return void
	 */
	public function render_section_description() {
		echo '<ul class="slr-settings-bullets">';
		echo '<li>' . esc_html__(
			'Values for the fields below should be created with Google\'s reCAPTCHA admin console.',
			'sikorix-login-recaptcha'
		) . '</li>';
		echo '<li>' . esc_html__(
			'The reCAPTCHA element will not appear on the WordPress login page if any value is missing.',
			'sikorix-login-recaptcha'
		) . '</li>';
		echo '</ul>';
	}

	/**
	 * Render the Site Key / ID input field.
	 *
	 * @return void
	 */
	public function render_site_key_field() {
		$this->render_settings_field( 'site_key' );
	}

	/**
	 * Render the Secret Key input field.
	 *
	 * @return void
	 */
	public function render_secret_key_field() {
		$this->render_settings_field( 'secret_key' );
	}

	/**
	 * Render a settings text input for the given option key.
	 *
	 * @param string $key Setting key (`site_key` or `secret_key`).
	 * @return void
	 */
	private function render_settings_field( $key ) {
		// autocomplete + manager ignore attrs reduce credential autofill
		printf(
			'<input type="text" class="regular-text slr-settings-input" name="%1$s[%2$s]" value="%3$s" autocomplete="off" spellcheck="false" data-1p-ignore="true" data-lpignore="true" data-bwignore="true" data-form-type="other" />',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $key ),
			esc_attr( $this->get( $key ) )
		);
	}

	/**
	 * Render the full settings page markup.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<style>
				.slr-settings-version {
					font-size: 0.55em;
					font-weight: 400;
					margin-left: 0.4em;
				}
				.slr-settings-section > ul.slr-settings-bullets {
					list-style: disc;
					margin: 0.5em 0 1em 1.5em;
					padding-left: 1.5em;
				}
				.slr-settings-section > .form-table {
					margin-left: 1.5em;
					width: auto;
				}
				.slr-settings-section > .form-table th {
					width: 1%;
					padding: 10px 8px 6px 0;
					vertical-align: top;
					white-space: nowrap;
					line-height: 2;
				}
				.slr-settings-section > .form-table td {
					padding: 6px 0;
					vertical-align: top;
				}
			</style>
			<h1 style="font-weight: 700;">
				<?php echo esc_html( get_admin_page_title() ); ?>
				<span class="slr-settings-version"><?php echo esc_html( SLR_VERSION ); ?></span>
			</h1>
			<form action="options.php" method="post" autocomplete="off">
				<?php settings_fields( self::OPTION_GROUP ); ?>
				<div class="slr-settings-section">
					<?php do_settings_sections( self::PAGE_SLUG ); ?>
				</div>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
