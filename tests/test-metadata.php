<?php
/**
 * What is true of WP-Print and of no other plugin.
 *
 * The twenty-three assertions §7.2 asks of all nineteen live in
 * Plugin_Metadata_TestCase, a byte-identical copy of
 * _standards/templates/helper-metadata-testcase.php. What is left here is the
 * three declarations that class cannot derive, the hooks it reaches back
 * through, and the few checks that are genuinely this plugin's: the two legacy
 * rows it has to clear, the two icons it replaced with an inline SVG, and the
 * two core filters it consumes rather than fires.
 *
 * @package WP-Print
 */

/**
 * WP-Print against §7.2.
 *
 * @coversNothing
 */
class WP_Print_Metadata_Test extends Plugin_Metadata_TestCase {

	/**
	 * The version this release ships.
	 *
	 * @return string
	 */
	protected function expected_version() {
		return '3.0.0';
	}

	/**
	 * The prefix every class the plugin declares carries.
	 *
	 * @return string
	 */
	protected function class_prefix() {
		return 'WP_Print';
	}

	/**
	 * Every break a site owner updating from the released 2.55 would notice.
	 *
	 * Permalinks have to be re-saved, two option rows moved, the icon setting
	 * and two template variables are gone, the mirrored stylesheet and its
	 * webfont call with them, and six classes were renamed out of a prefix far
	 * too common to leave unclaimed.
	 *
	 * @return string[]
	 */
	protected function upgrade_notice_subjects() {
		return array(
			'6.8',
			'8.2',
			// The one manual step.
			'Permalinks',
			// The rows that moved.
			'print_options',
			'wp_print_options',
			'print_db_version',
			'wp_print_version',
			// The template variables: one retired, one changed in meaning, one
			// new.
			'%PRINT_ICON_URL%',
			'%PRINT_ICON%',
			'%PRINT_TEXT%',
			'%POST_TYPE%',
			// The mirrored stylesheet, and what it was calling out to.
			'print-css-rtl.css',
			'fonts.googleapis.com',
			// The theme-overridable template and the sheet it now loads.
			'print-posts.php',
			'css/wp-print.css',
			// The class renames.
			'Print_Options',
			'WP_Print_Options',
			// The one signature change, and the tags that did not change.
			'print_link()',
			'$display',
			'[print_link]',
			'[donotprint]',
		);
	}

	/**
	 * The settings row, the marker row and the two pre-3.0.0 rows.
	 *
	 * @return void
	 */
	protected function seed_option_rows() {
		$this->set_options();
		$this->write_version_row();
	}

	/**
	 * Write the marker row through the plugin's own upgrade routine.
	 *
	 * @return void
	 */
	protected function write_version_row() {
		WP_Print_Options::maybe_upgrade();
	}

	/**
	 * Round-trip the settings sanitiser.
	 *
	 * @param array $input What the settings form is pretending to have posted.
	 * @return array
	 */
	protected function sanitize_settings( array $input ) {
		return (array) WP_Print_Settings::sanitize( $input );
	}

	/**
	 * Two real settings keys, so the sanitiser has work of its own to do.
	 *
	 * @return array
	 */
	protected function settings_fixture() {
		return array(
			'print_html' => '<a href="%PRINT_URL%">Print This</a>',
			'comments'   => 1,
		);
	}

	/**
	 * Register the front-end assets and the settings screen's.
	 *
	 * @return void
	 */
	protected function register_plugin_assets() {
		WP_Print_Template::register_assets();
		WP_Print_Admin::enqueue( 'settings_page_' . WP_Print_Admin::PAGE );
	}

	/**
	 * The two pre-3.0.0 rows go too, and nothing else does.
	 *
	 * The shared uninstall test reads wp_print_% only, which is the right rule
	 * for eighteen plugins and one row short here: a site that never opened
	 * wp-admin after updating never ran the migration, so it still carries
	 * print_options and print_db_version under their old, unprefixed names.
	 * Unprefixed row names are also the ones an over-broad uninstaller can take
	 * a neighbour's data with, so the negative half is asserted beside it.
	 */
	public function test_uninstall_clears_the_legacy_rows_and_nothing_else() {
		update_option( WP_Print_Options::LEGACY_OPTION, array( 'post_text' => 'Legacy' ) );
		update_option( WP_Print_Options::LEGACY_VERSION, '4' );
		update_option( 'an_unrelated_option', 'keep me' );

		$this->run_uninstall();

		wp_cache_flush();

		$this->assertFalse( get_option( WP_Print_Options::LEGACY_OPTION, false ), 'The pre-3.0.0 settings row survived uninstall.' );
		$this->assertFalse( get_option( WP_Print_Options::LEGACY_VERSION, false ), 'The pre-3.0.0 schema marker survived uninstall.' );
		$this->assertSame( 'keep me', get_option( 'an_unrelated_option' ), 'Uninstall took a row that was not its own.' );
	}

	/**
	 * No raster image ships, in any directory.
	 *
	 * The two bundled print GIFs are one inline SVG now, which is why the icon
	 * setting could go: there is no longer a choice to make.
	 */
	public function test_no_raster_images_ship() {
		$found = array();

		foreach ( $this->shipped_directories() as $directory ) {
			foreach ( array( 'gif', 'png', 'jpg', 'jpeg', 'bmp', 'ico' ) as $extension ) {
				$found = array_merge( $found, (array) glob( $directory . '/*.' . $extension ) );
			}
		}

		$this->assertSame( array(), array_filter( $found ), 'Raster images are replaced by inline SVG.' );
		$this->assertDirectoryDoesNotExist( $this->metadata_root() . '/images', 'No raster images ship; the icons are inline SVG.' );
	}

	/**
	 * Every fired hook carries the plugin's prefix.
	 *
	 * Core's the_content and comment_text are applied rather than fired: the
	 * printable page runs the post and its comments through the same filters
	 * the theme would have. The query var is unprefixed too and must stay so -
	 * /a-post/print/ is the public URL of every printable page and predates the
	 * rule by twenty years.
	 */
	public function test_every_hook_the_plugin_fires_is_prefixed() {
		preg_match_all(
			"/(?:apply_filters|do_action)(?:_ref_array)?\(\s*'([a-z0-9_]+)'/",
			wp_print_test_source_code(),
			$hooks
		);

		$this->assertNotEmpty( $hooks[1], 'The plugin fires at least one hook of its own.' );

		$consumed = array( 'the_content', 'comment_text' );

		foreach ( $hooks[1] as $hook ) {
			if ( in_array( $hook, $consumed, true ) ) {
				continue;
			}

			$this->assertStringStartsWith( 'wp_print_', $hook, "{$hook} is not prefixed." );
		}
	}

	/**
	 * Five tags, which is what the listing shows.
	 */
	public function test_the_readme_lists_five_tags() {
		$this->assertCount( 5, explode( ',', $this->readme_field( 'Tags' ) ), 'wordpress.org reads at most five tags, so a sixth is silently dropped.' );
	}

	/**
	 * The copyright block agrees with the header two lines above it.
	 *
	 * Five plugins in this collection carry a version-2-only block directly
	 * beneath a "GPLv2 or later" header and a GPL-2.0-or-later composer.json,
	 * which is a self-contradicting licence statement. WP-Print is not one of
	 * them, and this is here so it does not become one.
	 */
	public function test_the_licence_block_is_the_or_later_variant() {
		$this->assertSame( 'GPLv2 or later', $this->header_field( 'License' ) );
		$this->assertStringContainsString( 'either version 2 of the License, or', $this->plugin_file() );
		$this->assertStringContainsString( '(at your option) any later version.', $this->plugin_file() );
	}

	/**
	 * The donations paragraph is the collection's exact wording.
	 */
	public function test_the_donations_paragraph_is_the_family_wording() {
		$this->assertStringContainsString(
			'I spent most of my free time creating, updating, maintaining and supporting'
				. ' these plugins, if you really love my plugins and could spare me a couple of'
				. ' bucks, I will really appreciate it. If not feel free to use it without any'
				. ' obligations.',
			$this->readme()
		);
		$this->assertStringNotContainsString( 'as my school allowance', $this->readme() );
	}
}
