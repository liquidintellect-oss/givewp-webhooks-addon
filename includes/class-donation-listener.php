<?php
/**
 * Listens for GiveWP's donation-completed event and triggers webhook dispatch.
 *
 * @package GiveWebhooks
 */

namespace GiveWebhooks;

use Give\Donations\Models\Donation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bridges GiveWP's donation-completed hook to the webhook dispatcher.
 */
class DonationListener {

	/**
	 * Registers the donation-completed listener.
	 *
	 * @return void
	 */
	public static function init() {
		// Fires exactly once when a donation transitions to "complete", regardless of
		// whether it went through the legacy Give_Payment path or the new Donation model
		// (the two layers are bridged internally), so we don't need to de-dupe here.
		add_action( 'give_complete_donation', array( self::class, 'handleDonationCompleted' ), 100, 1 );
	}

	/**
	 * Loads the completed donation and dispatches its webhook payload.
	 *
	 * @param int $donation_id The completed donation's ID.
	 * @return void
	 */
	public static function handleDonationCompleted( $donation_id ) {
		$donation = Donation::find( (int) $donation_id );

		if ( ! $donation ) {
			return;
		}

		$payload = PayloadBuilder::build( $donation, 'donation.completed' );

		WebhookSender::dispatchToAll( $payload );
	}
}
