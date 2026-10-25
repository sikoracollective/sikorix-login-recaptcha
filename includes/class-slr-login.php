<?php
/**
 * Login page reCAPTCHA rendering and verification.
 *
 * Shows the Google reCAPTCHA v2 widget on wp-login.php when keys are configured
 * and blocks authentication unless Google returns a successful response for
 * this site's hostname.
 *
 * @package SLR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // prevent direct access
}

/**
 * Integrates Google reCAPTCHA with the WordPress login flow.
 */
class SLR_Login {

	/**
	 * Google siteverify endpoint.
	 *
	 * @var string
	 */
	const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

	/**
	 * Settings helper used for keys and configuration checks.
	 *
	 * @var SLR_Settings
	 */
	private $settings;

	/**
	 * @param SLR_Settings $settings Settings instance.
	 */
	public function __construct( SLR_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Register login-page assets, markup, and authentication filter.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'login_form', array( $this, 'render_widget' ) );
		// after core credential checks (priority 20) so a wp_error is not overwritten
		add_filter( 'authenticate', array( $this, 'verify_recaptcha' ), 99, 3 );
	}

	/**
	 * Enqueue login styles and Google reCAPTCHA api.js when configured.
	 *
	 * Loads api.js from Google over HTTPS as part of the reCAPTCHA service.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->settings->is_configured() ) {
			return;
		}

		$css = 'body.login #login{width:350px;}'
			. '#login .slr-recaptcha-wrapper{margin:12px 0 16px;clear:both;}'
			. '#login .slr-recaptcha-wrapper .g-recaptcha{display:block;width:304px;max-width:100%;}';

		wp_register_style( 'slr-login', false, array(), SLR_VERSION );
		wp_enqueue_style( 'slr-login' );
		wp_add_inline_style( 'slr-login', $css );

		$site_key = $this->settings->get( 'site_key' );
		$api_url  = add_query_arg(
			array(
				'onload' => 'slrRecaptchaOnload',
				'render' => 'explicit',
			),
			'https://www.google.com/recaptcha/api.js'
		);

		wp_register_script( 'slr-google-recaptcha', $api_url, array(), SLR_VERSION, true );
		wp_enqueue_script( 'slr-google-recaptcha' );

		$inline = 'function slrRecaptchaOnload(){'
			. 'var el=document.getElementById("slr-recaptcha");'
			. 'if(!el||typeof grecaptcha==="undefined"){return;}'
			. 'if(el.getAttribute("data-slr-rendered")==="1"){return;}'
			. 'grecaptcha.render(el,{sitekey:' . wp_json_encode( $site_key ) . '});'
			. 'el.setAttribute("data-slr-rendered","1");'
			. '}';

		wp_add_inline_script( 'slr-google-recaptcha', $inline, 'before' );
		add_filter( 'script_loader_tag', array( $this, 'filter_recaptcha_script_tag' ), 10, 3 );
	}

	/**
	 * Add async/defer to the Google reCAPTCHA script tag.
	 *
	 * @param string $tag    Script HTML.
	 * @param string $handle Script handle.
	 * @param string $src    Script source URL.
	 * @return string
	 */
	public function filter_recaptcha_script_tag( $tag, $handle, $src ) {
		if ( 'slr-google-recaptcha' !== $handle ) {
			return $tag;
		}

		if ( false !== strpos( $tag, ' async' ) || false !== strpos( $tag, ' defer' ) ) {
			return $tag;
		}

		return str_replace( ' src', ' async defer src', $tag );
	}

	/**
	 * Output the reCAPTCHA widget container on the login form.
	 *
	 * @return void
	 */
	public function render_widget() {
		if ( ! $this->settings->is_configured() ) {
			return;
		}

		wp_nonce_field( 'slr_login_recaptcha', 'slr_login_nonce' );
		?>
		<div class="slr-recaptcha-wrapper">
			<div id="slr-recaptcha" class="g-recaptcha" data-sitekey="<?php echo esc_attr( $this->settings->get( 'site_key' ) ); ?>"></div>
		</div>
		<?php
	}

	/**
	 * Require a successful reCAPTCHA before allowing login.
	 *
	 * Runs after core credential authentication so a failed captcha cannot be
	 * replaced by a successful username/password result.
	 *
	 * @param WP_User|WP_Error|null $user     Authenticated user, error, or null.
	 * @param string                $username Submitted username.
	 * @param string                $password Submitted password.
	 * @return WP_User|WP_Error|null
	 */
	public function verify_recaptcha( $user, $username, $password ) {
		if ( empty( $username ) && empty( $password ) ) {
			return $user; // skip empty login attempts
		}

		if ( ! $this->settings->is_configured() ) {
			$this->log_error( 'Verify skipped: plugin is not configured with both keys.' );
			return $user;
		}

		if ( ! $this->verify_login_nonce() ) {
			$this->log_error( 'Verify failed: missing or invalid login nonce.' );

			return new WP_Error(
				'slr_invalid_login_nonce',
				__( '<strong>Error:</strong> Login security check failed. Please try again.', 'sikorix-login-recaptcha' )
			);
		}

		$token = $this->get_recaptcha_token();

		$this->log_error(
			sprintf(
				'Verify started. token_present=%s token_length=%d remote_ip=%s',
				'' !== $token ? 'yes' : 'no',
				strlen( $token ),
				$this->get_remote_ip() !== '' ? 'yes' : 'no'
			)
		);

		if ( '' === $token ) {
			$this->log_error( 'Verify failed: missing g-recaptcha-response token in POST.' );

			return new WP_Error(
				'slr_missing_recaptcha',
				__( '<strong>Error:</strong> Please complete the reCAPTCHA.', 'sikorix-login-recaptcha' )
			);
		}

		$request_body = array(
			'secret'   => $this->settings->get( 'secret_key' ),
			'response' => $token,
		);

		$remote_ip = $this->get_remote_ip();
		if ( '' !== $remote_ip ) {
			$request_body['remoteip'] = $remote_ip;
		}

		$response = wp_remote_post(
			self::VERIFY_URL,
			array(
				'timeout' => 10,
				'body'    => $request_body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->verification_request_error(
				sprintf(
					'Google reCAPTCHA verify request failed: %s',
					$response->get_error_message()
				)
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$raw_body    = wp_remote_retrieve_body( $response );

		$this->log_error(
			sprintf(
				'Google siteverify HTTP %d. body_length=%d',
				$status_code,
				strlen( (string) $raw_body )
			)
		);

		if ( $status_code < 200 || $status_code >= 300 ) {
			return $this->verification_request_error(
				sprintf(
					'Google reCAPTCHA verify returned HTTP %d.',
					$status_code
				)
			);
		}

		$body = json_decode( $raw_body, true );

		if ( ! is_array( $body ) ) {
			return $this->verification_request_error(
				sprintf(
					'Google reCAPTCHA verify returned an unexpected response body. json_error=%s',
					function_exists( 'json_last_error_msg' ) ? json_last_error_msg() : 'unknown'
				)
			);
		}

		$codes    = ( isset( $body['error-codes'] ) && is_array( $body['error-codes'] ) )
			? implode( ', ', $body['error-codes'] )
			: 'none';
		$hostname = isset( $body['hostname'] ) ? (string) $body['hostname'] : '';
		$success  = ! empty( $body['success'] );

		$this->log_error(
			sprintf(
				'Google siteverify parsed. success=%s hostname="%s" error-codes=%s allowed_hostnames=%s',
				$success ? 'true' : 'false',
				$hostname,
				$codes,
				implode( ', ', $this->get_allowed_hostnames() )
			)
		);

		if ( ! $success ) {
			$this->log_error( 'Verify failed: Google returned success=false.' );

			$message = __( '<strong>Error:</strong> reCAPTCHA verification failed. Please try again.', 'sikorix-login-recaptcha' );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$message = sprintf(
					/* translators: %s: Google reCAPTCHA error codes */
					__( '<strong>Error:</strong> reCAPTCHA was rejected by Google (%s). Please try again.', 'sikorix-login-recaptcha' ),
					esc_html( $codes )
				);
			}

			return new WP_Error( 'slr_invalid_recaptcha', $message );
		}

		if ( ! $this->is_hostname_allowed( $hostname ) ) {
			$this->log_error(
				sprintf(
					'Verify failed: hostname mismatch. received="%s" allowed=%s',
					$hostname,
					implode( ', ', $this->get_allowed_hostnames() )
				)
			);

			$message = __( '<strong>Error:</strong> reCAPTCHA verification failed. Please try again.', 'sikorix-login-recaptcha' );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$message = sprintf(
					/* translators: %s: hostname returned by Google */
					__( '<strong>Error:</strong> reCAPTCHA hostname mismatch (%s). Please try again.', 'sikorix-login-recaptcha' ),
					esc_html( $hostname )
				);
			}

			return new WP_Error( 'slr_invalid_recaptcha_hostname', $message );
		}

		$this->log_error( 'Verify succeeded.' );

		return $user;
	}

	/**
	 * Verify the login form nonce before reading POST data.
	 *
	 * @return bool
	 */
	private function verify_login_nonce() {
		return isset( $_POST['slr_login_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['slr_login_nonce'] ) ), 'slr_login_recaptcha' );
	}

	/**
	 * Read the submitted reCAPTCHA response token.
	 *
	 * Avoids sanitize_text_field(), which strips percent-encoded sequences and
	 * can corrupt valid Google tokens. Requires a valid login nonce first.
	 *
	 * @return string
	 */
	private function get_recaptcha_token() {
		if (
			! isset( $_POST['slr_login_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['slr_login_nonce'] ) ), 'slr_login_recaptcha' )
		) {
			return '';
		}

		if ( ! isset( $_POST['g-recaptcha-response'] ) ) {
			return '';
		}

		// Opaque Google token: do not use sanitize_text_field() — it strips %XX and can invalidate responses.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified via Google siteverify API
		$raw = wp_unslash( $_POST['g-recaptcha-response'] );
		if ( ! is_string( $raw ) ) {
			return '';
		}

		$token = trim( $raw );

		// reject clearly invalid payloads; google performs full validation
		if ( '' === $token || false !== strpos( $token, "\0" ) ) {
			return '';
		}

		return $token;
	}

	/**
	 * Log a verify-request failure and return the shared user-facing error.
	 *
	 * @param string $message Debug log message.
	 * @return WP_Error
	 */
	private function verification_request_error( $message ) {
		$this->log_error( $message );

		return new WP_Error(
			'slr_recaptcha_request_failed',
			__( '<strong>Error:</strong> Could not verify reCAPTCHA. Please try again.', 'sikorix-login-recaptcha' )
		);
	}

	/**
	 * Whether Google's reported hostname matches this site.
	 *
	 * @param string $hostname Hostname from the siteverify response.
	 * @return bool
	 */
	private function is_hostname_allowed( $hostname ) {
		$hostname = strtolower( trim( $hostname ) );
		if ( '' === $hostname ) {
			return false;
		}

		foreach ( $this->get_allowed_hostnames() as $allowed ) {
			if ( $hostname === $allowed || $this->normalize_hostname( $hostname ) === $this->normalize_hostname( $allowed ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Hostnames allowed for Google reCAPTCHA responses.
	 *
	 * @return string[]
	 */
	private function get_allowed_hostnames() {
		$allowed = array();

		foreach ( array( home_url(), site_url() ) as $url ) {
			$host = wp_parse_url( $url, PHP_URL_HOST );
			if ( is_string( $host ) && '' !== $host ) {
				$allowed[] = strtolower( $host );
			}
		}

		if ( ! empty( $_SERVER['HTTP_HOST'] ) ) {
			$host = strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) );
			$host = preg_replace( '/:\d+$/', '', $host ); // strip port
			if ( is_string( $host ) && '' !== $host ) {
				$allowed[] = $host;
			}
		}

		return array_values( array_unique( $allowed ) );
	}

	/**
	 * Normalize a hostname for www / non-www comparison.
	 *
	 * @param string $hostname Hostname.
	 * @return string
	 */
	private function normalize_hostname( $hostname ) {
		if ( 0 === strpos( $hostname, 'www.' ) ) {
			return substr( $hostname, 4 );
		}

		return $hostname;
	}

	/**
	 * Log an error when WordPress debug logging is enabled.
	 *
	 * @param string $message Error message.
	 * @return void
	 */
	private function log_error( $message ) {
		if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- gated by WP_DEBUG_LOG for site owners debugging verify failures
		error_log( 'Sikorix Login reCAPTCHA: ' . $message );
	}

	/**
	 * Best-effort client IP for Google verification.
	 *
	 * Uses REMOTE_ADDR only; proxy headers are ignored to avoid spoofing.
	 *
	 * @return string
	 */
	private function get_remote_ip() {
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
			return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
		}

		return '';
	}
}
