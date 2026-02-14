<?php

namespace NewfoldLabs\WP\Module\Reset\Tests\WPUnit;

use lucatume\WPBrowser\TestCase\WPTestCase;
use NewfoldLabs\WP\Module\Reset\Reset;
use NewfoldLabs\WP\Module\Reset\ResetFeature;
use NewfoldLabs\WP\Module\Reset\Permissions;
use NewfoldLabs\WP\Module\Reset\Data\BrandConfig;
use NewfoldLabs\WP\Module\Reset\Services\ResetService;
use NewfoldLabs\WP\Module\Reset\Services\SilentUpgraderSkin;
use NewfoldLabs\WP\Module\Reset\Admin\ToolsPage;
use NewfoldLabs\WP\Module\Reset\Api\Controllers\ResetController;

/**
 * Verify that all module classes exist and constants are defined.
 */
class ModuleLoadingWPUnitTest extends WPTestCase {

	public function test_reset_class_exists() {
		$this->assertTrue( class_exists( Reset::class ) );
	}

	public function test_reset_feature_class_exists() {
		$this->assertTrue( class_exists( ResetFeature::class ) );
	}

	public function test_reset_service_class_exists() {
		$this->assertTrue( class_exists( ResetService::class ) );
	}

	public function test_reset_controller_class_exists() {
		$this->assertTrue( class_exists( ResetController::class ) );
	}

	public function test_tools_page_class_exists() {
		$this->assertTrue( class_exists( ToolsPage::class ) );
	}

	public function test_permissions_class_exists() {
		$this->assertTrue( class_exists( Permissions::class ) );
	}

	public function test_brand_config_class_exists() {
		$this->assertTrue( class_exists( BrandConfig::class ) );
	}

	public function test_silent_upgrader_skin_class_exists() {
		// WP_Upgrader_Skin is in wp-admin/includes/class-wp-upgrader.php,
		// which is not auto-loaded in unit test context.
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		$this->assertTrue( class_exists( SilentUpgraderSkin::class ) );
	}

	public function test_nfd_reset_version_constant_defined() {
		$this->assertTrue( defined( 'NFD_RESET_VERSION' ) );
	}

	public function test_nfd_reset_dir_constant_defined() {
		$this->assertTrue( defined( 'NFD_RESET_DIR' ) );
	}
}
