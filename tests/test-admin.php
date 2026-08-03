<?php
/**
 * The admin menu, the screen it renders and the script that screen loads.
 *
 * The fields on that screen, and the sanitiser behind them, belong to
 * WP_Print_Settings and are covered in test-settings.php.
 *
 * @package WP-Print
 */

/**
 * Tests for WP_Print_Admin.
 *
 * @covers WP_Print_Admin
 */
class WP_Print_Admin_Test extends WP_Print_TestCase {

	/**
	 * Act as an administrator on a configured site.
	 */
	public function set_up() {
		parent::set_up();

		wp_set_current_user( $this->create_admin() );

		$this->set_options();

		require_once ABSPATH . 'wp-admin/includes/template.php';
	}

	/**
	 * A plugin whose only admin surface is settings goes under Settings, with no
	 * top-level menu of its own.
	 */
	public function test_the_screen_is_added_under_settings() {
		global $submenu, $menu;

		// wp-admin builds these two once per request and nothing resets them
		// between tests, so they are saved and put back rather than emptied.
		$was_submenu = $submenu;
		$was_menu    = $menu;

		$submenu = array();
		$menu    = array();

		try {
			WP_Print_Admin::add_page();

			$this->assertArrayHasKey( 'options-general.php', $submenu, 'The screen belongs under Settings.' );
			$this->assertContains( WP_Print_Admin::PAGE, wp_list_pluck( $submenu['options-general.php'], 2 ), 'The screen is registered under Settings, per the menu rule.' );
			$this->assertSame( array(), $menu, 'WP-Print claims no top-level menu.' );
		} finally {
			$submenu = $was_submenu;
			$menu    = $was_menu;
		}
	}

	/**
	 * The screen requires manage_options, which is what a settings screen takes.
	 */
	public function test_the_screen_requires_manage_options_by_default() {
		$this->assertSame( 'manage_options', WP_Print_Admin::CAPABILITY, 'The capability constant is manage_options.' );
		$this->assertSame( 'manage_options', WP_Print_Admin::capability( 'settings' ), 'And the accessor answers with it, so the two cannot drift.' );
	}

	/**
	 * Every check goes through one filter, so a site that wants its editors to
	 * reach the print settings has one thing to hook.
	 */
	public function test_the_capability_filter_is_honoured() {
		$contexts = array();

		$filter = static function ( $capability, $context ) use ( &$contexts ) {
			$contexts[] = $context;

			return 'edit_pages';
		};

		add_filter( 'wp_print_capability', $filter, 10, 2 );

		$this->assertSame( 'edit_pages', WP_Print_Admin::capability( 'menu' ), 'A filter can replace the menu capability.' );
		$this->assertSame( 'edit_pages', WP_Print_Admin::capability( 'settings' ), 'And the settings capability, which is asked for separately.' );

		remove_filter( 'wp_print_capability', $filter, 10 );

		$this->assertSame( array( 'menu', 'settings' ), $contexts, 'The context is passed to the filter.' );
	}

	/**
	 * The page renders the form that posts to options.php, under the group the
	 * setting is registered in.
	 */
	public function test_the_page_renders_the_form_for_the_registered_group() {
		WP_Print_Settings::register();

		ob_start();
		WP_Print_Admin::render_page();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'action="options.php"', $html, 'The form posts to options.php, so core handles the save.' );
		$this->assertStringContainsString( WP_Print_Settings::GROUP, $html, 'Naming the settings group the setting is registered in.' );
		$this->assertSame( 1, substr_count( $html, '<h1>' ), 'One h1 per screen.' );
		$this->assertStringContainsString( 'class="wrap"', $html, 'And it uses the core page wrapper.' );
	}

	/**
	 * A user without the capability is turned away rather than shown the form.
	 */
	public function test_the_page_refuses_a_user_without_the_capability() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->expectException( WPDieException::class );

		WP_Print_Admin::render_page();
	}

	/**
	 * The script loads on the plugin's own screen and nowhere else. Enqueuing it
	 * everywhere is how a settings screen's JavaScript ends up running on the
	 * post editor.
	 */
	public function test_the_admin_script_loads_only_on_its_own_screen() {
		$this->reset_scripts();

		WP_Print_Admin::enqueue( 'index.php' );
		$this->assertFalse( wp_script_is( 'wp-print-admin', 'enqueued' ), 'Not on the dashboard.' );

		WP_Print_Admin::enqueue( 'settings_page_' . WP_Print_Admin::PAGE );
		$this->assertTrue( wp_script_is( 'wp-print-admin', 'enqueued' ), 'The admin script is enqueued on its own screen.' );
	}

	/**
	 * The localised defaults reach the page byte for byte.
	 *
	 * Localisation runs html_entity_decode() over every scalar it is given, which
	 * turned the shipped disclaimer's &copy; into a literal ©. Nesting the two
	 * strings one level deep avoids that, and this is the test that fails if
	 * anyone flattens them back.
	 */
	public function test_the_localised_defaults_keep_their_entities() {
		$this->reset_scripts();

		WP_Print_Admin::enqueue( 'settings_page_' . WP_Print_Admin::PAGE );

		$data = wp_scripts()->get_data( 'wp-print-admin', 'data' );

		$this->assertIsString( $data, 'The localised defaults arrive as a string rather than being dropped.' );
		$this->assertStringContainsString( 'wpPrintL10n', $data, 'The localised object is attached under the name the script reads.' );
		$this->assertStringContainsString( '&copy;', $data, 'The entity must survive localisation.' );
		$this->assertStringNotContainsString( 'wpPrintDefaults', $data, 'The old object name is retired.' );
	}

	/**
	 * The script declares no dependencies at all, jQuery least of all.
	 */
	public function test_the_admin_script_declares_no_dependencies() {
		$this->reset_scripts();

		WP_Print_Admin::enqueue( 'settings_page_' . WP_Print_Admin::PAGE );

		$this->assertSame( array(), wp_scripts()->registered['wp-print-admin']->deps, 'The admin script declares no dependencies, so nothing is pulled in behind it.' );
	}

	/**
	 * The Plugins screen gains a Settings link pointing at the new slug.
	 */
	public function test_a_settings_action_link_is_added() {
		$links = WP_Print_Admin::action_links( array( 'Deactivate' ) );

		$this->assertStringContainsString( 'page=' . WP_Print_Admin::PAGE, $links[0], 'The Settings action link points at this plugin screen.' );
		$this->assertStringContainsString( 'Settings', $links[0], 'And is labelled.' );
		$this->assertContains( 'Deactivate', $links, 'While the links already there survive.' );
	}

	/**
	 * A filter handing back something other than an array must not fatal the
	 * Plugins screen.
	 */
	public function test_the_action_links_survive_a_non_array() {
		$links = WP_Print_Admin::action_links( null );

		$this->assertCount( 1, $links, 'A non-array links list still yields the plugin one link.' );
		$this->assertStringContainsString( 'page=' . WP_Print_Admin::PAGE, $links[0], 'A non-array links list still yields the Settings link.' );
	}

	/**
	 * Start from an empty script registry.
	 *
	 * WP_Dependencies remembers what has been registered and enqueued for the
	 * whole process, so without this a test asserting the script is *not*
	 * enqueued would depend on which test ran before it.
	 *
	 * @return void
	 */
	private function reset_scripts() {
		$GLOBALS['wp_scripts'] = null;
	}
}
