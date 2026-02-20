<?php

namespace NewfoldLabs\WP\Module\Reset\Tests\WPUnit;

use lucatume\WPBrowser\TestCase\WPTestCase;
use NewfoldLabs\WP\Module\Reset\Services\ResetService;
use NewfoldLabs\WP\Module\Reset\Data\BrandConfig;

/**
 * Test the destructive ResetService operations.
 *
 * These tests call reset_database() and the full prepare() → execute() flow.
 * After each destructive test, the site is left in a fresh WordPress install
 * state with the brand plugin active — which is the expected clean state.
 *
 * IMPORTANT: This file must be loaded LAST among test files because the
 * database reset drops all tables. Codeception loads files alphabetically,
 * and "ResetServiceExecute" sorts after all other test files.
 */
class ResetServiceExecuteWPUnitTest extends WPTestCase {

	/**
	 * Path to fake theme directory.
	 *
	 * @var string
	 */
	private $fake_theme_dir = '';

	public function setUp(): void {
		parent::setUp();

		// Ensure admin includes are available.
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Create a minimal theme so the reset flow's ensure_theme_installed()
		// short-circuits without hitting the WordPress.org API.
		$theme_slug           = BrandConfig::get_default_theme_slug();
		$this->fake_theme_dir = get_theme_root() . '/' . $theme_slug;

		if ( ! is_dir( $this->fake_theme_dir ) ) {
			mkdir( $this->fake_theme_dir, 0755, true );
		}
		file_put_contents(
			$this->fake_theme_dir . '/style.css',
			"/*\nTheme Name: Test Stub\n*/"
		);
		if ( ! file_exists( $this->fake_theme_dir . '/index.php' ) ) {
			file_put_contents( $this->fake_theme_dir . '/index.php', '<?php' );
		}

		// Block outbound HTTP as a safety net.
		add_filter( 'pre_http_request', array( $this, 'block_http' ), 10, 3 );
	}

	public function tearDown(): void {
		remove_filter( 'pre_http_request', array( $this, 'block_http' ), 10 );

		// Ensure the fake brand theme dir exists for the next test.
		if ( ! is_dir( $this->fake_theme_dir ) ) {
			mkdir( $this->fake_theme_dir, 0755, true );
			file_put_contents(
				$this->fake_theme_dir . '/style.css',
				"/*\nTheme Name: Test Stub\n*/"
			);
			file_put_contents( $this->fake_theme_dir . '/index.php', '<?php' );
		}

		// Restore the "default" theme that WPLoader needs for bootstrapping.
		$default_dir = get_theme_root() . '/default';
		if ( ! is_dir( $default_dir ) ) {
			mkdir( $default_dir, 0755, true );
			file_put_contents( $default_dir . '/style.css', "/*\nTheme Name: Default\n*/" );
			file_put_contents( $default_dir . '/index.php', '<?php' );
		}

		parent::tearDown();
	}

	/**
	 * Short-circuit all outbound HTTP requests.
	 *
	 * @return array
	 */
	public function block_http() {
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => '',
		);
	}

	// ------------------------------------------------------------------
	// reset_database() — the core destructive operation
	// ------------------------------------------------------------------

	public function test_reset_database_drops_tables_and_reinstalls() {
		global $wpdb;

		// Create a custom table to prove it gets dropped.
		// phpcs:ignore
		$wpdb->query( "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}test_reset_proof` (id INT)" );

		$blogname   = get_option( 'blogname' );
		$user       = wp_get_current_user();
		$user_login = $user->user_login ?: 'admin';
		$user_email = $user->user_email ?: 'admin@example.org';

		// If no user exists yet (fresh test DB), create one.
		if ( 0 === (int) $user->ID ) {
			$admin_id = self::factory()->user->create(
				array(
					'role'       => 'administrator',
					'user_login' => 'testadmin',
					'user_email' => 'testadmin@example.org',
				)
			);
			wp_set_current_user( $admin_id );
			$user_login = 'testadmin';
			$user_email = 'testadmin@example.org';
		}

		// Suppress email during wp_install.
		add_filter( 'pre_wp_mail', '__return_true' );

		$result = $this->call_private(
			'reset_database',
			$blogname ?: 'Test Site',
			$user_login,
			$user_email,
			'1',
			''
		);

		remove_filter( 'pre_wp_mail', '__return_true' );

		$this->assertTrue( $result['success'], 'reset_database should succeed: ' . ( $result['message'] ?? '' ) );
		$this->assertNotEmpty( $result['user_id'], 'Should return a valid user_id.' );

		// Verify the custom table was dropped.
		// phpcs:ignore
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$wpdb->prefix . 'test_reset_proof'
			)
		);
		$this->assertNull( $table_exists, 'Custom table should have been dropped.' );

		// Verify WP was reinstalled — core tables exist.
		// phpcs:ignore
		$posts_table = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'posts' )
		);
		$this->assertNotNull( $posts_table, 'wp_posts table should exist after reinstall.' );
	}

	// ------------------------------------------------------------------
	// restore_values() — after a real database reset
	// ------------------------------------------------------------------

	public function test_restore_values_restores_password_and_urls() {
		global $wpdb;

		// First reset the database to get a clean slate.
		add_filter( 'pre_wp_mail', '__return_true' );
		$db_result = $this->call_private(
			'reset_database',
			'Test Site',
			'resetadmin',
			'reset@example.org',
			'1',
			''
		);
		remove_filter( 'pre_wp_mail', '__return_true' );

		$this->assertTrue( $db_result['success'] );

		$fake_pass_hash = '$P$BfakePasswordHashForTesting123';
		$siteurl        = 'http://test-reset.example.com';
		$home           = 'http://test-reset.example.com';

		$result = $this->call_private(
			'restore_values',
			$db_result,
			$fake_pass_hash,
			$siteurl,
			$home,
			'fake-brand/fake-brand.php'
		);

		$this->assertTrue( $result['success'] );

		// Verify password was updated.
		$user = get_user_by( 'id', $db_result['user_id'] );
		$this->assertSame( $fake_pass_hash, $user->user_pass );

		// Verify URLs were restored.
		$this->assertSame( $siteurl, get_option( 'siteurl' ) );
		$this->assertSame( $home, get_option( 'home' ) );
	}

	// ------------------------------------------------------------------
	// Full prepare() → execute() integration test
	// ------------------------------------------------------------------

	public function test_full_reset_flow_leaves_clean_install() {
		// Set up an admin user.
		$admin_id = self::factory()->user->create(
			array(
				'role'       => 'administrator',
				'user_login' => 'integrationadmin',
				'user_email' => 'integration@example.org',
			)
		);
		wp_set_current_user( $admin_id );

		// Seed some data that should be wiped.
		self::factory()->post->create_many( 5 );
		update_option( 'nfd_data_token', 'test-token-xyz' );

		// Suppress email and HTTP.
		add_filter( 'pre_wp_mail', '__return_true' );

		// Phase 1: Prepare.
		$preparation = ResetService::prepare();
		$this->assertTrue( $preparation['success'], 'prepare() should succeed.' );

		// Phase 2: Execute.
		$result = ResetService::execute( $preparation['data'], $preparation['steps'] );

		remove_filter( 'pre_wp_mail', '__return_true' );

		$this->assertTrue( $result['success'], 'execute() should succeed. Errors: ' . implode( '; ', $result['errors'] ?? array() ) );

		// Verify key steps completed.
		$this->assertArrayHasKey( 'reset_database', $result['steps'] );
		$this->assertTrue( $result['steps']['reset_database']['success'] );
		$this->assertArrayHasKey( 'restore_values', $result['steps'] );
		$this->assertTrue( $result['steps']['restore_values']['success'] );
		$this->assertArrayHasKey( 'restore_nfd_data', $result['steps'] );
		$this->assertTrue( $result['steps']['restore_nfd_data']['success'] );
		$this->assertArrayHasKey( 'restore_session', $result['steps'] );
		$this->assertTrue( $result['steps']['restore_session']['success'] );
		$this->assertArrayHasKey( 'harden_environment', $result['steps'] );
		$this->assertTrue( $result['steps']['harden_environment']['success'] );

		// Core tables should exist.
		global $wpdb;
		// phpcs:ignore
		$posts_table = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'posts' )
		);
		$this->assertNotNull( $posts_table );

		// NFD token should have been restored.
		$this->assertSame( 'test-token-xyz', get_option( 'nfd_data_token' ) );

		// Coming soon should be enabled.
		$this->assertTrue( (bool) get_option( 'nfd_coming_soon' ) );

		// Admin user should exist.
		$user = get_user_by( 'login', 'integrationadmin' );
		$this->assertNotFalse( $user );
	}

	// ------------------------------------------------------------------
	// Helper
	// ------------------------------------------------------------------

	/**
	 * Call a private static method on ResetService.
	 *
	 * @param string $method Method name.
	 * @param mixed  ...$args Arguments.
	 * @return mixed
	 */
	private function call_private( string $method, ...$args ) {
		$ref = new \ReflectionMethod( ResetService::class, $method );
		$ref->setAccessible( true );

		return $ref->invokeArgs( null, $args );
	}
}
