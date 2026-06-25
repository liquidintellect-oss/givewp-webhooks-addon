<?php
/**
 * Registers WP Cron hooks used for async delivery, retries, and log retention.
 *
 * @package GiveWebhooks
 */

namespace GiveWebhooks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the WP Cron hooks used for async delivery, retries, and log retention.
 */
class Cron {

	/**
	 * Registers the cron action callbacks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'give_webhooks_retry_delivery', array( WebhookSender::class, 'retry' ), 10, 3 );
		add_action( 'give_webhooks_cleanup_logs', array( self::class, 'cleanupLogs' ) );
	}

	/**
	 * Daily cron callback: prunes old delivery log rows.
	 *
	 * @return void
	 */
	public static function cleanupLogs() {
		WebhookLog::pruneOlderThan( 30 );
	}
}
