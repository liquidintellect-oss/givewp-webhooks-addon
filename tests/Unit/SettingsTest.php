<?php

use GiveWebhooks\Settings;
use PHPUnit\Framework\TestCase;

class SettingsTest extends TestCase {

	protected function setUp(): void {
		WP_Mock::setUp();
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
	}

	/** @test */
	public function get_settings_fills_in_missing_keys_with_defaults(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'give_webhooks_settings', Mockery::type( 'array' ) )
			->andReturn( array( 'secret' => 'abc123' ) );

		$settings = Settings::getSettings();

		$this->assertSame( 'abc123', $settings['secret'] );
		$this->assertFalse( $settings['enabled'] );
		$this->assertSame( array(), $settings['urls'] );
	}

	/** @test */
	public function get_settings_returns_stored_values_unchanged(): void {
		WP_Mock::userFunction( 'get_option' )
			->with( 'give_webhooks_settings', Mockery::type( 'array' ) )
			->andReturn(
				array(
					'enabled' => true,
					'urls'    => array( 'https://example.com/hook' ),
					'secret'  => 'topsecret',
				)
			);

		$settings = Settings::getSettings();

		$this->assertTrue( $settings['enabled'] );
		$this->assertSame( array( 'https://example.com/hook' ), $settings['urls'] );
		$this->assertSame( 'topsecret', $settings['secret'] );
	}

	/** @test */
	public function init_registers_the_admin_menu_and_form_handlers(): void {
		WP_Mock::expectActionAdded( 'admin_menu', array( Settings::class, 'registerMenu' ) );
		WP_Mock::expectActionAdded( 'admin_post_give_webhooks_save_settings', array( Settings::class, 'handleSave' ) );
		WP_Mock::expectActionAdded( 'admin_post_give_webhooks_regenerate_secret', array( Settings::class, 'handleRegenerateSecret' ) );

		Settings::init();

		$this->addToAssertionCount( 1 );
	}
}
