<?php
/**
 * Activation and uninstall.
 *
 * @package WP-Print
 */

/**
 * Tests for the plugin lifecycle.
 *
 * @covers Print_Core
 */
class Test_Print_Lifecycle extends WP_UnitTestCase {

	/**
	 * Start from an install that has never seen the plugin.
	 */
	public function set_up() {
		parent::set_up();

		delete_option( Print_Options::OPTION_NAME );
		delete_option( Print_Options::DB_VERSION_NAME );
	}

	/**
	 * Activation seeds the defaults and records the schema version, so the first
	 * page load after activating has a complete row to read.
	 */
	public function test_activation_seeds_the_defaults() {
		Print_Core::activate();

		$stored = get_option( Print_Options::OPTION_NAME );

		$this->assertIsArray( $stored );
		$this->assertSame( 'print.gif', $stored['print_icon'] );
		$this->assertSame( WP_PRINT_DB_VERSION, get_option( Print_Options::DB_VERSION_NAME ) );
	}

	/**
	 * Reactivating must not reset a configured site. add_option() does not
	 * overwrite, which is what makes deactivate/reactivate safe.
	 */
	public function test_activation_does_not_overwrite_existing_settings() {
		update_option(
			Print_Options::OPTION_NAME,
			array_merge(
				Print_Options::get_defaults(),
				array(
					'post_text'   => 'Mine',
					'print_style' => 3,
				)
			)
		);

		Print_Core::activate();

		$this->assertSame( 'Mine', Print_Options::get( 'post_text' ) );
		$this->assertSame( 3, (int) Print_Options::get( 'print_style' ) );
	}

	/**
	 * Activation runs the migration too, so an install upgraded from before 3.0.0
	 * is fixed up whichever way it gets there.
	 */
	public function test_activation_runs_the_migration() {
		update_option( Print_Options::OPTION_NAME, array( 'post_text' => "Tom & Jerry\\'s Post" ) );

		Print_Core::activate();

		$this->assertSame( "Tom & Jerry's Post", Print_Options::get( 'post_text' ) );
		$this->assertSame( WP_PRINT_DB_VERSION, get_option( Print_Options::DB_VERSION_NAME ) );
	}

	/**
	 * Activation leaves the rewrite rules carrying the print endpoint, which is
	 * what stops a fresh install 404ing until permalinks are saved by hand.
	 */
	public function test_activation_leaves_the_endpoint_in_the_rules() {
		global $wp_rewrite;

		$this->set_permalink_structure( '/%postname%/' );
		Print_Core::add_endpoint();

		Print_Core::activate();

		$print_rules = array_filter(
			array_keys( $wp_rewrite->wp_rewrite_rules() ),
			static function ( $pattern ) {
				return false !== strpos( $pattern, 'print' );
			}
		);

		$this->assertNotEmpty( $print_rules );
	}

	/**
	 * The uninstaller removes both rows the plugin owns and nothing else.
	 */
	public function test_uninstall_removes_the_plugin_options() {
		update_option( Print_Options::OPTION_NAME, Print_Options::get_defaults() );
		update_option( Print_Options::DB_VERSION_NAME, WP_PRINT_DB_VERSION );
		update_option( 'an_unrelated_option', 'keep me' );

		// uninstall.php guards on this and would exit the whole test run without it.
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'wp-print/wp-print.php' );
		}

		require plugin_dir_path( WP_PRINT_MAIN_FILE ) . 'uninstall.php';

		$this->assertFalse( get_option( Print_Options::OPTION_NAME ) );
		$this->assertFalse( get_option( Print_Options::DB_VERSION_NAME ) );
		$this->assertSame( 'keep me', get_option( 'an_unrelated_option' ) );
	}

	/**
	 * Uninstalling twice is not an error - the second pass has nothing to do.
	 */
	public function test_uninstall_is_idempotent() {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'wp-print/wp-print.php' );
		}

		print_uninstall_site();
		print_uninstall_site();

		$this->assertFalse( get_option( Print_Options::OPTION_NAME ) );
	}
}
