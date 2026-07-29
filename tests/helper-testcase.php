<?php
/**
 * Shared base class for the WP-Print test cases.
 *
 * @package WP-Print
 */

/**
 * Starts every test from an install that has never seen the plugin.
 */
abstract class WP_Print_TestCase extends WP_UnitTestCase {

	/**
	 * Clear the plugin's four option rows and the footnote accumulator.
	 *
	 * All four, not just the two the plugin writes today: a test that leaves a
	 * legacy row behind makes the next test's migration do something it was not
	 * asked to, and the failure lands in whichever test happens to run second.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		delete_option( WP_Print_Options::OPTION );
		delete_option( WP_Print_Options::VERSION );
		delete_option( WP_Print_Options::LEGACY_OPTION );
		delete_option( WP_Print_Options::LEGACY_VERSION );

		WP_Print_Content::reset();
	}

	/**
	 * Store the shipped defaults with some keys overridden.
	 *
	 * @param array $overrides Keys to change.
	 * @return void
	 */
	protected function set_options( array $overrides = array() ) {
		update_option( WP_Print_Options::OPTION, array_merge( WP_Print_Options::get_defaults(), $overrides ) );
	}

	/**
	 * Every option row the plugin owns, read straight from the table.
	 *
	 * A LIKE over wp_options rather than a list of delete_option() checks: a row
	 * added later and forgotten in uninstall.php is exactly the failure this is
	 * here to catch, and naming the rows would miss it.
	 *
	 * @return string[]
	 */
	protected function stored_option_names() {
		global $wpdb;

		return (array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( 'wp_print_' ) . '%',
				$wpdb->esc_like( 'print_' ) . '%'
			)
		);
	}

	/**
	 * Run the uninstaller.
	 *
	 * The uninstaller does its work in the file body, and PHP will not run a
	 * file body twice -- so the first caller in a process gets the real thing
	 * and any later one would silently get nothing at all. The require is
	 * therefore only there to guarantee the function exists, and the fan-out is
	 * driven from here: the same loop the file itself runs, with the same
	 * arguments. That uninstall.php's own copy of it is correct is asserted
	 * against the source in test-source.php, which is where it belongs -- a
	 * single-site suite cannot build a 101-site network to prove the cap is
	 * lifted.
	 *
	 * @return void
	 */
	protected function run_uninstall() {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'wp-print/wp-print.php' );
		}

		require_once WP_PRINT_DIR . 'uninstall.php';

		if ( is_multisite() ) {
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				wp_print_uninstall_site();
				restore_current_blog();
			}

			return;
		}

		wp_print_uninstall_site();
	}
}
