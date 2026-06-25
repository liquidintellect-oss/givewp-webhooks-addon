<?php

use Give\Donations\Models\Donation;
use Give\Donations\ValueObjects\EnumStub;
use Give\Framework\Support\ValueObjects\Money;
use GiveWebhooks\DonationListener;
use PHPUnit\Framework\TestCase;

class DonationListenerTest extends TestCase {

	protected function setUp(): void {
		WP_Mock::setUp();

		global $wpdb;
		$wpdb = new WpdbStub();

		Donation::$fixtures = array();
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
	}

	/** @test */
	public function init_hooks_give_complete_donation_at_priority_100(): void {
		WP_Mock::expectActionAdded(
			'give_complete_donation',
			array( DonationListener::class, 'handleDonationCompleted' ),
			100,
			1
		);

		DonationListener::init();

		$this->addToAssertionCount( 1 );
	}

	/** @test */
	public function handle_donation_completed_does_nothing_when_the_donation_cannot_be_found(): void {
		// No fixture seeded for id 999, so Donation::find() returns null and the
		// listener must bail out before touching settings/HTTP at all.
		DonationListener::handleDonationCompleted( 999 );

		$this->addToAssertionCount( 1 );
	}

	/** @test */
	public function handle_donation_completed_builds_a_payload_and_dispatches_it(): void {
		$donation         = new Donation();
		$donation->id     = 42;
		$donation->status = new EnumStub( 'publish' );
		$donation->amount = new Money( '25.00', 'USD' );
		$donation->email  = 'jane@example.com';

		Donation::$fixtures[42] = $donation;

		WP_Mock::userFunction( 'get_option' )
			->with( 'give_webhooks_settings', Mockery::type( 'array' ) )
			->andReturn(
				array(
					'enabled' => true,
					'urls'    => array( 'https://example.com/hook' ),
					'secret'  => 'topsecret',
				)
			);

		WP_Mock::userFunction( 'wp_schedule_single_event' )
			->once()
			->with(
				Mockery::type( 'integer' ),
				'give_webhooks_retry_delivery',
				Mockery::on(
					function ( $args ) {
						return 'https://example.com/hook' === $args[0]
							&& 42 === $args[1]['donation']['id']
							&& 'jane@example.com' === $args[1]['donation']['email']
							&& 1 === $args[2];
					}
				)
			);

		DonationListener::handleDonationCompleted( 42 );

		$this->addToAssertionCount( 1 );
	}
}
