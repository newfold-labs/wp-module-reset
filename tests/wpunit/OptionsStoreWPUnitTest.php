<?php

namespace NewfoldLabs\WP\Module\Reset\Tests\WPUnit;

use lucatume\WPBrowser\TestCase\WPTestCase;
use NewfoldLabs\WP\Module\Reset\Infrastructure\OptionsStore;

/**
 * Test OptionsStore get/set and transient helpers.
 */
class OptionsStoreWPUnitTest extends WPTestCase {

	private $option_name = 'nfd_reset_test_option_' . __CLASS__;
	private $transient_name = 'nfd_reset_test_transient_' . __CLASS__;

	public function tearDown(): void {
		delete_option( $this->option_name );
		delete_transient( $this->transient_name );
		parent::tearDown();
	}

	public function test_get_returns_default_when_option_missing() {
		$value = OptionsStore::get( $this->option_name, 'default_value' );

		$this->assertSame( 'default_value', $value );
	}

	public function test_get_returns_stored_value() {
		update_option( $this->option_name, 'stored_value' );

		$value = OptionsStore::get( $this->option_name, 'default_value' );

		$this->assertSame( 'stored_value', $value );
	}

	public function test_set_updates_option_and_returns_true() {
		$result = OptionsStore::set( $this->option_name, 'new_value' );

		$this->assertTrue( $result );
		$this->assertSame( 'new_value', get_option( $this->option_name ) );
	}

	public function test_get_transient_returns_default_when_missing() {
		$value = OptionsStore::get_transient( $this->transient_name, 'transient_default' );

		$this->assertSame( 'transient_default', $value );
	}

	public function test_get_transient_returns_stored_value() {
		set_transient( $this->transient_name, 'transient_value', 60 );

		$value = OptionsStore::get_transient( $this->transient_name, 'default' );

		$this->assertSame( 'transient_value', $value );
	}

	public function test_set_transient_stores_value() {
		$result = OptionsStore::set_transient( $this->transient_name, 'stored_transient', 60 );

		$this->assertTrue( $result );
		$this->assertSame( 'stored_transient', get_transient( $this->transient_name ) );
	}
}
