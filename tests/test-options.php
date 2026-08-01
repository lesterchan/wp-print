<?php
/**
 * Plugin options: the defaults, reading them, and the 3.0.0 migration.
 *
 * The sanitize callback belongs to WP_Print_Settings, which is where
 * register_setting() hangs it, and is covered in test-settings.php.
 *
 * @package WP-Print
 */

/**
 * Tests for the plugin options.
 *
 * @covers WP_Print_Options
 */
class WP_Print_Options_Test extends WP_Print_TestCase {

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

		foreach ( array( 'print_html', 'comments', 'links', 'images', 'thumbnail', 'videos', 'disclaimer' ) as $key ) {
			$this->assertArrayHasKey( $key, $defaults, "Missing default for $key" );
		}
	}

	/**
	 * And nothing else: a retired key with a default is a setting that comes back
	 * every time a reader merges the defaults in.
	 */
	public function test_defaults_carry_no_retired_key() {
		$defaults = WP_Print_Options::get_defaults();

		foreach ( WP_Print_Options::retired_keys() as $key ) {
			$this->assertArrayNotHasKey( $key, $defaults, "{$key} is retired and must not have a default." );
		}
	}

	/**
	 * The shipped template renders what the shipped settings used to render: the
	 * glyph, then the words, both inside one link.
	 */
	public function test_the_default_template_is_the_icon_and_text_link() {
		$this->assertSame(
			'<a href="%PRINT_URL%" rel="nofollow" title="Print This %POST_TYPE%">%PRINT_ICON% Print This %POST_TYPE%</a>',
			WP_Print_Options::default_template()
		);
		$this->assertSame( WP_Print_Options::default_template(), WP_Print_Options::get_defaults()['print_html'] );
	}

	/**
	 * A row written before a key existed still reads that key, without a warning.
	 *
	 * The thumbnail key arrived in 2.58; reading it unguarded raised "Undefined array
	 * key" and rendered a print link with no text and no icon.
	 */
	public function test_missing_keys_fall_back_to_defaults() {
		update_option( WP_Print_Options::OPTION, array( 'links' => 0 ) );

		$this->assertSame( 0, WP_Print_Options::can( 'links' ) );
		$this->assertSame( WP_Print_Options::default_template(), WP_Print_Options::get( 'print_html' ) );
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
	 * The migration moves the pre-3.0.0 row to the prefixed name and unslashes it
	 * on the way, exactly once.
	 */
	public function test_migration_folds_the_legacy_row_into_the_prefixed_one() {
		update_option(
			WP_Print_Options::LEGACY_OPTION,
			array(
				'print_html' => "<a href=\"%PRINT_URL%\">Tom & Jerry\\'s Post</a>",
				'disclaimer' => "Copyright \\'26",
			)
		);
		update_option( WP_Print_Options::LEGACY_VERSION, '1' );

		WP_Print_Options::maybe_upgrade();

		$this->assertSame( '<a href="%PRINT_URL%">Tom & Jerry\'s Post</a>', WP_Print_Options::get( 'print_html' ) );
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
		update_option( WP_Print_Options::LEGACY_OPTION, array( 'disclaimer' => 'Old' ) );
		update_option( WP_Print_Options::OPTION, array( 'disclaimer' => 'New' ) );

		WP_Print_Options::maybe_upgrade();

		$this->assertSame( 'New', WP_Print_Options::get( 'disclaimer' ) );
	}

	/**
	 * And it is idempotent: running it again must not eat another backslash, nor
	 * write defaults over a row that has already been migrated.
	 *
	 * Gating on the version rather than on "does the old shape still look present"
	 * is what makes this hold.
	 */
	public function test_migration_is_idempotent() {
		update_option( WP_Print_Options::LEGACY_OPTION, array( 'disclaimer' => 'Back\\\\slash kept' ) );

		WP_Print_Options::maybe_upgrade();
		$once = WP_Print_Options::get( 'disclaimer' );

		WP_Print_Options::maybe_upgrade();
		WP_Print_Options::maybe_upgrade();

		$this->assertSame( $once, WP_Print_Options::get( 'disclaimer' ) );
	}

	/**
	 * A migrated install keeps its settings; the migration must not reset them.
	 */
	public function test_migration_does_not_overwrite_settings_with_defaults() {
		WP_Print_Options::maybe_upgrade();
		update_option(
			WP_Print_Options::OPTION,
			array(
				'disclaimer' => 'Mine',
				'links'      => 0,
			)
		);

		WP_Print_Options::maybe_upgrade();

		$this->assertSame( 'Mine', WP_Print_Options::get( 'disclaimer' ) );
		$this->assertSame( 0, WP_Print_Options::can( 'links' ) );
	}
}
