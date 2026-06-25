<?php
/**
 * Minimal stand-ins for the GiveWP classes our plugin depends on
 * (Give\Donations\Models\Donation and its value objects), so unit tests can
 * run without a real GiveWP installation.
 *
 * Donation::find() is test-controllable via Donation::$fixtures, which test
 * cases populate before invoking code that calls find().
 */

namespace Give\Framework\Support\ValueObjects {

	class Currency {
		private string $code;

		public function __construct( string $code ) {
			$this->code = $code;
		}

		public function getCode(): string {
			return $this->code;
		}
	}

	class Money {
		private string $decimal;
		private Currency $currency;

		public function __construct( string $decimal, string $currency_code ) {
			$this->decimal  = $decimal;
			$this->currency = new Currency( $currency_code );
		}

		public function formatToDecimal(): string {
			return $this->decimal;
		}

		public function getCurrency(): Currency {
			return $this->currency;
		}
	}
}

namespace Give\Donations\ValueObjects {

	/**
	 * Stand-in for GiveWP's Enum-based value objects (DonationStatus,
	 * DonationMode, DonationType) -- real instances expose getValue().
	 */
	class EnumStub {
		private string $value;

		public function __construct( string $value ) {
			$this->value = $value;
		}

		public function getValue(): string {
			return $this->value;
		}
	}
}

namespace Give\Donations\Models {

	use Give\Donations\ValueObjects\EnumStub;
	use Give\Framework\Support\ValueObjects\Money;

	/**
	 * Test double for Give\Donations\Models\Donation.
	 *
	 * Real GiveWP code resolves instances via the static find() method; tests
	 * seed Donation::$fixtures[$id] with a configured instance beforehand.
	 */
	class Donation {
		public int $id;
		public int $campaignId              = 0;
		public int $formId                  = 0;
		public string $formTitle            = '';
		public ?\DateTime $createdAt        = null;
		public ?\DateTime $updatedAt        = null;
		public ?EnumStub $status            = null;
		public ?EnumStub $mode              = null;
		public ?EnumStub $type              = null;
		public ?Money $amount               = null;
		public string $gatewayId            = '';
		public string $gatewayTransactionId = '';
		public int $donorId                 = 0;
		public string $firstName            = '';
		public string $lastName             = '';
		public string $email                = '';
		public string $company              = '';
		public string $comment              = '';
		public bool $anonymous              = false;
		public int $subscriptionId          = 0;
		public string $purchaseKey          = '';

		/** @var array<int,self> */
		public static array $fixtures = array();

		public static function find( $id ) {
			return self::$fixtures[ $id ] ?? null;
		}
	}
}
