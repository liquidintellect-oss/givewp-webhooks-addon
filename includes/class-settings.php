<?php
/**
 * Admin settings page: webhook URLs, secret management, enable toggle, delivery log viewer.
 *
 * @package GiveWebhooks
 */

namespace GiveWebhooks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin settings page and option storage for the plugin's webhook configuration.
 */
class Settings {

	const OPTION_NAME        = 'give_webhooks_settings';
	const PAGE_SLUG          = 'give-webhooks';
	const NONCE_ACTION       = 'give_webhooks_save_settings';
	const REGEN_NONCE_ACTION = 'give_webhooks_regenerate_secret';

	/**
	 * Registers the admin menu and form-submission handlers.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( self::class, 'registerMenu' ) );
		add_action( 'admin_post_give_webhooks_save_settings', array( self::class, 'handleSave' ) );
		add_action( 'admin_post_give_webhooks_regenerate_secret', array( self::class, 'handleRegenerateSecret' ) );
	}

	/**
	 * Returns the plugin's settings, merged with defaults.
	 *
	 * @return array{enabled: bool, urls: string[], secret: string}
	 */
	public static function getSettings() {
		$defaults = array(
			'enabled' => false,
			'urls'    => array(),
			'secret'  => '',
		);

		$settings = get_option( self::OPTION_NAME, array() );

		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Registers the "Webhooks" admin submenu page under Donations.
	 *
	 * @return void
	 */
	public static function registerMenu() {
		add_submenu_page(
			'edit.php?post_type=give_forms',
			__( 'Webhooks', 'givewp-webhooks' ),
			__( 'Webhooks', 'givewp-webhooks' ),
			'manage_give_settings',
			self::PAGE_SLUG,
			array( self::class, 'renderPage' )
		);
	}

	/**
	 * Checks whether the current user may manage this plugin's settings.
	 *
	 * @return bool
	 */
	private static function currentUserCan() {
		return current_user_can( 'manage_give_settings' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Handles the "Save Settings" form submission.
	 *
	 * @return void
	 */
	public static function handleSave() {
		if ( ! self::currentUserCan() ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'givewp-webhooks' ) );
		}

		check_admin_referer( self::NONCE_ACTION );

		$settings = self::getSettings();

		$settings['enabled'] = ! empty( $_POST['enabled'] );

		// Each line is validated below via wp_http_validate_url(); anything that
		// isn't a well-formed URL is dropped before it's ever stored or used.
		$raw_urls = isset( $_POST['urls'] ) ? (string) wp_unslash( $_POST['urls'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$urls     = array_filter( array_map( 'trim', explode( "\n", $raw_urls ) ) );
		$urls     = array_values(
			array_filter(
				$urls,
				function ( $url ) {
					return (bool) wp_http_validate_url( $url );
				}
			)
		);

		$settings['urls'] = $urls;

		update_option( self::OPTION_NAME, $settings );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::PAGE_SLUG,
					'updated' => '1',
				),
				admin_url( 'edit.php?post_type=give_forms' )
			)
		);
		exit;
	}

	/**
	 * Handles the "Regenerate Secret" form submission.
	 *
	 * @return void
	 */
	public static function handleRegenerateSecret() {
		if ( ! self::currentUserCan() ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'givewp-webhooks' ) );
		}

		check_admin_referer( self::REGEN_NONCE_ACTION );

		$settings           = self::getSettings();
		$settings['secret'] = wp_generate_password( 64, true, true );
		update_option( self::OPTION_NAME, $settings );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => self::PAGE_SLUG,
					'regenerated' => '1',
				),
				admin_url( 'edit.php?post_type=give_forms' )
			)
		);
		exit;
	}

	/**
	 * Renders the settings page: the configuration form and the delivery log.
	 *
	 * @return void
	 */
	public static function renderPage() {
		if ( ! self::currentUserCan() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'givewp-webhooks' ) );
		}

		$settings = self::getSettings();
		$logs     = WebhookLog::recent( 50 );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'GiveWP Webhooks', 'givewp-webhooks' ); ?></h1>

			<?php if ( ! empty( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag, not acted upon. ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'givewp-webhooks' ); ?></p></div>
			<?php endif; ?>

			<?php if ( ! empty( $_GET['regenerated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag, not acted upon. ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Secret regenerated. Update your receiving endpoint(s) with the new secret.', 'givewp-webhooks' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="give_webhooks_save_settings" />
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enabled', 'givewp-webhooks' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enabled" value="1" <?php checked( $settings['enabled'] ); ?> />
								<?php esc_html_e( 'Send webhooks when a donation completes', 'givewp-webhooks' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="give-webhooks-urls"><?php esc_html_e( 'Webhook URLs', 'givewp-webhooks' ); ?></label>
						</th>
						<td>
							<textarea id="give-webhooks-urls" name="urls" rows="5" cols="60" class="large-text code">
							<?php
								echo esc_textarea( implode( "\n", $settings['urls'] ) );
							?>
							</textarea>
							<p class="description"><?php esc_html_e( 'One URL per line. Each will receive a POST request for every completed donation.', 'givewp-webhooks' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Signing Secret', 'givewp-webhooks' ); ?></th>
						<td>
							<code><?php echo esc_html( $settings['secret'] ); ?></code>
							<p class="description">
								<?php esc_html_e( 'Sent requests are signed with HMAC-SHA256 using this secret in the X-GiveWP-Webhook-Signature header (format: sha256=<hex>, computed over "<timestamp>.<raw body>").', 'givewp-webhooks' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Settings', 'givewp-webhooks' ) ); ?>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: -10px;">
				<input type="hidden" name="action" value="give_webhooks_regenerate_secret" />
				<?php wp_nonce_field( self::REGEN_NONCE_ACTION ); ?>
				<?php submit_button( __( 'Regenerate Secret', 'givewp-webhooks' ), 'secondary', 'submit', false ); ?>
			</form>

			<h2><?php esc_html_e( 'Recent Deliveries', 'givewp-webhooks' ); ?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Time', 'givewp-webhooks' ); ?></th>
						<th><?php esc_html_e( 'Donation', 'givewp-webhooks' ); ?></th>
						<th><?php esc_html_e( 'Event', 'givewp-webhooks' ); ?></th>
						<th><?php esc_html_e( 'URL', 'givewp-webhooks' ); ?></th>
						<th><?php esc_html_e( 'Attempt', 'givewp-webhooks' ); ?></th>
						<th><?php esc_html_e( 'Status', 'givewp-webhooks' ); ?></th>
						<th><?php esc_html_e( 'Response Code', 'givewp-webhooks' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $logs ) ) : ?>
						<tr><td colspan="7"><?php esc_html_e( 'No deliveries yet.', 'givewp-webhooks' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $logs as $log ) : ?>
							<tr>
								<td><?php echo esc_html( $log['created_at'] ); ?></td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'post.php?action=edit&post=' . absint( $log['donation_id'] ) ) ); ?>">
										#<?php echo esc_html( $log['donation_id'] ); ?>
									</a>
								</td>
								<td><?php echo esc_html( $log['event'] ); ?></td>
								<td><?php echo esc_html( $log['url'] ); ?></td>
								<td><?php echo esc_html( $log['attempt'] ); ?></td>
								<td><?php echo esc_html( $log['status'] ); ?></td>
								<td><?php echo esc_html( $log['response_code'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
