<?php

namespace NewfoldLabs\WP\Module\Reset\Tests\WPUnit;

use lucatume\WPBrowser\TestCase\WPTestCase;
use NewfoldLabs\WP\Module\Reset\Admin\ToolsPage;
use NewfoldLabs\WP\Module\Reset\Data\BrandConfig;

/**
 * Test the Tools admin page registration and configuration.
 */
class ToolsPageWPUnitTest extends WPTestCase {

	public function test_constructor_hooks_admin_menu() {
		$tools_page = new ToolsPage();

		$this->assertIsInt( has_action( 'admin_menu', array( $tools_page, 'register_page' ) ) );
	}

	public function test_constructor_hooks_admin_init() {
		$tools_page = new ToolsPage();

		$this->assertIsInt( has_action( 'admin_init', array( $tools_page, 'handle_submission' ) ) );
	}

	public function test_page_slug_contains_brand_id() {
		$slug     = ToolsPage::get_slug();
		$brand_id = BrandConfig::get_brand_id();

		$this->assertStringContainsString( $brand_id, $slug );
	}

	public function test_page_slug_ends_with_factory_reset_website() {
		$slug = ToolsPage::get_slug();

		$this->assertStringEndsWith( '-factory-reset-website', $slug );
	}

	public function test_page_registers_under_tools_menu() {
		global $submenu;

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$tools_page = new ToolsPage();
		$tools_page->register_page();

		$slug  = ToolsPage::get_slug();
		$found = false;

		if ( ! empty( $submenu['tools.php'] ) ) {
			foreach ( $submenu['tools.php'] as $item ) {
				if ( $item[2] === $slug ) {
					$found = true;
					break;
				}
			}
		}

		$this->assertTrue( $found, 'Factory Reset page should be registered under Tools menu.' );
	}

	public function test_required_capability_is_manage_options() {
		global $submenu;

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$tools_page = new ToolsPage();
		$tools_page->register_page();

		$slug       = ToolsPage::get_slug();
		$capability = '';

		if ( ! empty( $submenu['tools.php'] ) ) {
			foreach ( $submenu['tools.php'] as $item ) {
				if ( $item[2] === $slug ) {
					$capability = $item[1];
					break;
				}
			}
		}

		$this->assertSame( 'manage_options', $capability );
	}

	public function test_nonce_action_constant_defined() {
		$this->assertSame( 'nfd_factory_reset_action', ToolsPage::NONCE_ACTION );
	}

	public function test_nonce_name_constant_defined() {
		$this->assertSame( 'nfd_factory_reset_nonce', ToolsPage::NONCE_NAME );
	}
}
