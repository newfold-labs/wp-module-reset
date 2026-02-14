<?php

namespace NewfoldLabs\WP\Module\Reset\Tests\WPUnit;

use lucatume\WPBrowser\TestCase\WPTestCase;
use NewfoldLabs\WP\Module\Reset\Data\BrandConfig;

/**
 * Test the BrandConfig data class.
 */
class BrandConfigWPUnitTest extends WPTestCase {

	public function test_get_brand_id_returns_non_empty_string() {
		$brand_id = BrandConfig::get_brand_id();

		$this->assertIsString( $brand_id );
		$this->assertNotEmpty( $brand_id );
	}

	public function test_get_default_theme_slug_returns_non_empty_string() {
		$theme_slug = BrandConfig::get_default_theme_slug();

		$this->assertIsString( $theme_slug );
		$this->assertNotEmpty( $theme_slug );
	}

	public function test_get_page_slug_contains_brand_id() {
		$page_slug = BrandConfig::get_page_slug();
		$brand_id  = BrandConfig::get_brand_id();

		$this->assertStringContainsString( $brand_id, $page_slug );
	}

	public function test_get_page_slug_ends_with_factory_reset_website() {
		$page_slug = BrandConfig::get_page_slug();

		$this->assertStringEndsWith( '-factory-reset-website', $page_slug );
	}

	public function test_get_rest_namespace_contains_brand_id() {
		$namespace = BrandConfig::get_rest_namespace();
		$brand_id  = BrandConfig::get_brand_id();

		$this->assertStringContainsString( $brand_id, $namespace );
	}

	public function test_get_rest_namespace_contains_version() {
		$namespace = BrandConfig::get_rest_namespace();

		$this->assertStringContainsString( '/v1', $namespace );
	}

	public function test_get_brand_plugin_basename_returns_string() {
		$basename = BrandConfig::get_brand_plugin_basename();

		$this->assertIsString( $basename );
	}

	public function test_get_brand_name_returns_string() {
		$name = BrandConfig::get_brand_name();

		$this->assertIsString( $name );
	}
}
