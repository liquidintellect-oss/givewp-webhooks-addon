<?php
/**
 * Handles plugin activation/deactivation: DB table creation, secret generation, cron unscheduling.
 *
 * @package GiveWebhooks
 */

namespace GiveWebhooks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin activation and deactivation lifecycle handler.
 */
class Activation {

	const OPTION_SETTINGS = 'give_webhooks_settings';

	/**
	 * Runs on plugin activation: creates the log table, ensures a secret
	 * exists, and schedules the daily log-cleanup cron event.
	 *
	 * @return void
	 */
	public static function activate() {
		self::createLogTable();
		self::maybeGenerateSecret();

		if ( ! wp_next_scheduled( 'give_webhooks_cleanup_logs' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'give_webhooks_cleanup_logs' );
		}
	}

	/**
	 * Runs on plugin deactivation: clears scheduled cron events.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'give_webhooks_cleanup_logs' );
		wp_clear_scheduled_hook( 'give_webhooks_retry_delivery' );
	}

	/**
	 * Creates the webhook delivery log table if it doesn't already exist.
	 *
	 * @return void
	 */
	private static function createLogTable() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = $wpdb->prefix . 'give_webhook_logs';
		$charset_collate = $wpdb->get_charset_collate();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.NamingConventions.ValidVariableName.InterpolatedVariableNotSnakeCase -- dbDelta() requires a literal CREATE TABLE statement; the table name isn't user input.
		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			donation_id BIGINT(20) UNSIGNED NOT NULL,
			event VARCHAR(100) NOT NULL DEFAULT 'donation.completed',
			url TEXT NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			response_code SMALLINT NULL,
			response_body LONGTEXT NULL,
			attempt SMALLINT UNSIGNED NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY donation_id (donation_id),
			KEY status (status)
		) {$charset_collate};";
		// phpcs:enable

		dbDelta( $sql );
	}

	/**
	 * Ensures the settings option has a signing secret and the other
	 * defaults populated, generating a secret the first time the plugin
	 * is activated.
	 *
	 * @return void
	 */
	private static function maybeGenerateSecret() {
		$settings = get_option( self::OPTION_SETTINGS, array() );

		if ( empty( $settings['secret'] ) ) {
			$settings['secret'] = wp_generate_password( 64, true, true );
		}

		if ( ! isset( $settings['urls'] ) ) {
			$settings['urls'] = array();
		}

		if ( ! isset( $settings['enabled'] ) ) {
			$settings['enabled'] = false;
		}

		update_option( self::OPTION_SETTINGS, $settings );
	}
}
