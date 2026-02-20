<?php

namespace NewfoldLabs\WP\Module\Reset\Tests\WPUnit;

use lucatume\WPBrowser\TestCase\WPTestCase;
use NewfoldLabs\WP\Module\Reset\Services\ResetStepRunner;

/**
 * Test ResetStepRunner step execution and result normalization.
 */
class ResetStepRunnerWPUnitTest extends WPTestCase {

	public function test_run_returns_step_array_unchanged_when_callback_returns_success_structure() {
		$callback = function () {
			return array( 'success' => true, 'message' => 'Done.' );
		};

		$result = ResetStepRunner::run( $callback );

		$this->assertSame( array( 'success' => true, 'message' => 'Done.' ), $result );
	}

	public function test_run_wraps_truthy_return_in_standard_structure() {
		$callback = function () {
			return true;
		};

		$result = ResetStepRunner::run( $callback );

		$this->assertTrue( $result['success'] );
		$this->assertSame( '', $result['message'] );
	}

	public function test_run_wraps_falsy_return_in_standard_structure() {
		$callback = function () {
			return false;
		};

		$result = ResetStepRunner::run( $callback );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'Step failed.', $result['message'] );
	}

	public function test_run_catches_throwable_and_returns_error_structure() {
		$callback = function () {
			throw new \RuntimeException( 'Simulated failure' );
		};

		$result = ResetStepRunner::run( $callback );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'Simulated failure', $result['message'] );
		$this->assertStringContainsString( 'Error:', $result['message'] );
	}

	public function test_run_passes_arguments_to_callback() {
		$callback = function ( $a, $b ) {
			return array( 'success' => true, 'message' => $a . '-' . $b );
		};

		$result = ResetStepRunner::run( $callback, 'foo', 'bar' );

		$this->assertTrue( $result['success'] );
		$this->assertSame( 'foo-bar', $result['message'] );
	}

	public function test_run_with_success_false_in_array_returns_unchanged() {
		$callback = function () {
			return array( 'success' => false, 'message' => 'Custom error' );
		};

		$result = ResetStepRunner::run( $callback );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'Custom error', $result['message'] );
	}
}
