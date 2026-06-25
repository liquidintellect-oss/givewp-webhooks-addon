<?php
/**
 * Plugin bootstrap: verifies GiveWP is active and compatible, then wires up the rest of the plugin.
 *
 * @package GiveWebhooks
 */

namespace GiveWebhooks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Verifies GiveWP is active and compatible, then wires up the rest of the plugin.
 */
class Bootstrap {

	/**
	 * Entry point, hooked on `plugins_loaded` (priority 20, after GiveWP itself loads).
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! self::giveIsActiveAndCompatible() ) {
			add_action( 'admin_notices', array( self::class, 'renderIncompatibilityNotice' ) );
			add_action( 'admin_init', array( self::class, 'deactivateSelf' ) );
			return;
		}

		self::loadTextDomain();
		self::loadIncludes();

		Settings::init();
		DonationListener::init();
		Cron::init();
	}

	/**
	 * Checks whether GiveWP is active and at least GIVE_WEBHOOKS_MIN_GIVE_VERSION.
	 *
	 * @return bool
	 */
	private static function giveIsActiveAndCompatible() {
		if ( ! defined( 'GIVE_VERSION' ) ) {
			return false;
		}

		if ( ! class_exists( 'Give\\Donations\\Models\\Donation' ) ) {
			return false;
		}

		return version_compare( GIVE_VERSION, GIVE_WEBHOOKS_MIN_GIVE_VERSION, '>=' );
	}

	/**
	 * Loads the plugin's translation files.
	 *
	 * @return void
	 */
	private static function loadTextDomain() {
		load_plugin_textdomain( 'givewp-webhooks', false, dirname( GIVE_WEBHOOKS_BASENAME ) . '/languages' );
	}

	/**
	 * Requires every class file the plugin needs once GiveWP compatibility is confirmed.
	 *
	 * @return void
	 */
	private static function loadIncludes() {
		require_once GIVE_WEBHOOKS_DIR . 'includes/class-webhook-log.php';
		require_once GIVE_WEBHOOKS_DIR . 'includes/class-payload-builder.php';
		require_once GIVE_WEBHOOKS_DIR . 'includes/class-webhook-sender.php';
		require_once GIVE_WEBHOOKS_DIR . 'includes/class-donation-listener.php';
		require_once GIVE_WEBHOOKS_DIR . 'includes/class-cron.php';
		require_once GIVE_WEBHOOKS_DIR . 'includes/class-settings.php';
	}

	/**
	 * Renders the admin notice shown when GiveWP is missing or too old.
	 *
	 * @return void
	 */
	public static function renderIncompatibilityNotice() {
		?>
		<div class="notice notice-error">
			<p>
				<?php
				printf(
					/* translators: %s: minimum required GiveWP version. */
					esc_html__( 'GiveWP Webhooks requires GiveWP version %s or higher to be active. The plugin has been deactivated.', 'givewp-webhooks' ),
					esc_html( GIVE_WEBHOOKS_MIN_GIVE_VERSION )
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Deactivates this plugin when GiveWP isn't active/compatible.
	 *
	 * @return void
	 */
	public static function deactivateSelf() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		deactivate_plugins( GIVE_WEBHOOKS_BASENAME );

		// Avoid the default "Plugin activated" admin notice from also showing.
		// Read-only: clears a display flag, doesn't act on submitted form data.
		if ( isset( $_GET['activate'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			unset( $_GET['activate'] );
		}
	}
}
