<?php
/**
 * PHPUnit bootstrap file.
 *
 * Initialises WP_Mock, defines the WordPress/GiveWP constants and function
 * stubs needed by the plugin classes under test, and registers the Composer
 * classmap autoloader so plugin classes can be resolved without a running
 * WordPress + GiveWP installation.
 */

require_once __DIR__ . '/../vendor/autoload.php';

// ── WordPress constants ──────────────────────────────────────────────────────

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/give-webhooks-test/' );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}

// Plugin constants normally set by the main plugin file.
if ( ! defined( 'GIVE_WEBHOOKS_VERSION' ) ) {
	define( 'GIVE_WEBHOOKS_VERSION', '1.0.0.0-test' );
}
if ( ! defined( 'GIVE_WEBHOOKS_URL' ) ) {
	define( 'GIVE_WEBHOOKS_URL', 'https://example.com/wp-content/plugins/givewp-webhooks-addon/' );
}
if ( ! defined( 'GIVE_WEBHOOKS_FILE' ) ) {
	define( 'GIVE_WEBHOOKS_FILE', dirname( __DIR__ ) . '/givewp-webhooks.php' );
}
if ( ! defined( 'GIVE_WEBHOOKS_DIR' ) ) {
	define( 'GIVE_WEBHOOKS_DIR', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'GIVE_WEBHOOKS_BASENAME' ) ) {
	define( 'GIVE_WEBHOOKS_BASENAME', 'givewp-webhooks-addon/givewp-webhooks.php' );
}
if ( ! defined( 'GIVE_WEBHOOKS_MIN_GIVE_VERSION' ) ) {
	define( 'GIVE_WEBHOOKS_MIN_GIVE_VERSION', '2.20.0' );
}

// ── WordPress function stubs ─────────────────────────────────────────────────

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, string $domain = 'default' ): string {
		return esc_html( $text );
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( string $text, string $domain = 'default' ): void {
		echo esc_html( $text );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( string $url ): string {
		return $url;
	}
}

if ( ! function_exists( 'esc_textarea' ) ) {
	function esc_textarea( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'checked' ) ) {
	function checked( $checked, $current = true, bool $echo = true ): string {
		$result = ( (string) $checked === (string) $current ) ? ' checked="checked"' : '';
		if ( $echo ) {
			echo $result;
		}
		return $result;
	}
}

if ( ! function_exists( 'submit_button' ) ) {
	function submit_button( string $text = '', string $type = 'primary', string $name = 'submit', bool $wrap = true ): void {
		echo $text;
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( $action = -1, string $name = '_wpnonce', bool $referer = true, bool $echo = true ): string {
		$field = '<input type="hidden" name="' . $name . '" value="test-nonce" />';
		if ( $echo ) {
			echo $field;
		}
		return $field;
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, array $defaults = array() ): array {
		if ( is_string( $args ) ) {
			parse_str( $args, $args );
		}
		return array_merge( $defaults, (array) $args );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( int $length = 12, bool $special_chars = true, bool $extra_special_chars = false ): string {
		return substr( str_repeat( 'a1B2', (int) ceil( $length / 4 ) ), 0, $length );
	}
}

if ( ! function_exists( 'wp_http_validate_url' ) ) {
	function wp_http_validate_url( string $url ) {
		return filter_var( $url, FILTER_VALIDATE_URL ) ? $url : false;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, int $options = 0 ) {
		return json_encode( $data, $options );
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( string $path = '' ): string {
		return 'https://example.com' . $path;
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( string $show = '' ): string {
		return 'Example Site';
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( string $path = '' ): string {
		return 'https://example.com/wp-admin/' . $path;
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( array $args, string $url ): string {
		return $url . '?' . http_build_query( $args );
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( string $type = 'mysql', $gmt = 0 ) {
		return gmdate( 'Y-m-d H:i:s' );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $capability ): bool {
		return true;
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ): bool {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( $response ) {
		return is_array( $response ) && isset( $response['response']['code'] ) ? $response['response']['code'] : 0;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $response ) {
		return is_array( $response ) && isset( $response['body'] ) ? $response['body'] : '';
	}
}

// ── WordPress class stubs ────────────────────────────────────────────────────

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private string $message;

		public function __construct( string $code = '', string $message = '' ) {
			$this->message = $message;
		}

		public function get_error_message(): string {
			return $this->message;
		}
	}
}

// ── GiveWP class/value-object stubs ──────────────────────────────────────────

require_once __DIR__ . '/Fixtures/GiveStubs.php';

// ── $wpdb stub ────────────────────────────────────────────────────────────────

require_once __DIR__ . '/Fixtures/WpdbStub.php';

global $wpdb;
$wpdb = new WpdbStub();

// ── WP_Mock bootstrap ────────────────────────────────────────────────────────

WP_Mock::bootstrap();
