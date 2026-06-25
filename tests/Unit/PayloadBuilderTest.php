<?php

use Give\Donations\Models\Donation;
use Give\Donations\ValueObjects\EnumStub;
use Give\Framework\Support\ValueObjects\Money;
use GiveWebhooks\PayloadBuilder;
use PHPUnit\Framework\TestCase;

class PayloadBuilderTest extends TestCase {

	private function makeDonation(): Donation {
		$donation                       = new Donation();
		$donation->id                   = 42;
		$donation->formId               = 7;
		$donation->formTitle            = 'Annual Fund';
		$donation->status               = new EnumStub( 'publish' );
		$donation->mode                 = new EnumStub( 'live' );
		$donation->type                 = new EnumStub( 'single' );
		$donation->amount               = new Money( '25.00', 'USD' );
		$donation->gatewayId            = 'stripe';
		$donation->gatewayTransactionId = 'pi_123';
		$donation->donorId              = 9;
		$donation->firstName            = 'Jane';
		$donation->lastName             = 'Smith';
		$donation->email                = 'jane@example.com';
		$donation->company              = 'Acme';
		$donation->comment              = 'Keep up the great work!';
		$donation->anonymous            = false;
		$donation->subscriptionId       = 0;
		$donation->purchaseKey          = 'abc123';
		$donation->createdAt            = new DateTime( '2024-01-15T10:00:00+00:00' );
		$donation->updatedAt            = new DateTime( '2024-01-15T10:05:00+00:00' );

		return $donation;
	}

	/** @test */
	public function builds_event_and_site_keys(): void {
		$payload = PayloadBuilder::build( $this->makeDonation() );

		$this->assertSame( 'donation.completed', $payload['event'] );
		$this->assertSame( 'https://example.com', $payload['site']['url'] );
		$this->assertSame( 'Example Site', $payload['site']['name'] );
	}

	/** @test */
	public function builds_donation_fields_from_the_model(): void {
		$payload  = PayloadBuilder::build( $this->makeDonation() );
		$donation = $payload['donation'];

		$this->assertSame( 42, $donation['id'] );
		$this->assertSame( 'publish', $donation['status'] );
		$this->assertSame( 'live', $donation['mode'] );
		$this->assertSame( 'single', $donation['type'] );
		$this->assertSame( '25.00', $donation['amount'] );
		$this->assertSame( 'USD', $donation['currency'] );
		$this->assertSame( 'stripe', $donation['gateway_id'] );
		$this->assertSame( 'pi_123', $donation['gateway_transaction_id'] );
		$this->assertSame( 7, $donation['form_id'] );
		$this->assertSame( 'Annual Fund', $donation['form_title'] );
		$this->assertSame( 'jane@example.com', $donation['email'] );
		$this->assertFalse( $donation['anonymous'] );
		$this->assertSame( '2024-01-15T10:00:00+00:00', $donation['created_at'] );
		$this->assertSame( '2024-01-15T10:05:00+00:00', $donation['updated_at'] );
	}

	/** @test */
	public function honors_a_custom_event_name(): void {
		$payload = PayloadBuilder::build( $this->makeDonation(), 'donation.refunded' );

		$this->assertSame( 'donation.refunded', $payload['event'] );
	}

	/** @test */
	public function handles_a_donation_without_an_amount(): void {
		$donation         = $this->makeDonation();
		$donation->amount = null;

		$payload = PayloadBuilder::build( $donation );

		$this->assertNull( $payload['donation']['amount'] );
		$this->assertNull( $payload['donation']['currency'] );
	}
}
