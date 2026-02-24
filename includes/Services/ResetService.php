<?php

namespace NewfoldLabs\WP\Module\Reset\Services;

use NewfoldLabs\WP\Module\Reset\Data\BrandConfig;

/**
 * Core factory reset execution logic.
 *
 * The reset runs in two phases across two HTTP requests:
 *
 * Phase 1 (prepare): Runs in the original request while all plugins are still
 * loaded. Installs the target theme (safety gate), preserves critical values,
 * deactivates third-party plugins at the DB level, and deletes MU plugins and
 * drop-ins. Returns a data package for Phase 2.
 *
 * Phase 2 (execute): Runs on a fresh request where ONLY the brand plugin
 * is active. No third-party hooks, autoloaders, or shutdown handlers can
 * interfere. Deletes plugin/theme files, resets the database, and restores
 * the site to a fresh state.
 */
class ResetService {

	/**
	 * Phase 1: Prepare for the reset.
	 *
	 * Runs while all plugins are still loaded. Collects preservation data,
	 * installs the target theme, deactivates third-party plugins, and removes
	 * MU plugins/drop-ins so Phase 2 runs in a clean environment.
	 *
	 * @return array Preparation result with 'success', 'data', 'steps', and 'errors'.
	 */
	public static function prepare() {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		@set_time_limit( 300 );

		$steps  = array();
		$errors = array();

		// -------------------------------------------------------------------
		// Pre-flight checks.
		// -------------------------------------------------------------------
		if ( ! current_user_can( 'manage_options' ) ) {
			return array(
				'success' => false,
				'data'    => array(),
				'steps'   => array(),
				'errors'  => array( 'Unauthorized. You must be an administrator to perform a factory reset.' ),
			);
		}

		if ( is_multisite() ) {
			return array(
				'success' => false,
				'data'    => array(),
				'steps'   => array(),
				'errors'  => array( 'Factory reset is not supported on WordPress multisite installations.' ),
			);
		}

		// Initialize WP_Filesystem.
		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();

		if ( ! $wp_filesystem ) {
			return array(
				'success' => false,
				'data'    => array(),
				'steps'   => array(),
				'errors'  => array( 'Unable to initialize the WordPress filesystem.' ),
			);
		}

		// -------------------------------------------------------------------
		// Preserve values before anything changes.
		// -------------------------------------------------------------------
		$current_user = wp_get_current_user();

		// If current user ID is 0 (e.g. WP-CLI), find first administrator.
		if ( 0 === (int) $current_user->ID ) {
			$admins = get_users(
				array(
					'role'    => 'administrator',
					'number'  => 1,
					'orderby' => 'ID',
				)
			);
			if ( ! empty( $admins ) ) {
				$current_user = $admins[0];
			}
		}

		// Capture all preserved values (including the brand plugin basename).
		$data = ResetDataPreserver::capture( $current_user );

		// Brand plugin basename is required for Phase 1 step 2 (deactivate all
		// plugins except the brand plugin at the DB level).
		$brand_basename = isset( $data['brand_basename'] ) ? $data['brand_basename'] : BrandConfig::get_brand_plugin_basename();

		// -------------------------------------------------------------------
		// Install target theme (safety gate — abort if this fails).
		// -------------------------------------------------------------------
		$theme_result           = self::ensure_theme_installed( BrandConfig::get_default_theme_slug() );
		$steps['install_theme'] = $theme_result;

		if ( ! $theme_result['success'] ) {
			return array(
				'success' => false,
				'data'    => array(),
				'steps'   => $steps,
				'errors'  => array( 'Failed to install the default theme. Reset aborted with no changes made.' ),
			);
		}

		// -------------------------------------------------------------------
		// Deactivate all third-party plugins at the DB level.
		//
		// On the next request (Phase 2) WordPress will only load the brand
		// plugin. No third-party hooks, autoloaders, or shutdown handlers.
		// -------------------------------------------------------------------
		update_option( 'active_plugins', array( $brand_basename ) );
		$steps['deactivate_plugins'] = array(
			'success' => true,
			'message' => 'Third-party plugins deactivated.',
		);

		// -------------------------------------------------------------------
		// Delete MU plugins and drop-ins now so they are not loaded in Phase 2.
		// -------------------------------------------------------------------
		$steps['remove_mu_plugins'] = self::remove_mu_plugins();
		$steps['remove_dropins']    = self::remove_dropins();

		return array(
			'success' => true,
			'data'    => $data,
			'steps'   => $steps,
			'errors'  => $errors,
		);
	}

	/**
	 * Phase 2: Execute the destructive reset.
	 *
	 * Runs on a clean request with no third-party plugins loaded.
	 *
	 * @param array $data     Preserved data from Phase 1.
	 * @param array $p1_steps Steps already completed in Phase 1.
	 * @return array Result with 'success', 'steps', and 'errors'.
	 */
	public static function execute( $data, $p1_steps = array() ) {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		@set_time_limit( 300 );

		$steps  = $p1_steps;
		$errors = array();

		// Initialize WP_Filesystem.
		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();

		// -------------------------------------------------------------------
		// Harden environment (defense-in-depth).
		// -------------------------------------------------------------------
		$steps['harden_environment'] = self::harden_environment();

		// -------------------------------------------------------------------
		// Staging cleanup (handled by DB reset).
		// -------------------------------------------------------------------
		$steps['staging_cleanup'] = array(
			'success' => true,
			'message' => 'Staging data will be cleared with database reset.',
		);

		// -------------------------------------------------------------------
		// Delete third-party plugin files.
		// -------------------------------------------------------------------
		$steps['remove_plugins'] = ResetStepRunner::run(
			array( __CLASS__, 'remove_plugins' ),
			$data['brand_basename']
		);

		// -------------------------------------------------------------------
		// Delete third-party themes.
		// -------------------------------------------------------------------
		$steps['remove_themes'] = ResetStepRunner::run( array( __CLASS__, 'remove_themes' ) );

		// -------------------------------------------------------------------
		// Clean wp-content extras.
		// -------------------------------------------------------------------
		$steps['clean_wp_content'] = ResetStepRunner::run( array( __CLASS__, 'clean_wp_content' ) );

		// -------------------------------------------------------------------
		// Clean uploads.
		// -------------------------------------------------------------------
		$steps['clean_uploads'] = ResetStepRunner::run( array( __CLASS__, 'clean_uploads' ) );

		// -------------------------------------------------------------------
		// Reset database.
		// -------------------------------------------------------------------
		$steps['reset_database'] = ResetStepRunner::run(
			array( __CLASS__, 'reset_database' ),
			$data['blogname'],
			$data['user_login'],
			$data['user_email'],
			$data['blog_public'],
			$data['wplang']
		);

		if ( ! $steps['reset_database']['success'] ) {
			$errors[] = 'Database reset encountered errors.';
		}

		// -------------------------------------------------------------------
		// Restore NFD data options and enable coming soon.
		//
		// This MUST run before restore_values() which calls activate_plugin().
		// The token needs to be in the database before plugin activation hooks
		// fire, otherwise the data module may attempt a fresh connect() and
		// event listeners may send requests to Hiive without a valid token.
		// -------------------------------------------------------------------
		$steps['restore_nfd_data'] = ResetStepRunner::run( array( __CLASS__, 'restore_nfd_data' ), $data );

		// -------------------------------------------------------------------
		// Restore preserved values.
		// -------------------------------------------------------------------
		$steps['restore_values'] = ResetStepRunner::run(
			array( __CLASS__, 'restore_values' ),
			$steps['reset_database'],
			$data['user_pass'],
			$data['siteurl'],
			$data['home'],
			$data['brand_basename']
		);

		// -------------------------------------------------------------------
		// Reinstall WordPress core (fresh copy of current version).
		// -------------------------------------------------------------------
		$steps['reinstall_core'] = ResetStepRunner::run(
			array( __CLASS__, 'reinstall_core' )
		);

		// -------------------------------------------------------------------
		// Reinstall default theme (fresh copy from WordPress.org).
		// -------------------------------------------------------------------
		$steps['reinstall_theme'] = ResetStepRunner::run(
			array( __CLASS__, 'reinstall_theme' )
		);

		// -------------------------------------------------------------------
		// Verify fresh install state.
		// -------------------------------------------------------------------
		$steps['verify_fresh_install'] = ResetStepRunner::run(
			array( __CLASS__, 'verify_fresh_install' )
		);

		// -------------------------------------------------------------------
		// Restore session.
		// -------------------------------------------------------------------
		$user_id                  = isset( $steps['reset_database']['user_id'] )
			? $steps['reset_database']['user_id']
			: 1;
		$steps['restore_session'] = ResetStepRunner::run(
			array( __CLASS__, 'restore_session' ),
			$user_id
		);

		// Restore normal error handling.
		self::restore_environment();

		// Collect errors from steps.
		foreach ( $steps as $step_name => $step_result ) {
			if ( ! $step_result['success'] && ! empty( $step_result['message'] ) ) {
				$errors[] = $step_name . ': ' . $step_result['message'];
			}
		}

		$has_critical_failure = ! $steps['reset_database']['success'];

		return array(
			'success' => ! $has_critical_failure,
			'steps'   => $steps,
			'errors'  => $errors,
		);
	}

	// =====================================================================
	// Helpers
	// =====================================================================

	// NOTE: Step execution is now handled by ResetStepRunner::run() so that
	// all services share a single, well-documented execution helper.

	/**
	 * Harden the PHP environment as defense-in-depth.
	 *
	 * Phase 2 already runs without third-party plugins, but this provides
	 * extra safety against edge cases (e.g. leftover autoloaders, shutdown
	 * functions registered by plugins before deactivation took effect).
	 *
	 * @return array Step result.
	 */
	private static function harden_environment() {
		global $wpdb;

		// Prevent ALL email during reset.
		add_filter( 'pre_wp_mail', '__return_true' );

		// Suppress DB error output.
		$wpdb->suppress_errors( true );
		$wpdb->show_errors = false;

		// Swallow PHP warnings/notices.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		set_error_handler( '__return_true' );

		// Remove all shutdown hooks to prevent leftover plugin code from
		// running during PHP shutdown (e.g. Action Scheduler).
		remove_all_actions( 'shutdown' );

		return array(
			'success' => true,
			'message' => 'Site ready to be reset.',
		);
	}

	/**
	 * Restore normal error handling.
	 */
	private static function restore_environment() {
		global $wpdb;

		restore_error_handler();
		$wpdb->suppress_errors( false );
		$wpdb->show_errors = defined( 'WP_DEBUG' ) && WP_DEBUG;
		remove_filter( 'pre_wp_mail', '__return_true' );
	}

	// =====================================================================
	// Individual step implementations
	// =====================================================================

	/**
	 * Ensure a theme is installed (install from WordPress.org if missing).
	 *
	 * @param string $theme_slug The theme slug.
	 * @return array Step result.
	 */
	private static function ensure_theme_installed( $theme_slug ) {
		$theme = wp_get_theme( $theme_slug );

		if ( $theme->exists() ) {
			return array(
				'success' => true,
				'message' => 'Theme already installed.',
			);
		}

		if ( ! function_exists( 'themes_api' ) ) {
			require_once ABSPATH . 'wp-admin/includes/theme.php';
		}

		$api = themes_api(
			'theme_information',
			array(
				'slug'   => $theme_slug,
				'fields' => array( 'sections' => false ),
			)
		);

		if ( is_wp_error( $api ) ) {
			return array(
				'success' => false,
				'message' => 'Could not fetch theme information: ' . $api->get_error_message(),
			);
		}

		if ( ! class_exists( 'Theme_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}

		$skin     = new \Automatic_Upgrader_Skin();
		$upgrader = new \Theme_Upgrader( $skin );
		$result   = $upgrader->install( $api->download_link );

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => 'Theme installation failed: ' . $result->get_error_message(),
			);
		}

		if ( ! $result ) {
			return array(
				'success' => false,
				'message' => 'Theme installation failed.',
			);
		}

		return array(
			'success' => true,
			'message' => 'Theme installed successfully.',
		);
	}

	/**
	 * Remove all plugins except the brand plugin.
	 *
	 * Uses direct filesystem deletion — no uninstall hooks (we drop all
	 * tables anyway so they are pointless and dangerous).
	 *
	 * @param string $brand_basename The brand plugin basename to preserve.
	 * @return array Step result.
	 */
	public static function remove_plugins( $brand_basename ) {
		global $wp_filesystem;

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins       = get_plugins();
		$plugins_to_remove = array();

		foreach ( $all_plugins as $basename => $plugin_data ) {
			if ( $basename === $brand_basename ) {
				continue;
			}
			$plugins_to_remove[] = $basename;
		}

		if ( empty( $plugins_to_remove ) ) {
			return array(
				'success' => true,
				'message' => 'No third-party plugins to remove.',
			);
		}

		$removed = 0;
		$errors  = array();

		foreach ( $plugins_to_remove as $basename ) {
			$plugin_dir = dirname( $basename );
			if ( '.' === $plugin_dir ) {
				$path = WP_PLUGIN_DIR . '/' . $basename;
			} else {
				$path = WP_PLUGIN_DIR . '/' . $plugin_dir;
			}

			if ( $wp_filesystem->exists( $path ) ) {
				$deleted = $wp_filesystem->delete( $path, true );
				if ( $deleted ) {
					++$removed;
				} else {
					$errors[] = $basename;
				}
			} else {
				++$removed;
			}
		}

		$total = $removed + count( $errors );

		if ( ! empty( $errors ) ) {
			return array(
				'success' => false,
				'message' => sprintf( 'Removed %d of %d plugin(s). Failed to remove: %s', $removed, $total, implode( ', ', $errors ) ),
			);
		}

		return array(
			'success' => true,
			'message' => sprintf( 'Removed %d plugin(s).', $removed ),
		);
	}

	/**
	 * Remove all themes except the brand default theme.
	 *
	 * @return array Step result.
	 */
	public static function remove_themes() {
		$default_theme = BrandConfig::get_default_theme_slug();
		switch_theme( $default_theme );

		$all_themes    = wp_get_themes();
		$removed_count = 0;
		$errors        = array();

		foreach ( $all_themes as $stylesheet => $theme ) {
			if ( $default_theme === $stylesheet ) {
				continue;
			}

			$result = delete_theme( $stylesheet );

			if ( is_wp_error( $result ) ) {
				$errors[] = $stylesheet . ': ' . $result->get_error_message();
			} else {
				++$removed_count;
			}
		}

		$total = $removed_count + count( $errors );

		if ( ! empty( $errors ) ) {
			return array(
				'success' => false,
				'message' => sprintf( 'Removed %d of %d theme(s). Failed to remove: %s', $removed_count, $total, implode( '; ', $errors ) ),
			);
		}

		return array(
			'success' => true,
			'message' => sprintf( 'Removed %d theme(s).', $removed_count ),
		);
	}

	/**
	 * Remove all MU plugins.
	 *
	 * @return array Step result.
	 */
	private static function remove_mu_plugins() {
		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			WP_Filesystem();
		}

		$mu_plugin_dir = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';

		if ( ! $wp_filesystem->is_dir( $mu_plugin_dir ) ) {
			return array(
				'success' => true,
				'message' => 'No MU plugins directory found.',
			);
		}

		$entries       = $wp_filesystem->dirlist( $mu_plugin_dir );
		$removed_count = 0;

		if ( ! empty( $entries ) ) {
			foreach ( $entries as $name => $info ) {
				$wp_filesystem->delete( $mu_plugin_dir . '/' . $name, true );
				++$removed_count;
			}
		}

		return array(
			'success' => true,
			'message' => sprintf( 'Removed %d MU plugin file(s)/folder(s).', $removed_count ),
		);
	}

	/**
	 * Remove WordPress drop-in files.
	 *
	 * @return array Step result.
	 */
	private static function remove_dropins() {
		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			WP_Filesystem();
		}

		$dropin_files = array(
			'advanced-cache.php',
			'db.php',
			'db-error.php',
			'install.php',
			'maintenance.php',
			'object-cache.php',
			'php-error.php',
			'fatal-error-handler.php',
			'sunrise.php',
		);

		$removed_count = 0;
		$content_dir   = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content';

		foreach ( $dropin_files as $file ) {
			$path = $content_dir . '/' . $file;
			if ( $wp_filesystem->exists( $path ) ) {
				$wp_filesystem->delete( $path );
				++$removed_count;
			}
		}

		return array(
			'success' => true,
			'message' => sprintf( 'Removed %d drop-in file(s).', $removed_count ),
		);
	}

	/**
	 * Clean extra directories and files from wp-content.
	 *
	 * @return array Step result.
	 */
	public static function clean_wp_content() {
		global $wp_filesystem;

		$content_dir = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content';

		$exclude = array(
			'plugins',
			'themes',
			'mu-plugins',
			'uploads',
			'index.php',
		);

		$entries       = $wp_filesystem->dirlist( $content_dir );
		$removed_count = 0;

		if ( ! empty( $entries ) ) {
			foreach ( $entries as $name => $info ) {
				if ( in_array( $name, $exclude, true ) ) {
					continue;
				}
				$wp_filesystem->delete( $content_dir . '/' . $name, true );
				++$removed_count;
			}
		}

		return array(
			'success' => true,
			'message' => sprintf( 'Removed %d extra wp-content item(s).', $removed_count ),
		);
	}

	/**
	 * Clean the uploads directory.
	 *
	 * @return array Step result.
	 */
	public static function clean_uploads() {
		global $wp_filesystem;

		$upload_dir = wp_get_upload_dir();
		$basedir    = $upload_dir['basedir'];

		if ( $wp_filesystem->is_dir( $basedir ) ) {
			$entries = $wp_filesystem->dirlist( $basedir );

			if ( ! empty( $entries ) ) {
				foreach ( $entries as $name => $info ) {
					$wp_filesystem->delete( $basedir . '/' . $name, true );
				}
			}
		}

		wp_mkdir_p( $basedir );

		return array(
			'success' => true,
			'message' => 'Uploads directory cleaned.',
		);
	}

	/**
	 * Reset the database: drop all tables and reinstall WordPress.
	 *
	 * @param string $blogname   The site title.
	 * @param string $user_login Admin username.
	 * @param string $user_email Admin email.
	 * @param string $blog_public Whether the site is public.
	 * @param string $wplang     Site language.
	 * @return array Step result with user_id.
	 */
	public static function reset_database( $blogname, $user_login, $user_email, $blog_public, $wplang ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'SET foreign_key_checks = 0' );

		$prefix = str_replace( '_', '\_', $wpdb->prefix );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $prefix . '%' ) );

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'SET foreign_key_checks = 1' );

		if ( ! function_exists( 'wp_install' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		// wp_install() sends a notification email near the end, after all DB
		// work is done. If the email fails (pre_wp_mail filter, or any other
		// reason), we catch the error and recover the user ID.
		try {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_token
			$result = wp_install( $blogname, $user_login, $user_email, $blog_public, '', md5( wp_rand() ), $wplang );
		} catch ( \Throwable $e ) {
			$user = get_user_by( 'login', $user_login );

			return array(
				'success' => true,
				'message' => 'Database reset (email notification skipped: ' . $e->getMessage() . ').',
				'user_id' => $user ? $user->ID : 1,
			);
		}

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => 'wp_install failed: ' . $result->get_error_message(),
				'user_id' => 0,
			);
		}

		return array(
			'success' => true,
			'message' => 'Database reset and WordPress reinstalled.',
			'user_id' => $result['user_id'],
		);
	}

	/**
	 * Restore preserved values after database reset.
	 *
	 * @param array  $db_result     Result from reset_database.
	 * @param string $old_user_pass The original password hash.
	 * @param string $siteurl       The original siteurl.
	 * @param string $home          The original home URL.
	 * @param string $brand_basename The brand plugin basename.
	 * @return array Step result.
	 */
	public static function restore_values( $db_result, $old_user_pass, $siteurl, $home, $brand_basename ) {
		global $wpdb;

		if ( ! $db_result['success'] || empty( $db_result['user_id'] ) ) {
			return array(
				'success' => false,
				'message' => 'Cannot restore values: database reset did not complete.',
			);
		}

		$user_id = $db_result['user_id'];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->users,
			array(
				'user_pass'           => $old_user_pass,
				'user_activation_key' => '',
			),
			array( 'ID' => $user_id )
		);

		update_user_meta( $user_id, 'default_password_nag', false );
		update_option( 'siteurl', $siteurl );
		update_option( 'home', $home );
		switch_theme( BrandConfig::get_default_theme_slug() );

		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		activate_plugin( $brand_basename );

		return array(
			'success' => true,
			'message' => 'Preserved values restored.',
		);
	}

	/**
	 * Reinstall WordPress core (fresh copy of the current version).
	 *
	 * @return array Step result.
	 */
	public static function reinstall_core() {
		if ( ! function_exists( 'wp_version_check' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}
		if ( ! class_exists( 'Core_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}

		wp_version_check();
		$updates = get_core_updates();

		if ( empty( $updates ) || is_wp_error( $updates ) ) {
			$msg = is_wp_error( $updates ) ? $updates->get_error_message() : 'No core update offers available.';
			return array(
				'success' => false,
				'message' => $msg,
			);
		}

		$update           = $updates[0];
		$update->response = 'reinstall';

		$skin     = new SilentUpgraderSkin();
		$upgrader = new \Core_Upgrader( $skin );
		$result   = $upgrader->upgrade( $update );

		$errors = $skin->errors_captured;

		if ( is_wp_error( $result ) ) {
			$errors = array_merge( $errors, $result->get_error_messages() );
		}

		if ( is_wp_error( $result ) || false === $result || ! empty( $errors ) ) {
			return array(
				'success' => false,
				'message' => 'Core reinstall failed: ' . ( ! empty( $errors ) ? implode( '; ', $errors ) : 'Unknown error.' ),
			);
		}

		return array(
			'success' => true,
			'message' => 'WordPress core reinstalled.',
		);
	}

	/**
	 * Reinstall the default theme with a fresh copy from WordPress.org.
	 *
	 * @return array Step result.
	 */
	public static function reinstall_theme() {
		global $wp_filesystem;

		$theme_slug = BrandConfig::get_default_theme_slug();
		$theme_dir  = get_theme_root() . '/' . $theme_slug;

		// Delete existing copy so ensure_theme_installed fetches a fresh one.
		if ( $wp_filesystem->is_dir( $theme_dir ) ) {
			$wp_filesystem->delete( $theme_dir, true );
		}

		$result = self::ensure_theme_installed( $theme_slug );

		if ( ! $result['success'] ) {
			return array(
				'success' => false,
				'message' => 'Theme reinstall failed: ' . $result['message'],
			);
		}

		// Re-activate since we deleted and reinstalled.
		switch_theme( $theme_slug );

		return array(
			'success' => true,
			'message' => 'Default theme reinstalled.',
		);
	}

	/**
	 * Restore Hiive/NFD data options and enable coming soon mode.
	 *
	 * @param array $data Preserved data from Phase 1.
	 * @return array Step result.
	 */
	public static function restore_nfd_data( $data ) {
		// Delegated to ResetDataPreserver for clearer separation of concerns.
		return ResetDataPreserver::restore_nfd_data( $data );
	}

	/**
	 * Restore auth session.
	 *
	 * @param int $user_id The admin user ID.
	 * @return array Step result.
	 */
	public static function restore_session( $user_id ) {
		wp_clear_auth_cookie();
		wp_set_auth_cookie( $user_id );

		return array(
			'success' => true,
			'message' => 'Session restored.',
		);
	}

	/**
	 * Verify the site looks like a fresh install.
	 *
	 * @return array Step result.
	 */
	public static function verify_fresh_install() {
		$failures = array();

		$oldest_post = get_posts(
			array(
				'post_type'           => array( 'post', 'page' ),
				'post_status'         => 'any',
				'orderby'             => 'ID',
				'order'               => 'ASC',
				'posts_per_page'      => 1,
				'ignore_sticky_posts' => true,
				'suppress_filters'    => true,
			)
		);

		if ( empty( $oldest_post ) || 1 !== (int) $oldest_post[0]->ID ) {
			$failures[] = 'Oldest post ID is not 1';
		}

		$newest_post = get_posts(
			array(
				'post_type'           => array( 'post', 'page' ),
				'post_status'         => 'any',
				'orderby'             => 'ID',
				'order'               => 'DESC',
				'posts_per_page'      => 1,
				'ignore_sticky_posts' => true,
				'suppress_filters'    => true,
			)
		);

		if ( ! empty( $oldest_post ) && ! empty( $newest_post ) ) {
			if ( $oldest_post[0]->post_modified_gmt !== $newest_post[0]->post_modified_gmt ) {
				$failures[] = 'Oldest and newest posts have different modification times';
			}
		}

		$user = get_userdata( 1 );
		if ( ! $user ) {
			$failures[] = 'User with ID 1 does not exist';
		}

		$user_count = count_users();
		$total      = isset( $user_count['total_users'] ) ? (int) $user_count['total_users'] : 0;
		if ( 1 !== $total ) {
			$failures[] = sprintf( 'Expected 1 user, found %d', $total );
		}

		if ( ! empty( $failures ) ) {
			return array(
				'success' => false,
				'message' => 'Fresh install check failed: ' . implode( '; ', $failures ),
			);
		}

		return array(
			'success' => true,
			'message' => 'Site passes fresh install detection.',
		);
	}
}
