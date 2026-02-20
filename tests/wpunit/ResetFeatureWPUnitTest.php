<?php

namespace NewfoldLabs\WP\Module\Reset\Tests\WPUnit;

use lucatume\WPBrowser\TestCase\WPTestCase;
use NewfoldLabs\WP\Module\Reset\ResetFeature;
use ReflectionClass;

/**
 * Test ResetFeature registration (name, value, initialize).
 */
class ResetFeatureWPUnitTest extends WPTestCase {

	public function test_feature_name_is_reset() {
		$ref = new ReflectionClass( ResetFeature::class );
		$prop = $ref->getProperty( 'name' );
		$prop->setAccessible( true );

		$feature = new ResetFeature();
		$this->assertSame( 'reset', $prop->getValue( $feature ) );
	}

	public function test_feature_value_defaults_to_true() {
		$ref  = new ReflectionClass( ResetFeature::class );
		$prop = $ref->getProperty( 'value' );
		$prop->setAccessible( true );

		$feature = new ResetFeature();
		$this->assertTrue( $prop->getValue( $feature ) );
	}

	public function test_initialize_adds_plugins_loaded_action() {
		$feature = new ResetFeature();
		$feature->initialize();

		$this->assertGreaterThan( 0, has_action( 'plugins_loaded' ) );
	}
}
