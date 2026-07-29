<?php
/**
 * Settings screen: the Settings API registration and the rendered fields.
 *
 * A Settings API screen fails silently when the option group does not match what
 * settings_fields() emits, or when the sanitize callback returns null - the save
 * simply does nothing. Both are asserted here.
 *
 * @package WP-Print
 */

/**
 * Tests for the settings screen.
 *
 * @covers WP_Print_Admin
 */
class Test_Print_Admin extends WP_UnitTestCase {

	/**
	 * Register the settings as an admin request would.
	 */
	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		update_option( WP_Print_Options::OPTION, WP_Print_Options::get_defaults() );

		require_once ABSPATH . 'wp-admin/includes/template.php';
		WP_Print_Admin::register_settings();
	}

	/**
	 * The setting is registered in the group the form posts, with the sanitize
	 * callback attached.
	 */
	public function test_the_setting_is_registered_in_the_right_group() {
		global $wp_registered_settings;

		$this->assertArrayHasKey( WP_Print_Options::OPTION, $wp_registered_settings );

		$registered = $wp_registered_settings[ WP_Print_Options::OPTION ];

		$this->assertSame( WP_Print_Admin::GROUP, $registered['group'] );
		$this->assertSame( array( 'WP_Print_Options', 'sanitize' ), $registered['sanitize_callback'] );
	}

	/**
	 * The form emits the same group the setting was registered under, which is the
	 * pairing that makes a save take effect at all.
	 */
	public function test_the_form_posts_the_registered_group() {
		ob_start();
		settings_fields( WP_Print_Admin::GROUP );
		$fields = ob_get_clean();

		// settings_fields() emits single-quoted attributes, so match the value alone.
		$this->assertStringContainsString( WP_Print_Admin::GROUP, $fields );
		$this->assertStringContainsString( "name='option_page'", $fields );
		$this->assertStringContainsString( '_wpnonce', $fields );
	}

	/**
	 * Writing the option runs it through the sanitize callback, because
	 * register_setting() hangs it on sanitize_option_{$option}. This is what makes
	 * the round trip safe rather than only the screen.
	 */
	public function test_writing_the_option_runs_the_sanitizer() {
		update_option(
			WP_Print_Options::OPTION,
			array(
				'print_style' => '99',
				'post_text'   => "Tom & Jerry's",
			)
		);

		$this->assertSame( 1, (int) WP_Print_Options::get( 'print_style' ) );
		$this->assertStringNotContainsString( '\\', WP_Print_Options::get( 'post_text' ) );
	}

	/**
	 * Every field renders, nests its name under the option key, and escapes.
	 */
	public function test_the_screen_renders_every_field() {
		$html = $this->render_screen();

		foreach ( array( 'post_text', 'page_text', 'print_style', 'print_html', 'comments', 'links', 'images', 'thumbnail', 'videos', 'disclaimer' ) as $key ) {
			$this->assertStringContainsString(
				WP_Print_Options::OPTION . '[' . $key . ']',
				$html,
				"Field $key is missing from the screen"
			);
		}

		$this->assertStringContainsString( 'action="options.php"', $html );
		$this->assertStringContainsString( WP_Print_Admin::GROUP, $html );
	}

	/**
	 * The screen carries no inline event handlers and no jQuery dependency: the
	 * behaviour is delegated from data-* attributes in print-admin.js.
	 */
	public function test_the_screen_has_no_inline_handlers() {
		$html = $this->render_screen();

		$this->assertStringNotContainsString( 'onclick=', $html );
		$this->assertStringNotContainsString( 'onchange=', $html );
		$this->assertStringContainsString( 'data-print-toggle', $html );
		$this->assertStringContainsString( 'data-print-restore', $html );
	}

	/**
	 * A stored value containing markup is escaped on the way into the form, so the
	 * screen cannot be used to execute what an earlier save stored.
	 */
	public function test_stored_markup_is_escaped_into_the_form() {
		update_option(
			WP_Print_Options::OPTION,
			array_merge(
				WP_Print_Options::get_defaults(),
				array(
					'post_text'  => 'Quote " and <b>bold</b>',
					'disclaimer' => '</textarea><script>alert(1)</script>',
				)
			)
		);

		$html = $this->render_screen();

		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
		$this->assertStringNotContainsString( '</textarea><script', $html );
	}

	/**
	 * The custom template block is hidden unless the Custom style is selected, so
	 * the screen looks the same as it did before the rewrite.
	 */
	public function test_the_custom_block_is_hidden_unless_selected() {
		$this->assertStringContainsString( 'wp-print-custom hidden', $this->render_screen() );

		update_option( WP_Print_Options::OPTION, array_merge( WP_Print_Options::get_defaults(), array( 'print_style' => 4 ) ) );

		$this->assertStringNotContainsString( 'wp-print-custom hidden', $this->render_screen() );
	}

	/**
	 * There is no icon picker any more, and no field for the setting behind it.
	 *
	 * One inline SVG replaced the two bundled GIFs, so the choice it offered no
	 * longer exists. A field left behind would write a key nothing reads.
	 */
	public function test_the_screen_offers_no_icon_setting() {
		$html = $this->render_screen();

		$this->assertStringNotContainsString( 'print_icon', $html );
		$this->assertStringNotContainsString( 'images/', $html );
	}

	/**
	 * The placeholder documentation is emitted as code spans rather than inside a
	 * translatable string, so phpcbf cannot renumber %PRINT_URL% as a printf
	 * placeholder and show users "%1$PRINT_URL%".
	 */
	public function test_placeholders_are_documented_outside_translatable_strings() {
		$html = $this->render_screen();

		$this->assertStringContainsString( '<code>%PRINT_URL%</code>', $html );
		$this->assertStringContainsString( '<code>%PRINT_TEXT%</code>', $html );
		$this->assertStringContainsString( '<code>%PRINT_ICON_URL%</code>', $html );
		$this->assertStringNotContainsString( '%1$PRINT', $html );
	}

	/**
	 * The Plugins screen gains a Settings link pointing at the new slug.
	 */
	public function test_a_settings_action_link_is_added() {
		$links = WP_Print_Admin::action_links( array( 'Deactivate' ) );

		$this->assertStringContainsString( 'page=' . WP_Print_Admin::PAGE, $links[0] );
		$this->assertStringContainsString( 'Settings', $links[0] );
		$this->assertContains( 'Deactivate', $links );
	}

	/**
	 * Render the settings screen.
	 *
	 * @return string
	 */
	private function render_screen() {
		ob_start();
		WP_Print_Admin::render_page();
		$page = ob_get_clean();

		ob_start();
		do_settings_sections( WP_Print_Admin::PAGE );
		$sections = ob_get_clean();

		return $page . $sections;
	}
}
