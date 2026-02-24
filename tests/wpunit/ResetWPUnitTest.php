<?php

namespace NewfoldLabs\WP\Module\Reset\Tests\WPUnit;

use lucatume\WPBrowser\TestCase\WPTestCase;
use NewfoldLabs\WP\Module\Reset\Reset;
use NewfoldLabs\WP\ModuleLoader\Container;

/**
 * Test Reset module main class hooks and load_textdomain.
 */
class ResetWPUnitTest extends WPTestCase {

	public function test_constructor_adds_init_action_for_load_textdomain() {
		if ( ! class_exists( Container::class ) ) {
			$this->markTestSkipped( 'Module loader Container not available in this environment.' );
		}
		$container = $this->createMock( Container::class );
		$reset     = new Reset( $container );

		$this->assertGreaterThan( 0, has_action( 'init', array( $reset, 'load_textdomain' ) ) );
	}

	public function test_load_textdomain_is_callable_without_error() {
		if ( ! class_exists( Container::class ) ) {
			$this->markTestSkipped( 'Module loader Container not available in this environment.' );
		}
		$container = $this->createMock( Container::class );
		$reset     = new Reset( $container );

		$reset->load_textdomain();

		$this->assertTrue( true, 'load_textdomain should not throw.' );
	}
}
