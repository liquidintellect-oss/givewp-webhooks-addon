<?php
/**
 * Builds the outbound webhook JSON payload from a GiveWP Donation model.
 *
 * @package GiveWebhooks
 */

namespace GiveWebhooks;

use Give\Donations\Models\Donation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts a GiveWP Donation model into the array sent as a webhook payload.
 */
class PayloadBuilder {

	/**
	 * Builds the payload array for a completed (or otherwise notable) donation.
	 *
	 * @param Donation $donation The donation to serialize.
	 * @param string   $event    The webhook event name.
	 * @return array
	 */
	public static function build( Donation $donation, $event = 'donation.completed' ) {
		$amount   = null;
		$currency = null;

		if ( $donation->amount ) {
			$amount   = $donation->amount->formatToDecimal();
			$currency = $donation->amount->getCurrency()->getCode();
		}

		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Donation model properties are GiveWP's own API and use camelCase; we don't control their naming.
		return array(
			'event'    => $event,
			'donation' => array(
				'id'                     => $donation->id,
				'status'                 => (string) $donation->status->getValue(),
				'mode'                   => $donation->mode ? (string) $donation->mode->getValue() : null,
				'type'                   => $donation->type ? (string) $donation->type->getValue() : null,
				'amount'                 => $amount,
				'currency'               => $currency,
				'gateway_id'             => $donation->gatewayId,
				'gateway_transaction_id' => $donation->gatewayTransactionId,
				'form_id'                => $donation->formId,
				'form_title'             => $donation->formTitle,
				'donor_id'               => $donation->donorId,
				'first_name'             => $donation->firstName,
				'last_name'              => $donation->lastName,
				'email'                  => $donation->email,
				'company'                => $donation->company,
				'comment'                => $donation->comment,
				'anonymous'              => (bool) $donation->anonymous,
				'subscription_id'        => $donation->subscriptionId,
				'purchase_key'           => $donation->purchaseKey,
				'created_at'             => $donation->createdAt ? $donation->createdAt->format( DATE_ATOM ) : null,
				'updated_at'             => $donation->updatedAt ? $donation->updatedAt->format( DATE_ATOM ) : null,
			),
			'site'     => array(
				'url'  => home_url(),
				'name' => get_bloginfo( 'name' ),
			),
		);
		// phpcs:enable
	}
}
