<?php
/**
 * Plugin options: defaults, reading, sanitizing and the 3.0.0 migration.
 *
 * @package WP-Print
 */

/**
 * Tests for the plugin options.
 *
 * @covers WP_Print_Options
 */
class Test_Print_Options extends WP_UnitTestCase {

	/**
	 * Reset the option row between tests.
	 */
	public function set_up() {
		parent::set_up();

		delete_option( WP_Print_Options::OPTION );
		delete_option( WP_Print_Options::VERSION );
		delete_option( WP_Print_Options::LEGACY_OPTION );
		delete_option( WP_Print_Options::LEGACY_VERSION );
	}

	/**
	 * Every documented key has a default.
	 */
	public function test_defaults_cover_every_key() {
		$defaults = WP_Print_Options::get_defaults();

		foreach ( array( 'post_text', 'page_text', 'print_style', 'print_html', 'comments', 'links', 'images', 'thumbnail', 'videos', 'disclaimer' ) as $key ) {
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
		update_option( WP_Print_Options::OPTION, array( 'print_style' => 3 ) );

		$this->assertSame( 3, (int) WP_Print_Options::get( 'print_style' ) );
		$this->assertSame( WP_Print_Options::get_defaults()['post_text'], WP_Print_Options::get( 'post_text' ) );
		$this->assertSame( 0, WP_Print_Options::can( 'thumbnail' ) );
	}

	/**
	 * A corrupt row does not take the plugin down with it.
	 */
	public function test_a_non_array_row_reads_as_defaults() {
		update_option( WP_Print_Options::OPTION, 'not an array' );

		$this->assertSame( WP_Print_Options::get_defaults(), WP_Print_Options::get() );
	}

	/**
	 * A toggle reads as an int, because templates use it in a boolean test and the
	 * string '0' an older row may hold is truthy.
	 */
	public function test_can_returns_an_int() {
		update_option( WP_Print_Options::OPTION, array( 'links' => '0' ) );

		$this->assertSame( 0, WP_Print_Options::can( 'links' ) );
		$this->assertSame( 0, WP_Print_Options::can( 'no_such_key' ) );
	}

	/**
	 * Toggles are stored as ints, and a key absent from the submission - which is
	 * what an unchecked control sends - reads as off rather than "leave alone".
	 */
	public function test_sanitize_normalizes_toggles() {
		update_option( WP_Print_Options::OPTION, array_merge( WP_Print_Options::get_defaults(), array( 'images' => 1 ) ) );

		$clean = WP_Print_Options::sanitize(
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
		$this->assertSame( 4, WP_Print_Options::sanitize( array( 'print_style' => '4' ) )['print_style'] );

		// An unknown style renders nothing at all, so the last valid one is kept.
		update_option( WP_Print_Options::OPTION, array_merge( WP_Print_Options::get_defaults(), array( 'print_style' => 3 ) ) );

		$this->assertSame( 3, WP_Print_Options::sanitize( array( 'print_style' => '99' ) )['print_style'] );
		$this->assertSame( 3, WP_Print_Options::sanitize( array( 'print_style' => 'abc' ) )['print_style'] );
	}

	/**
	 * The retired print_icon key is dropped on any write, however it got there.
	 *
	 * It chose between two bundled GIFs; there is one inline SVG now, so a row
	 * carrying it would be storing a setting nothing reads. The "keep what this
	 * screen does not render" merge would otherwise preserve it for ever.
	 */
	public function test_sanitize_drops_the_retired_icon_key() {
		update_option(
			WP_Print_Options::OPTION,
			array_merge( WP_Print_Options::get_defaults(), array( 'print_icon' => 'print.gif' ) )
		);

		$clean = WP_Print_Options::sanitize( array( 'post_text' => 'Anything' ) );

		$this->assertArrayNotHasKey( 'print_icon', $clean );
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
		$clean = WP_Print_Options::sanitize(
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

		$clean = WP_Print_Options::sanitize( array( 'disclaimer' => "O'Reilly & Sons <strong>2026</strong>" ) );

		$this->assertSame( "O'Reilly & Sons <strong>2026</strong>", $clean['disclaimer'] );
	}

	/**
	 * Empty link text falls back to the default rather than rendering an empty
	 * link that a reader cannot see or click.
	 */
	public function test_sanitize_falls_back_for_empty_link_text() {
		$clean = WP_Print_Options::sanitize( array( 'post_text' => '   ' ) );

		$this->assertSame( WP_Print_Options::get_defaults()['post_text'], $clean['post_text'] );
	}

	/**
	 * A user without unfiltered_html cannot store script, even though HTML is
	 * otherwise allowed in these two fields.
	 */
	public function test_sanitize_strips_script_without_unfiltered_html() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$clean = WP_Print_Options::sanitize(
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
			WP_Print_Options::OPTION,
			array_merge( WP_Print_Options::get_defaults(), array( 'third_party' => 'keep me' ) )
		);

		$clean = WP_Print_Options::sanitize( array( 'post_text' => 'Anything' ) );

		$this->assertSame( 'keep me', $clean['third_party'] );
	}

	/**
	 * The other direction: an unexpected posted key is not stored, or the row
	 * becomes a dumping ground for whatever a request carries.
	 */
	public function test_sanitize_drops_an_unexpected_posted_key() {
		$clean = WP_Print_Options::sanitize(
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
		update_option(
			WP_Print_Options::OPTION,
			array_merge(
				WP_Print_Options::get_defaults(),
				array(
					'disclaimer'  => 'MY DISCLAIMER',
					'print_html'  => '<a href="%PRINT_URL%">mine</a>',
					'post_text'   => 'My Post Text',
					'print_style' => 4,
				)
			)
		);

		$clean = WP_Print_Options::sanitize( array( 'comments' => 1 ) );

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
		update_option(
			WP_Print_Options::OPTION,
			array_merge( WP_Print_Options::get_defaults(), array( 'disclaimer' => 'MY DISCLAIMER' ) )
		);

		$this->assertSame( '', WP_Print_Options::sanitize( array( 'disclaimer' => '' ) )['disclaimer'] );
	}

	/**
	 * The migration moves the pre-3.0.0 row to the prefixed name and unslashes it
	 * on the way, exactly once.
	 */
	public function test_migration_folds_the_legacy_row_into_the_prefixed_one() {
		update_option(
			WP_Print_Options::LEGACY_OPTION,
			array(
				'post_text'  => "Tom & Jerry\\'s Post",
				'disclaimer' => "Copyright \\'26",
			)
		);
		update_option( WP_Print_Options::LEGACY_VERSION, '1' );

		WP_Print_Options::maybe_upgrade();

		$this->assertSame( "Tom & Jerry's Post", WP_Print_Options::get( 'post_text' ) );
		$this->assertSame( "Copyright '26", WP_Print_Options::get( 'disclaimer' ) );
	}

	/**
	 * ...and takes the two unprefixed rows away with it. Leaving them behind is
	 * how a plugin ends up owning four rows for two settings.
	 */
	public function test_migration_deletes_the_legacy_rows() {
		update_option( WP_Print_Options::LEGACY_OPTION, array( 'post_text' => 'Mine' ) );
		update_option( WP_Print_Options::LEGACY_VERSION, '1' );

		WP_Print_Options::maybe_upgrade();

		$this->assertFalse( get_option( WP_Print_Options::LEGACY_OPTION ), 'print_options must not survive the migration.' );
		$this->assertFalse( get_option( WP_Print_Options::LEGACY_VERSION ), 'print_db_version must not survive the migration.' );
	}

	/**
	 * The markers land in their own row, holding those two keys and no others.
	 */
	public function test_migration_writes_both_markers_to_their_own_row() {
		WP_Print_Options::maybe_upgrade();

		$this->assertSame(
			array(
				'plugin' => WP_PRINT_VERSION,
				'db'     => WP_PRINT_DB_VERSION,
			),
			get_option( WP_Print_Options::VERSION )
		);
	}

	/**
	 * A custom template built around the old icon URL keeps working.
	 *
	 * %PRINT_ICON_URL% had a URL to give it; the inline glyph has not, so the
	 * whole <img> is replaced rather than its src, which would otherwise have
	 * become <img src="<svg ...>">.
	 */
	public function test_migration_rewrites_an_icon_placeholder_inside_an_image_tag() {
		update_option(
			WP_Print_Options::LEGACY_OPTION,
			array( 'print_html' => '<a href="%PRINT_URL%"><img src="%PRINT_ICON_URL%" alt="%PRINT_TEXT%" /> %PRINT_TEXT%</a>' )
		);

		WP_Print_Options::maybe_upgrade();

		$this->assertSame(
			'<a href="%PRINT_URL%">%PRINT_ICON% %PRINT_TEXT%</a>',
			WP_Print_Options::get( 'print_html' )
		);
	}

	/**
	 * A bare one outside an image tag is simply renamed.
	 */
	public function test_migration_renames_a_bare_icon_placeholder() {
		update_option(
			WP_Print_Options::LEGACY_OPTION,
			array( 'print_html' => '<a href="%PRINT_URL%">%PRINT_ICON_URL%</a>' )
		);

		WP_Print_Options::maybe_upgrade();

		$this->assertSame( '<a href="%PRINT_URL%">%PRINT_ICON%</a>', WP_Print_Options::get( 'print_html' ) );
	}

	/**
	 * And the setting the placeholder went with is taken off the row.
	 */
	public function test_migration_removes_the_stored_icon_setting() {
		update_option( WP_Print_Options::LEGACY_OPTION, array( 'print_icon' => 'printer_famfamfam.gif' ) );

		WP_Print_Options::maybe_upgrade();

		$this->assertArrayNotHasKey( 'print_icon', (array) get_option( WP_Print_Options::OPTION ) );
	}

	/**
	 * A value already in the prefixed row wins over the legacy one, so a
	 * migration interrupted half way cannot undo itself on the next run.
	 */
	public function test_migration_does_not_overwrite_an_already_migrated_value() {
		update_option( WP_Print_Options::LEGACY_OPTION, array( 'post_text' => 'Old' ) );
		update_option( WP_Print_Options::OPTION, array( 'post_text' => 'New' ) );

		WP_Print_Options::maybe_upgrade();

		$this->assertSame( 'New', WP_Print_Options::get( 'post_text' ) );
	}

	/**
	 * And it is idempotent: running it again must not eat another backslash, nor
	 * write defaults over a row that has already been migrated.
	 *
	 * Gating on the version rather than on "does the old shape still look present"
	 * is what makes this hold.
	 */
	public function test_migration_is_idempotent() {
		update_option( WP_Print_Options::LEGACY_OPTION, array( 'post_text' => 'Back\\\\slash kept' ) );

		WP_Print_Options::maybe_upgrade();
		$once = WP_Print_Options::get( 'post_text' );

		WP_Print_Options::maybe_upgrade();
		WP_Print_Options::maybe_upgrade();

		$this->assertSame( $once, WP_Print_Options::get( 'post_text' ) );
	}

	/**
	 * A migrated install keeps its settings; the migration must not reset them.
	 */
	public function test_migration_does_not_overwrite_settings_with_defaults() {
		WP_Print_Options::maybe_upgrade();
		update_option(
			WP_Print_Options::OPTION,
			array(
				'post_text'   => 'Mine',
				'print_style' => 3,
			)
		);

		WP_Print_Options::maybe_upgrade();

		$this->assertSame( 'Mine', WP_Print_Options::get( 'post_text' ) );
		$this->assertSame( 3, (int) WP_Print_Options::get( 'print_style' ) );
	}
}
