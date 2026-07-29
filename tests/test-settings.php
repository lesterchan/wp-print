<?php
/**
 * The registered setting, its fields and its sanitiser.
 *
 * A Settings API screen fails silently when the option group does not match what
 * settings_fields() emits, or when the sanitize callback returns null - the save
 * simply does nothing. Both are asserted here.
 *
 * @package WP-Print
 */

/**
 * Tests for WP_Print_Settings.
 *
 * @covers WP_Print_Settings
 */
class WP_Print_Settings_Test extends WP_Print_TestCase {

	/**
	 * Register the settings as an admin request would.
	 */
	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->set_options();

		require_once ABSPATH . 'wp-admin/includes/template.php';
		WP_Print_Settings::register();
	}

	/**
	 * Render the fields, which is what do_settings_sections() does on the screen.
	 *
	 * @return string
	 */
	private function render_fields() {
		ob_start();
		do_settings_sections( WP_Print_Admin::PAGE );

		return ob_get_clean();
	}

	/**
	 * The setting is registered in the group the form posts, with the sanitize
	 * callback attached.
	 */
	public function test_the_setting_is_registered_in_the_right_group() {
		global $wp_registered_settings;

		$this->assertArrayHasKey( WP_Print_Options::OPTION, $wp_registered_settings );

		$registered = $wp_registered_settings[ WP_Print_Options::OPTION ];

		$this->assertSame( WP_Print_Settings::GROUP, $registered['group'] );
		$this->assertSame( array( 'WP_Print_Settings', 'sanitize' ), $registered['sanitize_callback'] );
	}

	/**
	 * The form emits the same group the setting was registered under, which is the
	 * pairing that makes a save take effect at all.
	 */
	public function test_the_form_posts_the_registered_group() {
		ob_start();
		settings_fields( WP_Print_Settings::GROUP );
		$fields = ob_get_clean();

		// settings_fields() emits single-quoted attributes, so match the value alone.
		$this->assertStringContainsString( WP_Print_Settings::GROUP, $fields );
		$this->assertStringContainsString( "name='option_page'", $fields );
		$this->assertStringContainsString( '_wpnonce', $fields );
	}

	/**
	 * Both sections are registered against the screen Admin owns.
	 *
	 * The two classes meet at this constant, so a section registered against the
	 * wrong page renders nothing and says nothing about it.
	 */
	public function test_both_sections_are_registered_against_the_admin_page() {
		global $wp_settings_sections;

		$this->assertArrayHasKey( WP_Print_Admin::PAGE, $wp_settings_sections );

		$sections = array_keys( $wp_settings_sections[ WP_Print_Admin::PAGE ] );

		$this->assertContains( WP_Print_Settings::SECTION_STYLES, $sections );
		$this->assertContains( WP_Print_Settings::SECTION_CONTENT, $sections );
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
	 * Every field renders and nests its name under the option key.
	 */
	public function test_every_field_renders() {
		$html = $this->render_fields();

		foreach ( array( 'post_text', 'page_text', 'print_style', 'print_html', 'comments', 'links', 'images', 'thumbnail', 'videos', 'disclaimer' ) as $key ) {
			$this->assertStringContainsString(
				WP_Print_Options::OPTION . '[' . $key . ']',
				$html,
				"Field $key is missing from the screen"
			);
		}
	}

	/**
	 * The fields carry no inline event handlers: the behaviour is delegated from
	 * data-* attributes in js/wp-print-admin.js.
	 */
	public function test_the_fields_have_no_inline_handlers() {
		$html = $this->render_fields();

		$this->assertStringNotContainsString( 'onclick=', $html );
		$this->assertStringNotContainsString( 'onchange=', $html );
		$this->assertStringContainsString( 'data-print-toggle', $html );
		$this->assertStringContainsString( 'data-print-restore', $html );
	}

	/**
	 * Nothing styles itself inline, and no field lays itself out with the
	 * attributes the standard forbids.
	 */
	public function test_the_fields_carry_no_presentational_attributes() {
		$html = $this->render_fields();

		foreach ( array( 'style="', 'width=', 'valign', 'align=' ) as $attribute ) {
			$this->assertStringNotContainsString( $attribute, $html, "The fields must not use {$attribute}." );
		}
	}

	/**
	 * A stored value containing markup is escaped on the way into the form, so the
	 * screen cannot be used to execute what an earlier save stored.
	 */
	public function test_stored_markup_is_escaped_into_the_form() {
		$this->set_options(
			array(
				'post_text'  => 'Quote " and <b>bold</b>',
				'disclaimer' => '</textarea><script>alert(1)</script>',
			)
		);

		$html = $this->render_fields();

		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
		$this->assertStringNotContainsString( '</textarea><script', $html );
	}

	/**
	 * The custom template block is hidden unless the Custom style is selected, so
	 * the screen looks the same as it did before the rewrite.
	 */
	public function test_the_custom_block_is_hidden_unless_selected() {
		$this->assertStringContainsString( 'wp-print-custom hidden', $this->render_fields() );

		$this->set_options( array( 'print_style' => 4 ) );

		$this->assertStringNotContainsString( 'wp-print-custom hidden', $this->render_fields() );
	}

	/**
	 * There is no icon picker any more, and no field for the setting behind it.
	 *
	 * One inline SVG replaced the two bundled GIFs, so the choice it offered no
	 * longer exists. A field left behind would write a key nothing reads.
	 */
	public function test_the_screen_offers_no_icon_setting() {
		$html = $this->render_fields();

		$this->assertStringNotContainsString( 'print_icon', $html );
		$this->assertStringNotContainsString( 'images/', $html );
	}

	/**
	 * The placeholder documentation is emitted as code spans rather than inside a
	 * translatable string, so phpcbf cannot renumber %PRINT_URL% as a printf
	 * placeholder and show users "%1$PRINT_URL%".
	 */
	public function test_placeholders_are_documented_outside_translatable_strings() {
		$html = $this->render_fields();

		$this->assertStringContainsString( '<code>%PRINT_URL%</code>', $html );
		$this->assertStringContainsString( '<code>%PRINT_TEXT%</code>', $html );
		$this->assertStringContainsString( '<code>%PRINT_ICON%</code>', $html );
		$this->assertStringNotContainsString( '%1$PRINT', $html );

		// The retired one is not offered: it named a URL, and the glyph is inline.
		$this->assertStringNotContainsString( '%PRINT_ICON_URL%', $html );
	}

	/**
	 * Toggles are stored as ints, and a key absent from the submission - which is
	 * what an unchecked control sends - reads as off rather than "leave alone".
	 */
	public function test_sanitize_normalizes_toggles() {
		$this->set_options( array( 'images' => 1 ) );

		$clean = WP_Print_Settings::sanitize(
			array(
				'comments' => '1',
				'images'   => '0',
			)
		);

		$this->assertSame( 1, $clean['comments'] );
		$this->assertSame( 0, $clean['images'] );
	}

	/**
	 * The style is clamped to one the renderer knows, or nothing renders at all.
	 */
	public function test_sanitize_clamps_the_style() {
		$this->assertSame( 4, WP_Print_Settings::sanitize( array( 'print_style' => '4' ) )['print_style'] );

		// An unknown style renders nothing at all, so the last valid one is kept.
		$this->set_options( array( 'print_style' => 3 ) );

		$this->assertSame( 3, WP_Print_Settings::sanitize( array( 'print_style' => '99' ) )['print_style'] );
		$this->assertSame( 3, WP_Print_Settings::sanitize( array( 'print_style' => 'abc' ) )['print_style'] );
	}

	/**
	 * The retired print_icon key is dropped on any write, however it got there.
	 *
	 * It chose between two bundled GIFs; there is one inline SVG now, so a row
	 * carrying it would be storing a setting nothing reads. The "keep what this
	 * screen does not render" merge would otherwise preserve it for ever.
	 */
	public function test_sanitize_drops_the_retired_icon_key() {
		$this->set_options( array( 'print_icon' => 'print.gif' ) );

		$clean = WP_Print_Settings::sanitize( array( 'post_text' => 'Anything' ) );

		$this->assertArrayNotHasKey( 'print_icon', $clean );
	}

	/**
	 * Link text must not come back slashed.
	 *
	 * The kses helper meant for the legacy pipeline also runs addslashes(),
	 * because it predates unslashed superglobals, and options.php has already
	 * unslashed by the time the sanitize callback runs. Using it here stored
	 * "Tom & Jerry\'s Post". A label without a quote in it does not catch this,
	 * which is why this test uses one.
	 */
	public function test_sanitize_does_not_slash_link_text() {
		$clean = WP_Print_Settings::sanitize(
			array(
				'post_text' => "Tom & Jerry's \"Post\"",
				'page_text' => "O'Brien & Co",
			)
		);

		$this->assertStringNotContainsString( '\\', $clean['post_text'] );
		$this->assertStringNotContainsString( '\\', $clean['page_text'] );

		// kses normalizes a bare ampersand, which is right: the value is rendered
		// as link text and inside a title attribute.
		$this->assertSame( 'Tom &amp; Jerry\'s "Post"', $clean['post_text'] );
		$this->assertSame( 'O\'Brien &amp; Co', $clean['page_text'] );
	}

	/**
	 * An HTML value must not come back slashed either.
	 */
	public function test_sanitize_does_not_slash_html_values() {
		$clean = WP_Print_Settings::sanitize( array( 'disclaimer' => "O'Reilly & Sons <strong>2026</strong>" ) );

		$this->assertSame( "O'Reilly & Sons <strong>2026</strong>", $clean['disclaimer'] );
	}

	/**
	 * Empty link text falls back to the default rather than rendering an empty
	 * link that a reader cannot see or click.
	 */
	public function test_sanitize_falls_back_for_empty_link_text() {
		$clean = WP_Print_Settings::sanitize( array( 'post_text' => '   ' ) );

		$this->assertSame( WP_Print_Options::get_defaults()['post_text'], $clean['post_text'] );
	}

	/**
	 * A user without unfiltered_html cannot store script, even though HTML is
	 * otherwise allowed in these two fields.
	 */
	public function test_sanitize_strips_script_without_unfiltered_html() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$clean = WP_Print_Settings::sanitize(
			array(
				'disclaimer' => '<strong>ok</strong><script>alert(1)</script>',
				'print_html' => '<a href="%PRINT_URL%" onclick="alert(1)">x</a>',
			)
		);

		$this->assertStringNotContainsString( '<script', $clean['disclaimer'] );
		$this->assertStringContainsString( '<strong>ok</strong>', $clean['disclaimer'] );
		$this->assertStringNotContainsString( 'onclick', $clean['print_html'] );
	}

	/**
	 * A key already in the row that this screen does not render survives a save,
	 * so a save cannot silently drop a third party's or a filter's addition.
	 */
	public function test_sanitize_keeps_stored_keys_it_does_not_render() {
		$this->set_options( array( 'third_party' => 'keep me' ) );

		$clean = WP_Print_Settings::sanitize( array( 'post_text' => 'Anything' ) );

		$this->assertSame( 'keep me', $clean['third_party'] );
	}

	/**
	 * The other direction: an unexpected posted key is not stored, or the row
	 * becomes a dumping ground for whatever a request carries.
	 */
	public function test_sanitize_drops_an_unexpected_posted_key() {
		$clean = WP_Print_Settings::sanitize(
			array(
				'post_text'    => 'Anything',
				'injected_key' => 'nope',
			)
		);

		$this->assertArrayNotHasKey( 'injected_key', $clean );
	}

	/**
	 * A partial update leaves every key it did not mention alone.
	 *
	 * Note that register_setting() hangs this callback on
	 * sanitize_option_wp_print_options, so it also runs for update_option()
	 * calls from WP-CLI, a migration or another plugin - and those are usually
	 * partial. Blanking the disclaimer and the custom template because a caller
	 * flipped one toggle is data loss.
	 */
	public function test_a_partial_update_keeps_everything_else() {
		$this->set_options(
			array(
				'disclaimer'  => 'MY DISCLAIMER',
				'print_html'  => '<a href="%PRINT_URL%">mine</a>',
				'post_text'   => 'My Post Text',
				'print_style' => 4,
			)
		);

		$clean = WP_Print_Settings::sanitize( array( 'comments' => 1 ) );

		$this->assertSame( 'MY DISCLAIMER', $clean['disclaimer'] );
		$this->assertSame( '<a href="%PRINT_URL%">mine</a>', $clean['print_html'] );
		$this->assertSame( 'My Post Text', $clean['post_text'] );
		$this->assertSame( 4, $clean['print_style'] );
		$this->assertSame( 1, $clean['comments'] );
	}

	/**
	 * Emptying an HTML field is still allowed - a site may not want a disclaimer.
	 * That is a submitted empty value, not an absent key.
	 */
	public function test_an_html_field_can_be_emptied_deliberately() {
		$this->set_options( array( 'disclaimer' => 'MY DISCLAIMER' ) );

		$this->assertSame( '', WP_Print_Settings::sanitize( array( 'disclaimer' => '' ) )['disclaimer'] );
	}
}
