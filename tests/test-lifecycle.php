<?php
/**
 * Activation and uninstall.
 *
 * @package WP-Print
 */

/**
 * Tests for the plugin lifecycle.
 *
 * @covers WP_Print
 */
class WP_Print_Lifecycle_Test extends WP_Print_TestCase {

	/**
	 * Start from an install that has never seen the plugin.
	 */
	public function set_up() {
		parent::set_up();

		delete_option( WP_Print_Options::OPTION );
		delete_option( WP_Print_Options::VERSION );
		delete_option( WP_Print_Options::LEGACY_OPTION );
		delete_option( WP_Print_Options::LEGACY_VERSION );
	}

	/**
	 * Activation seeds the defaults and records the schema version, so the first
	 * page load after activating has a complete row to read.
	 */
	public function test_activation_seeds_the_defaults() {
		WP_Print::activate();

		$stored = get_option( WP_Print_Options::OPTION );

		$this->assertIsArray( $stored );
		$this->assertSame( 1, (int) $stored['print_style'] );
		$this->assertSame(
			array(
				'plugin' => WP_PRINT_VERSION,
				'db'     => WP_PRINT_DB_VERSION,
			),
			get_option( WP_Print_Options::VERSION )
		);
	}

	/**
	 * Reactivating must not reset a configured site. add_option() does not
	 * overwrite, which is what makes deactivate/reactivate safe.
	 */
	public function test_activation_does_not_overwrite_existing_settings() {
		update_option(
			WP_Print_Options::OPTION,
			array_merge(
				WP_Print_Options::get_defaults(),
				array(
					'post_text'   => 'Mine',
					'print_style' => 3,
				)
			)
		);

		WP_Print::activate();

		$this->assertSame( 'Mine', WP_Print_Options::get( 'post_text' ) );
		$this->assertSame( 3, (int) WP_Print_Options::get( 'print_style' ) );
	}

	/**
	 * Activation runs the migration too, so an install upgraded from before 3.0.0
	 * is fixed up whichever way it gets there.
	 */
	public function test_activation_runs_the_migration() {
		update_option( WP_Print_Options::LEGACY_OPTION, array( 'post_text' => "Tom & Jerry\\'s Post" ) );

		WP_Print::activate();

		$this->assertSame( "Tom & Jerry's Post", WP_Print_Options::get( 'post_text' ) );
		$this->assertFalse( get_option( WP_Print_Options::LEGACY_OPTION ) );
		$this->assertSame( WP_PRINT_DB_VERSION, WP_Print_Options::markers()['db'] );
	}

	/**
	 * Activation leaves the rewrite rules carrying the print endpoint, which is
	 * what stops a fresh install 404ing until permalinks are saved by hand.
	 */
	public function test_activation_leaves_the_endpoint_in_the_rules() {
		global $wp_rewrite;

		$this->set_permalink_structure( '/%postname%/' );
		WP_Print::add_endpoint();

		WP_Print::activate();

		$print_rules = array_filter(
			array_keys( $wp_rewrite->wp_rewrite_rules() ),
			static function ( $pattern ) {
				return false !== strpos( $pattern, 'print' );
			}
		);

		$this->assertNotEmpty( $print_rules );
	}

	/**
	 * The uninstaller removes every row the plugin owns and nothing else.
	 */
	public function test_uninstall_removes_the_plugin_options() {
		$this->set_options();
		WP_Print_Options::maybe_upgrade();
		update_option( 'an_unrelated_option', 'keep me' );

		$this->run_uninstall();

		$this->assertFalse( get_option( WP_Print_Options::OPTION ) );
		$this->assertFalse( get_option( WP_Print_Options::VERSION ) );
		$this->assertSame( 'keep me', get_option( 'an_unrelated_option' ) );
	}

	/**
	 * Uninstalling twice is not an error - the second pass has nothing to do.
	 */
	public function test_uninstall_is_idempotent() {
		$this->set_options();

		$this->run_uninstall();
		$this->run_uninstall();

		$this->assertFalse( get_option( WP_Print_Options::OPTION ) );
	}
}
