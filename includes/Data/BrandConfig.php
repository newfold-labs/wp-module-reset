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
	 * Get the default theme slug for the factory reset.
	 *
	 * Returns WordPress core's own default theme for the running version, via
	 * the core `WP_DEFAULT_THEME` constant (e.g. `twentytwentyfive` on WP 6.7+,
	 * `twentytwentyfour` on 6.4–6.6, and so on). That is the theme core itself
	 * activates on a fresh install and falls back to when the active theme is
	 * broken, so restoring it is the most literal "return to stock WordPress."
	 *
	 * Using the core constant rather than a fixed slug is deliberate:
	 *
	 * - A factory reset must wipe the slate clean and work on any site, old or
	 *   new. `WP_DEFAULT_THEME` is version-matched to the site, so it is always
	 *   bundled with that core and always compatible — the reset never installs
	 *   a theme that requires a newer WordPress than the site runs. (A fixed
	 *   `twentytwentyfive` would require WP 6.7+ and break resets on older
	 *   sites.) On the common path it resolves locally with no WordPress.org
	 *   fetch at all.
	 * - Applying a brand theme is onboarding's job, not the reset's. Onboarding
	 *   re-runs after a reset and installs whatever theme the brand wants.
	 * - Being brand-agnostic removes a whole class of bug: a per-brand theme
	 *   slug that is a placeholder, gets deprecated, or is delisted from
	 *   WordPress.org would silently break the reset (see PRESS0-5302, where
	 *   HostGator pointed at a non-existent "flavor" theme).
	 *
	 * `WP_DEFAULT_THEME` has shipped with core since WordPress 3.0 and is
	 * always defined by the time this runs (set during the WordPress bootstrap,
	 * before any plugin, REST, or admin-page code), so no fallback is needed.
	 *
	 * @return string
	 */
	public static function get_default_theme_slug() {
		return WP_DEFAULT_THEME;
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
