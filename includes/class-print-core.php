<?php
/**
 * Plugin bootstrap.
 *
 * @package WP-Print
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin into WordPress.
 *
 * Named Print_Core rather than the collection's usual bare-slug main class name
 * because `print` is a reserved word in PHP: `class Print` does not parse.
 */
class Print_Core {

	/**
	 * The query var, and the rewrite endpoint's name.
	 *
	 * @var string
	 */
	const QUERY_VAR = 'print';

	/**
	 * Singleton instance.
	 *
	 * @var Print_Core|null
	 */
	private static $instance = null;

	/**
	 * Get the instance, creating it on first call.
	 *
	 * @return Print_Core
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register hooks.
	 *
	 * The activation hook is registered here because the constructor runs at
	 * file-load time, which is where WordPress requires it.
	 */
	private function __construct() {
		register_activation_hook( WP_PRINT_MAIN_FILE, array( __CLASS__, 'activate' ) );

		add_action( 'init', array( __CLASS__, 'add_endpoint' ) );
		add_filter( 'query_vars', array( __CLASS__, 'add_query_var' ) );

		// Priority 5, ahead of redirect_canonical at 10.
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ), 5 );

		add_shortcode( 'print_link', array( 'Print_Link', 'shortcode' ) );
		add_shortcode( 'donotprint', array( 'Print_Link', 'donotprint_shortcode' ) );

		// Activation does not fire when a plugin is updated, so the migration also
		// runs on the first admin request after an upgrade.
		add_action( 'admin_init', array( 'Print_Options', 'maybe_upgrade' ) );
	}

	/**
	 * Register the /print/ endpoint on posts and pages.
	 *
	 * @return void
	 */
	public static function add_endpoint() {
		add_rewrite_endpoint( self::QUERY_VAR, EP_PERMALINK | EP_PAGES );
	}

	/**
	 * Make the query var public, so it survives without pretty permalinks.
	 *
	 * @param array $query_vars Public query vars.
	 * @return array
	 */
	public static function add_query_var( $query_vars ) {
		$query_vars[] = self::QUERY_VAR;

		return $query_vars;
	}

	/**
	 * Render the print view when the endpoint was requested.
	 *
	 * The endpoint's value is an empty string for a bare /print/ URL, so the test
	 * is array_key_exists() rather than isset(): isset() would be satisfied here,
	 * but empty() would not.
	 *
	 * @return void
	 */
	public static function maybe_render() {
		global $wp_query;

		if ( $wp_query instanceof WP_Query && array_key_exists( self::QUERY_VAR, $wp_query->query_vars ) ) {
			Print_Template::render();
		}
	}

	/**
	 * Set the plugin up on activation.
	 *
	 * @param bool $network_wide Whether the plugin is being activated network-wide.
	 * @return void
	 */
	public static function activate( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			// 'number' => 0 is required: WP_Site_Query defaults to 100, so without
			// it every site past the hundredth is left unconfigured while activation
			// still reports success. 'fields' => 'ids' avoids hydrating WP_Site
			// objects the loop does not use.
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::activate_site();
				// Inside the loop: switch_to_blog() pushes onto a stack, so
				// restoring once afterwards would leave it unwound by all but one.
				restore_current_blog();
			}

			return;
		}

		self::activate_site();
	}

	/**
	 * Set the plugin up on one site.
	 *
	 * @return void
	 */
	private static function activate_site() {
		add_option( Print_Options::OPTION_NAME, Print_Options::get_defaults() );

		Print_Options::maybe_upgrade();

		// The endpoint is registered on init, which has already run by the time an
		// activation request reaches this point, so the rules can be rebuilt now.
		flush_rewrite_rules();
	}
}
