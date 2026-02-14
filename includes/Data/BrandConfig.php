<?php

namespace NewfoldLabs\WP\Module\Reset\Data;

use function NewfoldLabs\WP\ModuleLoader\container;

/**
 * Brand configuration accessor.
 *
 * Provides brand-specific values (slug, theme, basename, display name)
 * derived from the module loader container so the reset module works
 * for any Newfold brand without hardcoded references.
 */
class BrandConfig {

	/**
	 * Default theme slug per brand.
	 *
	 * @var array<string, string>
	 */
	private static $themes = array(
		'bluehost'  => 'bluehost-blueprint',
		'hostgator' => 'flavor',
	);

	/**
	 * Get the default theme slug for the current brand.
	 *
	 * @return string
	 */
	public static function get_default_theme_slug() {
		$brand = self::get_brand_id();
		return isset( self::$themes[ $brand ] ) ? self::$themes[ $brand ] : 'flavor';
	}

	/**
	 * Get the brand identifier (e.g. 'bluehost', 'hostgator').
	 *
	 * @return string
	 */
	public static function get_brand_id() {
		try {
			return container()->plugin()->id;
		} catch ( \Throwable $e ) {
			return 'bluehost';
		}
	}

	/**
	 * Get the brand plugin basename (e.g. 'bluehost-wordpress-plugin/bluehost-wordpress-plugin.php').
	 *
	 * @return string
	 */
	public static function get_brand_plugin_basename() {
		try {
			return container()->plugin()->basename;
		} catch ( \Throwable $e ) {
			return '';
		}
	}

	/**
	 * Get the brand display name (e.g. 'The Bluehost Plugin').
	 *
	 * @return string
	 */
	public static function get_brand_name() {
		try {
			return container()->plugin()->name;
		} catch ( \Throwable $e ) {
			return '';
		}
	}

	/**
	 * Get the tools page slug (e.g. 'bluehost-factory-reset-website').
	 *
	 * @return string
	 */
	public static function get_page_slug() {
		return self::get_brand_id() . '-factory-reset-website';
	}

	/**
	 * Get the REST API namespace (e.g. 'bluehost/v1').
	 *
	 * @return string
	 */
	public static function get_rest_namespace() {
		return self::get_brand_id() . '/v1';
	}
}
