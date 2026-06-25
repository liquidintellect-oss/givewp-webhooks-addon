<?php

use GiveWebhooks\WebhookSender;
use PHPUnit\Framework\TestCase;

class WebhookSenderTest extends TestCase {

	private const SETTINGS_OPTION = 'give_webhooks_settings';

	protected function setUp(): void {
		WP_Mock::setUp();

		global $wpdb;
		$wpdb = new WpdbStub();
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
	}

	private function stubSettings( array $settings ): void {
		WP_Mock::userFunction( 'get_option' )
			->with( self::SETTINGS_OPTION, Mockery::type( 'array' ) )
			->andReturn( $settings );
	}

	// ── send() ────────────────────────────────────────────────────────────────

	/** @test */
	public function send_signs_the_request_and_records_a_success(): void {
		$this->stubSettings(
			array(
				'enabled' => true,
				'urls'    => array(),
				'secret'  => 'topsecret',
			)
		);

		$payload = array( 'event' => 'donation.completed', 'donation' => array( 'id' => 42 ) );
		$body    = wp_json_encode( $payload );

		WP_Mock::userFunction( 'wp_remote_post' )
			->once()
			->with(
				'https://example.com/hook',
				Mockery::type( 'array' )
			)
			->andReturnUsing(
				function ( $url, $args ) use ( $body ) {
					$this->assertSame( $body, $args['body'] );
					$this->assertSame( 'donation.completed', $args['headers']['X-GiveWP-Webhook-Event'] );
					$this->assertArrayHasKey( 'X-GiveWP-Webhook-Timestamp', $args['headers'] );

					$timestamp = $args['headers']['X-GiveWP-Webhook-Timestamp'];
					$expected  = 'sha256=' . hash_hmac( 'sha256', $timestamp . '.' . $body, 'topsecret' );
					$this->assertSame( $expected, $args['headers']['X-GiveWP-Webhook-Signature'] );

					return array(
						'response' => array( 'code' => 200 ),
						'body'     => 'ok',
					);
				}
			);

		WebhookSender::send( 'https://example.com/hook', $payload, 1, 1 );

		global $wpdb;
		$this->assertCount( 1, $wpdb->updates );
		$this->assertSame( 'success', $wpdb->updates[0]['data']['status'] );
		$this->assertSame( 200, $wpdb->updates[0]['data']['response_code'] );
	}

	/** @test */
	public function send_records_a_failure_and_schedules_a_retry_on_a_non_2xx_response(): void {
		$this->stubSettings(
			array(
				'enabled' => true,
				'urls'    => array(),
				'secret'  => 'topsecret',
			)
		);

		$payload = array( 'event' => 'donation.completed', 'donation' => array( 'id' => 42 ) );

		WP_Mock::userFunction( 'wp_remote_post' )
			->once()
			->andReturn(
				array(
					'response' => array( 'code' => 500 ),
					'body'     => 'server error',
				)
			);

		WP_Mock::userFunction( 'wp_schedule_single_event' )
			->once()
			->with(
				Mockery::type( 'integer' ),
				'give_webhooks_retry_delivery',
				array( 'https://example.com/hook', $payload, 2 )
			);

		WebhookSender::send( 'https://example.com/hook', $payload, 1, 1 );

		global $wpdb;
		$this->assertSame( 'failed', $wpdb->updates[0]['data']['status'] );
		$this->assertSame( 500, $wpdb->updates[0]['data']['response_code'] );
	}

	/** @test */
	public function send_records_a_failure_when_the_request_errors_and_stops_after_the_final_attempt(): void {
		$this->stubSettings(
			array(
				'enabled' => true,
				'urls'    => array(),
				'secret'  => 'topsecret',
			)
		);

		$payload = array( 'event' => 'donation.completed', 'donation' => array( 'id' => 42 ) );

		WP_Mock::userFunction( 'wp_remote_post' )
			->once()
			->andReturn( new WP_Error( 'http_request_failed', 'Connection timed out' ) );

		// Already at the max attempt (5) -- no retry should be scheduled.
		WebhookSender::send( 'https://example.com/hook', $payload, 1, WebhookSender::MAX_ATTEMPTS );

		global $wpdb;
		$this->assertSame( 'failed', $wpdb->updates[0]['data']['status'] );
		$this->assertNull( $wpdb->updates[0]['data']['response_code'] );
	}

	// ── dispatchToAll() ──────────────────────────────────────────────────────

	/** @test */
	public function dispatch_to_all_does_nothing_when_webhooks_are_disabled(): void {
		$this->stubSettings(
			array(
				'enabled' => false,
				'urls'    => array( 'https://example.com/hook' ),
				'secret'  => 'topsecret',
			)
		);

		WebhookSender::dispatchToAll( array( 'event' => 'donation.completed' ) );

		$this->addToAssertionCount( 1 );
	}

	/** @test */
	public function dispatch_to_all_schedules_one_delivery_per_configured_url(): void {
		$this->stubSettings(
			array(
				'enabled' => true,
				'urls'    => array( 'https://example.com/a', 'https://example.com/b' ),
				'secret'  => 'topsecret',
			)
		);

		$payload = array( 'event' => 'donation.completed', 'donation' => array( 'id' => 42 ) );

		WP_Mock::userFunction( 'wp_schedule_single_event' )
			->once()
			->with( Mockery::type( 'integer' ), 'give_webhooks_retry_delivery', array( 'https://example.com/a', $payload, 1 ) );

		WP_Mock::userFunction( 'wp_schedule_single_event' )
			->once()
			->with( Mockery::type( 'integer' ), 'give_webhooks_retry_delivery', array( 'https://example.com/b', $payload, 1 ) );

		WebhookSender::dispatchToAll( $payload );

		$this->addToAssertionCount( 1 );
	}

	/** @test */
	public function dispatch_to_all_skips_blank_urls(): void {
		$this->stubSettings(
			array(
				'enabled' => true,
				'urls'    => array( '  ', '' ),
				'secret'  => 'topsecret',
			)
		);

		WebhookSender::dispatchToAll( array( 'event' => 'donation.completed' ) );

		$this->addToAssertionCount( 1 );
	}
}
