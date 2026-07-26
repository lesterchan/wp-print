<?php
/**
 * Plugin options: defaults, reading, sanitizing and the 3.0.0 migration.
 *
 * @package WP-Print
 */

/**
 * Tests for the plugin options.
 *
 * @covers Print_Options
 */
class Test_Print_Options extends WP_UnitTestCase {

	/**
	 * Reset the option row between tests.
	 */
	public function set_up() {
		parent::set_up();

		delete_option( Print_Options::OPTION_NAME );
		delete_option( Print_Options::DB_VERSION_NAME );
	}

	/**
	 * Every documented key has a default.
	 */
	public function test_defaults_cover_every_key() {
		$defaults = Print_Options::get_defaults();

		foreach ( array( 'post_text', 'page_text', 'print_icon', 'print_style', 'print_html', 'comments', 'links', 'images', 'thumbnail', 'videos', 'disclaimer' ) as $key ) {
			$this->assertArrayHasKey( $key, $defaults, "Missing default for $key" );
		}
	}

	/**
	 * A row written before a key existed still reads that key, without a warning.
	 *
	 * The thumbnail key arrived in 2.58; reading it unguarded raised "Undefined array
	 * key" and rendered a print link with no text and no icon.
	 */
	public function test_missing_keys_fall_back_to_defaults() {
		update_option( Print_Options::OPTION_NAME, array( 'print_style' => 3 ) );

		$this->assertSame( 3, (int) Print_Options::get( 'print_style' ) );
		$this->assertSame( Print_Options::get_defaults()['post_text'], Print_Options::get( 'post_text' ) );
		$this->assertSame( 'print.gif', Print_Options::get( 'print_icon' ) );
		$this->assertSame( 0, Print_Options::can( 'thumbnail' ) );
	}

	/**
	 * A corrupt row does not take the plugin down with it.
	 */
	public function test_a_non_array_row_reads_as_defaults() {
		update_option( Print_Options::OPTION_NAME, 'not an array' );

		$this->assertSame( Print_Options::get_defaults(), Print_Options::get() );
	}

	/**
	 * A toggle reads as an int, because templates use it in a boolean test and the
	 * string '0' an older row may hold is truthy.
	 */
	public function test_can_returns_an_int() {
		update_option( Print_Options::OPTION_NAME, array( 'links' => '0' ) );

		$this->assertSame( 0, Print_Options::can( 'links' ) );
		$this->assertSame( 0, Print_Options::can( 'no_such_key' ) );
	}

	/**
	 * Toggles are stored as ints, and a key absent from the submission - which is
	 * what an unchecked control sends - reads as off rather than "leave alone".
	 */
	public function test_sanitize_normalizes_toggles() {
		$clean = Print_Options::sanitize( array( 'comments' => '1' ) );

		$this->assertSame( 1, $clean['comments'] );
		$this->assertSame( 0, $clean['images'] );
	}

	/**
	 * The style is clamped to one the renderer knows, or nothing renders at all.
	 */
	public function test_sanitize_clamps_the_style() {
		$this->assertSame( 4, Print_Options::sanitize( array( 'print_style' => '4' ) )['print_style'] );
		$this->assertSame( 1, Print_Options::sanitize( array( 'print_style' => '99' ) )['print_style'] );
		$this->assertSame( 1, Print_Options::sanitize( array( 'print_style' => 'abc' ) )['print_style'] );
	}

	/**
	 * The icon is a file name inside the plugin's own images directory and nothing
	 * else, so the stored value can never become a path traversal.
	 *
	 * @dataProvider data_rejected_icons
	 *
	 * @param string $icon Submitted icon value.
	 */
	public function test_sanitize_rejects_an_icon_outside_the_images_directory( $icon ) {
		$this->assertSame( 'print.gif', Print_Options::sanitize( array( 'print_icon' => $icon ) )['print_icon'] );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_rejected_icons() {
		return array(
			'traversal'     => array( '../../../wp-config.php' ),
			'absolute path' => array( '/etc/passwd' ),
			'absent file'   => array( 'no-such-icon.gif' ),
			'empty'         => array( '' ),
			'nested escape' => array( 'images/../../wp-config.php' ),
		);
	}

	/**
	 * A real icon is accepted.
	 */
	public function test_sanitize_accepts_a_bundled_icon() {
		$this->assertSame(
			'printer_famfamfam.gif',
			Print_Options::sanitize( array( 'print_icon' => 'printer_famfamfam.gif' ) )['print_icon']
		);
	}

	/**
	 * Link text must not come back slashed.
	 *
	 * The kses helper meant for the legacy pipeline also runs addslashes(), because
	 * it predates unslashed
	 * superglobals, and options.php has already unslashed by the time the sanitize
	 * callback runs. Using it here stored "Tom & Jerry\'s Post". A label without a
	 * quote in it does not catch this, which is why this test uses one.
	 */
	public function test_sanitize_does_not_slash_link_text() {
		$clean = Print_Options::sanitize(
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
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$clean = Print_Options::sanitize( array( 'disclaimer' => "O'Reilly & Sons <strong>2026</strong>" ) );

		$this->assertSame( "O'Reilly & Sons <strong>2026</strong>", $clean['disclaimer'] );
	}

	/**
	 * Empty link text falls back to the default rather than rendering an empty
	 * link that a reader cannot see or click.
	 */
	public function test_sanitize_falls_back_for_empty_link_text() {
		$clean = Print_Options::sanitize( array( 'post_text' => '   ' ) );

		$this->assertSame( Print_Options::get_defaults()['post_text'], $clean['post_text'] );
	}

	/**
	 * A user without unfiltered_html cannot store script, even though HTML is
	 * otherwise allowed in these two fields.
	 */
	public function test_sanitize_strips_script_without_unfiltered_html() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$clean = Print_Options::sanitize(
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
		update_option(
			Print_Options::OPTION_NAME,
			array_merge( Print_Options::get_defaults(), array( 'third_party' => 'keep me' ) )
		);

		$clean = Print_Options::sanitize( array( 'post_text' => 'Anything' ) );

		$this->assertSame( 'keep me', $clean['third_party'] );
	}

	/**
	 * The other direction: an unexpected posted key is not stored, or the row
	 * becomes a dumping ground for whatever a request carries.
	 */
	public function test_sanitize_drops_an_unexpected_posted_key() {
		$clean = Print_Options::sanitize(
			array(
				'post_text'    => 'Anything',
				'injected_key' => 'nope',
			)
		);

		$this->assertArrayNotHasKey( 'injected_key', $clean );
	}

	/**
	 * The migration unslashes a pre-3.0.0 row exactly once.
	 */
	public function test_migration_unslashes_a_legacy_row() {
		update_option(
			Print_Options::OPTION_NAME,
			array(
				'post_text'  => "Tom & Jerry\\'s Post",
				'disclaimer' => "Copyright \\'26",
			)
		);

		Print_Options::maybe_upgrade();

		$this->assertSame( "Tom & Jerry's Post", Print_Options::get( 'post_text' ) );
		$this->assertSame( "Copyright '26", Print_Options::get( 'disclaimer' ) );
		$this->assertSame( WP_PRINT_DB_VERSION, get_option( Print_Options::DB_VERSION_NAME ) );
	}

	/**
	 * And it is idempotent: running it again must not eat another backslash, nor
	 * write defaults over a row that has already been migrated.
	 *
	 * Gating on the version rather than on "does the old shape still look present"
	 * is what makes this hold.
	 */
	public function test_migration_is_idempotent() {
		update_option( Print_Options::OPTION_NAME, array( 'post_text' => 'Back\\\\slash kept' ) );

		Print_Options::maybe_upgrade();
		$once = Print_Options::get( 'post_text' );

		Print_Options::maybe_upgrade();
		Print_Options::maybe_upgrade();

		$this->assertSame( $once, Print_Options::get( 'post_text' ) );
	}

	/**
	 * A migrated install keeps its settings; the migration must not reset them.
	 */
	public function test_migration_does_not_overwrite_settings_with_defaults() {
		update_option( Print_Options::DB_VERSION_NAME, WP_PRINT_DB_VERSION );
		update_option(
			Print_Options::OPTION_NAME,
			array(
				'post_text'   => 'Mine',
				'print_style' => 3,
			)
		);

		Print_Options::maybe_upgrade();

		$this->assertSame( 'Mine', Print_Options::get( 'post_text' ) );
		$this->assertSame( 3, (int) Print_Options::get( 'print_style' ) );
	}
}
