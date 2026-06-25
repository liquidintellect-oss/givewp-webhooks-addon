<?php
/**
 * Signs and delivers webhook payloads, scheduling retries on failure.
 *
 * @package GiveWebhooks
 */

namespace GiveWebhooks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Signs outbound webhook requests and delivers them, retrying on failure.
 */
class WebhookSender {

	const MAX_ATTEMPTS = 5;

	/**
	 * Retry backoff in seconds, indexed by attempt number (1-based, attempt that just failed).
	 *
	 * @var int[]
	 */
	private static $backoff = array(
		1 => 60,    // 1 minute.
		2 => 300,   // 5 minutes.
		3 => 1800,  // 30 minutes.
		4 => 7200,  // 2 hours.
	);

	/**
	 * Dispatches a payload to every configured webhook URL.
	 *
	 * Delivery is scheduled via WP Cron (fires within seconds, off the current request)
	 * so that the donor-facing checkout request is never blocked on outbound HTTP calls.
	 *
	 * @param array $payload The webhook payload, as built by PayloadBuilder.
	 * @return void
	 */
	public static function dispatchToAll( array $payload ) {
		$settings = Settings::getSettings();

		if ( empty( $settings['enabled'] ) || empty( $settings['urls'] ) ) {
			return;
		}

		foreach ( $settings['urls'] as $url ) {
			$url = trim( $url );

			if ( '' === $url ) {
				continue;
			}

			// Schedule for "now" rather than calling send() inline, so this never
			// blocks the request thread that just completed the donation.
			wp_schedule_single_event( time(), 'give_webhooks_retry_delivery', array( $url, $payload, 1 ) );
		}
	}

	/**
	 * Sends (or resends) a single webhook attempt.
	 *
	 * @param string $url     The destination URL.
	 * @param array  $payload The webhook payload.
	 * @param int    $log_id  The WebhookLog row this attempt updates.
	 * @param int    $attempt The attempt number (1-based).
	 * @return void
	 */
	public static function send( $url, array $payload, $log_id, $attempt ) {
		$settings = Settings::getSettings();
		$secret   = isset( $settings['secret'] ) ? $settings['secret'] : '';

		$body      = wp_json_encode( $payload );
		$timestamp = time();
		$signature = hash_hmac( 'sha256', $timestamp . '.' . $body, $secret );

		$response = wp_remote_post(
			$url,
			array(
				'timeout'  => 10,
				'blocking' => true,
				'headers'  => array(
					'Content-Type'               => 'application/json',
					'X-GiveWP-Webhook-Event'     => isset( $payload['event'] ) ? $payload['event'] : '',
					'X-GiveWP-Webhook-Timestamp' => (string) $timestamp,
					'X-GiveWP-Webhook-Signature' => 'sha256=' . $signature,
				),
				'body'     => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			self::handleFailure( $url, $payload, $log_id, $attempt, null, $response->get_error_message() );
			return;
		}

		$code          = (int) wp_remote_retrieve_response_code( $response );
		$body_response = wp_remote_retrieve_body( $response );

		if ( $code >= 200 && $code < 300 ) {
			WebhookLog::recordResult( $log_id, 'success', $code, $body_response );
			return;
		}

		self::handleFailure( $url, $payload, $log_id, $attempt, $code, $body_response );
	}

	/**
	 * Records a failed delivery attempt and schedules a retry if attempts remain.
	 *
	 * @param string      $url          The destination URL.
	 * @param array       $payload      The webhook payload.
	 * @param int         $log_id       The WebhookLog row this attempt updates.
	 * @param int         $attempt      The attempt number that just failed.
	 * @param int|null    $code         The HTTP response code, if any.
	 * @param string|null $body_response The HTTP response body, if any.
	 * @return void
	 */
	private static function handleFailure( $url, array $payload, $log_id, $attempt, $code, $body_response ) {
		WebhookLog::recordResult( $log_id, 'failed', $code, $body_response );

		if ( $attempt >= self::MAX_ATTEMPTS ) {
			return;
		}

		$delay        = isset( self::$backoff[ $attempt ] ) ? self::$backoff[ $attempt ] : 7200;
		$next_attempt = $attempt + 1;

		wp_schedule_single_event(
			time() + $delay,
			'give_webhooks_retry_delivery',
			array( $url, $payload, $next_attempt )
		);
	}

	/**
	 * Cron callback for a scheduled retry.
	 *
	 * @param string $url     The destination URL.
	 * @param array  $payload The webhook payload.
	 * @param int    $attempt The attempt number being sent.
	 * @return void
	 */
	public static function retry( $url, array $payload, $attempt ) {
		$donation_id = isset( $payload['donation']['id'] ) ? (int) $payload['donation']['id'] : 0;
		$event       = isset( $payload['event'] ) ? $payload['event'] : 'donation.completed';

		$log_id = WebhookLog::create( $donation_id, $event, $url, $attempt );
		self::send( $url, $payload, $log_id, $attempt );
	}
}
