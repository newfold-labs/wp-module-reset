<?php

namespace NewfoldLabs\WP\Module\Reset\Tests\WPUnit;

use lucatume\WPBrowser\TestCase\WPTestCase;
use NewfoldLabs\WP\Module\Reset\Admin\ToolsPage;
use NewfoldLabs\WP\Module\Reset\Data\BrandConfig;
use ReflectionMethod;

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

	/**
	 * Test format_step_name returns mapped label for known step.
	 */
	public function test_format_step_name_returns_mapped_label_for_known_step() {
		$method = new ReflectionMethod( ToolsPage::class, 'format_step_name' );
		$method->setAccessible( true );

		$this->assertSame( 'Install default theme', $method->invoke( null, 'install_theme' ) );
		$this->assertSame( 'Reset database', $method->invoke( null, 'reset_database' ) );
		$this->assertSame( 'Restore hosting connection', $method->invoke( null, 'restore_nfd_data' ) );
	}

	/**
	 * Test format_step_name returns ucwords fallback for unknown step.
	 */
	public function test_format_step_name_returns_ucwords_for_unknown_step() {
		$method = new ReflectionMethod( ToolsPage::class, 'format_step_name' );
		$method->setAccessible( true );

		$this->assertSame( 'Unknown Step Name', $method->invoke( null, 'unknown_step_name' ) );
	}

	/**
	 * Regression: confirmation page must not mention MU plugins / drop-in files.
	 */
	public function test_confirmation_output_does_not_contain_mu_plugins_line() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$page = new ToolsPage();
		$page->register_page();

		ob_start();
		$page->render_page();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'MU plugins', $output );
		$this->assertStringNotContainsString( 'drop-in files', $output );
	}
}
