<?php

namespace NewfoldLabs\WP\Module\Reset\Tests\WPUnit;

use lucatume\WPBrowser\TestCase\WPTestCase;
use NewfoldLabs\WP\Module\Reset\Services\SilentUpgraderSkin;

/**
 * Test the SilentUpgraderSkin utility class.
 */
class SilentUpgraderSkinWPUnitTest extends WPTestCase {

	/**
	 * @var SilentUpgraderSkin
	 */
	private $skin;

	public function setUp(): void {
		parent::setUp();

		// WP_Upgrader_Skin lives in an admin include, not autoloaded.
		if ( ! class_exists( 'WP_Upgrader_Skin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}

		$this->skin = new SilentUpgraderSkin();

		// SilentUpgraderSkin::feedback() references $this->upgrader->strings,
		// so we need a minimal upgrader object with a strings array.
		$this->skin->upgrader = (object) array( 'strings' => array() );
	}

	// ------------------------------------------------------------------
	// feedback()
	// ------------------------------------------------------------------

	public function test_feedback_captures_plain_string() {
		$this->skin->feedback( 'Installing theme...' );

		$this->assertCount( 1, $this->skin->messages );
		$this->assertSame( 'Installing theme...', $this->skin->messages[0] );
	}

	public function test_feedback_strips_html_tags() {
		$this->skin->feedback( '<b>Bold</b> <script>alert(1)</script>message' );

		$this->assertSame( 'Bold message', $this->skin->messages[0] );
	}

	public function test_feedback_resolves_upgrader_string_keys() {
		$this->skin->upgrader->strings['installing'] = 'Installing package...';

		$this->skin->feedback( 'installing' );

		$this->assertSame( 'Installing package...', $this->skin->messages[0] );
	}

	public function test_feedback_applies_sprintf_args() {
		$this->skin->feedback( 'Step %d of %d', 2, 5 );

		$this->assertSame( 'Step 2 of 5', $this->skin->messages[0] );
	}

	public function test_feedback_resolves_key_then_applies_args() {
		$this->skin->upgrader->strings['progress'] = 'Processing %s...';

		$this->skin->feedback( 'progress', 'themes' );

		$this->assertSame( 'Processing themes...', $this->skin->messages[0] );
	}

	public function test_feedback_accumulates_multiple_messages() {
		$this->skin->feedback( 'First' );
		$this->skin->feedback( 'Second' );
		$this->skin->feedback( 'Third' );

		$this->assertCount( 3, $this->skin->messages );
		$this->assertSame( 'Third', $this->skin->messages[2] );
	}

	// ------------------------------------------------------------------
	// error()
	// ------------------------------------------------------------------

	public function test_error_captures_string_message() {
		$this->skin->error( 'Something went wrong' );

		$this->assertCount( 1, $this->skin->errors_captured );
		$this->assertSame( 'Something went wrong', $this->skin->errors_captured[0] );
	}

	public function test_error_captures_wp_error_messages() {
		$wp_error = new \WP_Error( 'fail', 'First error' );
		$wp_error->add( 'fail2', 'Second error' );

		$this->skin->error( $wp_error );

		$this->assertCount( 2, $this->skin->errors_captured );
		$this->assertSame( 'First error', $this->skin->errors_captured[0] );
		$this->assertSame( 'Second error', $this->skin->errors_captured[1] );
	}

	public function test_error_ignores_non_string_non_wp_error() {
		$this->skin->error( 12345 );

		$this->assertEmpty( $this->skin->errors_captured );
	}

	public function test_error_accumulates_across_calls() {
		$this->skin->error( 'Error one' );
		$this->skin->error( new \WP_Error( 'code', 'Error two' ) );

		$this->assertCount( 2, $this->skin->errors_captured );
	}

	// ------------------------------------------------------------------
	// Initial state
	// ------------------------------------------------------------------

	public function test_messages_array_starts_empty() {
		$fresh = new SilentUpgraderSkin();

		$this->assertIsArray( $fresh->messages );
		$this->assertEmpty( $fresh->messages );
	}

	public function test_errors_captured_array_starts_empty() {
		$fresh = new SilentUpgraderSkin();

		$this->assertIsArray( $fresh->errors_captured );
		$this->assertEmpty( $fresh->errors_captured );
	}
}
