<?php
/**
 * CRUD helper for the give_webhook_logs table.
 *
 * @package GiveWebhooks
 */

namespace GiveWebhooks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes rows in this plugin's custom webhook delivery log table.
 *
 * Direct (uncached) database queries are used throughout this class: the log
 * table is custom (not a post type or transient), append-mostly, and queried
 * by recency/id rather than by any value worth caching.
 */
class WebhookLog {

	/**
	 * Returns the fully-prefixed log table name.
	 *
	 * @return string
	 */
	public static function tableName() {
		global $wpdb;

		return $wpdb->prefix . 'give_webhook_logs';
	}

	/**
	 * Inserts a new pending log row and returns its ID.
	 *
	 * @param int    $donation_id The donation this delivery is for.
	 * @param string $event       The webhook event name.
	 * @param string $url         The destination URL.
	 * @param int    $attempt     The attempt number (1-based).
	 * @return int Inserted row ID.
	 */
	public static function create( $donation_id, $event, $url, $attempt = 1 ) {
		global $wpdb;

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			self::tableName(),
			array(
				'donation_id' => $donation_id,
				'event'       => $event,
				'url'         => $url,
				'status'      => 'pending',
				'attempt'     => $attempt,
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Records the outcome of a delivery attempt.
	 *
	 * @param int         $log_id        The log row to update.
	 * @param string      $status        'success' or 'failed'.
	 * @param int|null    $response_code The HTTP response code, if any.
	 * @param string|null $response_body The HTTP response body, if any.
	 * @return void
	 */
	public static function recordResult( $log_id, $status, $response_code = null, $response_body = null ) {
		global $wpdb;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			self::tableName(),
			array(
				'status'        => $status,
				'response_code' => $response_code,
				'response_body' => is_string( $response_body ) ? substr( $response_body, 0, 5000 ) : null,
			),
			array( 'id' => $log_id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Fetches the most recent log rows for the admin UI.
	 *
	 * @param int $limit Maximum number of rows to return.
	 * @return array
	 */
	public static function recent( $limit = 50 ) {
		global $wpdb;

		$table_name = self::tableName();

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT * FROM {$table_name} ORDER BY id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * Deletes log rows older than the retention window.
	 *
	 * @param int $days Number of days of history to keep.
	 * @return void
	 */
	public static function pruneOlderThan( $days = 30 ) {
		global $wpdb;

		$table_name = self::tableName();
		$cutoff     = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"DELETE FROM {$table_name} WHERE created_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$cutoff
			)
		);
	}
}
