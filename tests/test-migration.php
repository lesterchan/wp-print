<?php
/**
 * The link settings that became one template, and the sites they belonged to.
 *
 * Up to 3.0.0 the print link was four settings: a style out of four, and two
 * link labels the three fixed styles interpolated. It is one HTML template now,
 * so every one of those sites has to be handed a string that renders what it was
 * already rendering -- an icon-only site stays icon-only, a site that renamed its
 * link keeps the name it chose.
 *
 * The wording is where it gets lossy, and deliberately so. Two labels can say two
 * things and one template can say one, so the pair is recognised where it is
 * still the shipped one and collapsed to %POST_TYPE%, which says both and says
 * something sensible on a custom post type as well. Where the pair was
 * customised and diverged, the post wording wins and the page wording is lost.
 * That is stated in the readme's upgrade notice and asserted here, because an
 * accepted loss that nobody wrote down is just a bug with a good excuse.
 *
 * Both entry points are exercised. Updating through the Plugins screen never
 * fires the activation hook and leaves admin_init -> maybe_upgrade() running
 * alone; reactivating fires the hook and never sees admin_init. They are not the
 * same code path and only one of them has the settings screen's sanitize
 * callback attached.
 *
 * @package WP-Print
 */

/**
 * Tests for the 3.0.0 link-settings migration.
 *
 * @covers WP_Print_Options
 */
class WP_Print_Migration_Test extends WP_Print_TestCase {

	/**
	 * The wording every release up to 2.58.3 shipped.
	 *
	 * @var string
	 */
	const STOCK_POST = 'Print This Post';

	/**
	 * And its other half.
	 *
	 * @var string
	 */
	const STOCK_PAGE = 'Print This Page';

	/**
	 * Put the install into the shape a pre-3.0.0 site is in.
	 *
	 * The unprefixed row and the bare schema counter, and nothing else -- which is
	 * exactly what a site updating from 2.58.3 hands the migration.
	 *
	 * @param array $legacy The legacy settings row.
	 * @return void
	 */
	private function install_legacy( array $legacy ) {
		update_option( WP_Print_Options::LEGACY_OPTION, $legacy );
		update_option( WP_Print_Options::LEGACY_VERSION, '2.58.3' );
	}

	/**
	 * The template the migration produced, read off the row rather than merged.
	 *
	 * @return string|null
	 */
	private function stored_template() {
		$stored = get_option( WP_Print_Options::OPTION );

		return is_array( $stored ) && isset( $stored['print_html'] ) ? $stored['print_html'] : null;
	}

	/**
	 * A site that never touched a setting gets the string the plugin now ships.
	 *
	 * Not merely something equivalent: the shipped default is what the Restore
	 * Default Template button puts back, so a site that was on the defaults and a
	 * site that presses that button have to end up with the same row.
	 */
	public function test_the_shipped_settings_become_the_shipped_template() {
		$this->install_legacy(
			array(
				'post_text'   => self::STOCK_POST,
				'page_text'   => self::STOCK_PAGE,
				'print_style' => WP_Print_Options::LEGACY_STYLE_ICON_TEXT,
			)
		);

		WP_Print_Options::maybe_upgrade();

		$this->assertSame( WP_Print_Options::default_template(), $this->stored_template(), 'A site on the shipped settings migrates to the shipped template.' );
	}

	/**
	 * An icon-only site stays icon-only: the glyph, and the wording in the two
	 * attributes that were the link's only accessible name.
	 */
	public function test_an_icon_only_site_stays_icon_only() {
		$this->install_legacy(
			array(
				'post_text'   => self::STOCK_POST,
				'page_text'   => self::STOCK_PAGE,
				'print_style' => WP_Print_Options::LEGACY_STYLE_ICON,
			)
		);

		WP_Print_Options::maybe_upgrade();

		$this->assertSame(
			'<a href="%PRINT_URL%" rel="nofollow" title="Print This %POST_TYPE%"'
				. ' aria-label="Print This %POST_TYPE%">%PRINT_ICON%</a>',
			$this->stored_template(),
			'An icon-only site keeps an icon-only template.'
		);
	}

	/**
	 * A text-only site keeps its words and does not get a glyph it never had.
	 */
	public function test_a_text_only_site_stays_text_only() {
		$this->install_legacy(
			array(
				'post_text'   => self::STOCK_POST,
				'page_text'   => self::STOCK_PAGE,
				'print_style' => WP_Print_Options::LEGACY_STYLE_TEXT,
			)
		);

		WP_Print_Options::maybe_upgrade();

		$this->assertSame(
			'<a href="%PRINT_URL%" rel="nofollow" title="Print This %POST_TYPE%">Print This %POST_TYPE%</a>',
			$this->stored_template(),
			'A text-only site keeps a text-only one.'
		);
		$this->assertStringNotContainsString( '%PRINT_ICON%', (string) $this->stored_template(), 'With no icon placeholder added to it.' );
	}

	/**
	 * A row written before print_style existed reads as the style that was the
	 * default when it did not, which is the one the plugin has always shipped.
	 */
	public function test_a_row_with_no_style_reads_as_the_shipped_one() {
		$this->install_legacy( array( 'post_text' => self::STOCK_POST ) );

		WP_Print_Options::maybe_upgrade();

		$this->assertSame( WP_Print_Options::default_template(), $this->stored_template(), 'A row that never named a style reads as the shipped one.' );
	}

	/**
	 * A site that renamed its link keeps the name, verbatim and without a
	 * placeholder: %POST_TYPE% would put a word back that this site took out.
	 */
	public function test_customised_wording_is_kept_verbatim() {
		$this->install_legacy(
			array(
				'post_text'   => 'Take this away with you',
				'page_text'   => 'Take this page away',
				'print_style' => WP_Print_Options::LEGACY_STYLE_TEXT,
			)
		);

		WP_Print_Options::maybe_upgrade();

		$this->assertSame(
			'<a href="%PRINT_URL%" rel="nofollow" title="Take this away with you">Take this away with you</a>',
			$this->stored_template(),
			'Customised wording is carried over exactly.'
		);
		$this->assertStringNotContainsString( '%POST_TYPE%', (string) $this->stored_template(), 'Rather than being collapsed into the token, which would lose it.' );
	}

	/**
	 * The accepted loss, written down.
	 *
	 * One template cannot hold two arbitrary strings, so a site whose two labels
	 * said different things keeps the post one and loses the page one. This is
	 * here so that the day somebody decides that is not acceptable, the test says
	 * what the current behaviour is rather than the readme alone.
	 */
	public function test_divergent_wording_loses_the_page_half() {
		$this->install_legacy(
			array(
				'post_text'   => 'Print this article',
				'page_text'   => 'Print this document',
				'print_style' => WP_Print_Options::LEGACY_STYLE_TEXT,
			)
		);

		WP_Print_Options::maybe_upgrade();

		$this->assertStringContainsString( 'Print this article', (string) $this->stored_template(), 'Where the post and page wordings differ, the post half is kept.' );
		$this->assertStringNotContainsString( 'Print this document', (string) $this->stored_template(), 'And the page half is lost, which one template cannot express.' );
	}

	/**
	 * Half a customised pair is still a customised pair: only both labels still
	 * being the shipped ones means the site never chose any wording at all.
	 */
	public function test_a_customised_page_label_stops_the_wording_collapsing() {
		$this->install_legacy(
			array(
				'post_text'   => self::STOCK_POST,
				'page_text'   => 'Print this document',
				'print_style' => WP_Print_Options::LEGACY_STYLE_TEXT,
			)
		);

		WP_Print_Options::maybe_upgrade();

		$this->assertSame(
			'<a href="%PRINT_URL%" rel="nofollow" title="Print This Post">Print This Post</a>',
			$this->stored_template(),
			'A customised page label alone stops the collapse, so the post wording survives.'
		);
	}

	/**
	 * The wording lands in an attribute as well as in the anchor, so the attribute
	 * copy is escaped as it is written rather than left for whatever reads the row
	 * next to remember.
	 */
	public function test_customised_wording_is_escaped_into_the_title() {
		$this->install_legacy(
			array(
				'post_text'   => 'Tom &amp; Jerry\'s "Post"',
				'page_text'   => 'Tom &amp; Jerry\'s "Page"',
				'print_style' => WP_Print_Options::LEGACY_STYLE_TEXT,
			)
		);

		WP_Print_Options::maybe_upgrade();

		$this->assertSame(
			'<a href="%PRINT_URL%" rel="nofollow" title="Tom &amp; Jerry&#039;s &quot;Post&quot;">'
				. 'Tom &amp; Jerry\'s "Post"</a>',
			$this->stored_template(),
			'Customised wording is escaped into the title attribute and left as text in the body.'
		);
	}

	/**
	 * A label the old admin screen slashed on the way in is unslashed before it
	 * reaches the template, or every visitor is shown the backslashes.
	 */
	public function test_a_slashed_label_is_unslashed_into_the_template() {
		$this->install_legacy(
			array(
				'post_text'   => "Print O\\'Brien\\'s post",
				'page_text'   => "Print O\\'Brien\\'s page",
				'print_style' => WP_Print_Options::LEGACY_STYLE_TEXT,
			)
		);

		WP_Print_Options::maybe_upgrade();

		$this->assertStringContainsString( ">Print O'Brien's post</a>", (string) $this->stored_template(), 'A slashed label is unslashed into the template.' );
		$this->assertStringNotContainsString( '\\', (string) $this->stored_template(), 'Leaving no backslash behind.' );
	}

	/**
	 * A site already writing its own template keeps it byte for byte.
	 *
	 * That row already holds the only thing 3.0.0 stores, and rewriting it would
	 * be the migration overwriting the exact thing it exists to preserve. The
	 * retired %PRINT_TEXT% is left in it deliberately: the placeholder is gone, and
	 * a template still carrying it renders it as written, which is visibly wrong on
	 * the page rather than silently missing its words.
	 */
	public function test_a_custom_template_is_left_alone() {
		$template = '<a class="mine" href="%PRINT_URL%" title="%PRINT_TEXT%">%PRINT_ICON% %PRINT_TEXT%</a>';

		$this->install_legacy(
			array(
				'post_text'   => 'Print this article',
				'page_text'   => 'Print this document',
				'print_style' => WP_Print_Options::LEGACY_STYLE_CUSTOM,
				'print_html'  => $template,
			)
		);

		WP_Print_Options::maybe_upgrade();

		$this->assertSame( $template, $this->stored_template(), 'A template already customised is left alone.' );
	}

	/**
	 * The four settings the template replaced are taken off the row.
	 *
	 * Left behind they would be read by nothing and written back by the "keep what
	 * this screen does not render" merge for ever.
	 */
	public function test_the_retired_settings_are_taken_off_the_row() {
		$this->install_legacy(
			array(
				'post_text'   => self::STOCK_POST,
				'page_text'   => self::STOCK_PAGE,
				'print_style' => WP_Print_Options::LEGACY_STYLE_TEXT,
				'print_icon'  => 'printer.gif',
				'links'       => 0,
			)
		);

		WP_Print_Options::maybe_upgrade();

		$stored = (array) get_option( WP_Print_Options::OPTION );

		foreach ( WP_Print_Options::retired_keys() as $key ) {
			$this->assertArrayNotHasKey( $key, $stored, "{$key} survived the migration." );
		}

		// And the settings that were not retired came through it.
		$this->assertSame( 0, WP_Print_Options::can( 'links' ), 'The retired settings are taken off the row rather than left unreachable.' );
	}

	/**
	 * The admin_init entry point, with the settings screen's sanitize callback
	 * attached -- which is the shape of a real update through the Plugins screen.
	 *
	 * Registration hangs that callback on sanitize_option_wp_print_options,
	 * so every option write the migration makes goes through it, and it drops
	 * exactly the keys the migration is reading. Registration happens first on a
	 * real request because wp-print.php requires the admin classes before it
	 * constructs WP_Print, so this test calls them in that order rather than the
	 * one the test bootstrap happens to load them in.
	 */
	public function test_the_migration_survives_its_own_sanitize_callback() {
		wp_set_current_user( $this->create_admin() );

		$this->install_legacy(
			array(
				'post_text'   => 'Take this away with you',
				'page_text'   => 'Take this away with you',
				'print_style' => WP_Print_Options::LEGACY_STYLE_ICON,
			)
		);

		WP_Print_Settings::register();
		WP_Print_Options::maybe_upgrade();

		$this->assertSame(
			'<a href="%PRINT_URL%" rel="nofollow" title="Take this away with you"'
				. ' aria-label="Take this away with you">%PRINT_ICON%</a>',
			$this->stored_template(),
			'The migration survives its own sanitise callback, which activation does not run.'
		);

		$stored = (array) get_option( WP_Print_Options::OPTION );

		foreach ( WP_Print_Options::retired_keys() as $key ) {
			$this->assertArrayNotHasKey( $key, $stored, 'The retired key ' . $key . ' survived the migration into the stored row.' );
		}
	}

	/**
	 * The commonest install of all, on the path every real update takes.
	 *
	 * A site that never touched a link setting migrates to exactly the shipped
	 * defaults, and that is the one shape the admin path used to lose.
	 * register_setting() is passed a `default`, which answers get_option() with
	 * those defaults for a row that does not exist; update_option() compared the
	 * migrated value against them, found it equal and declined to write. The
	 * legacy row had already been deleted and the markers are stamped either way,
	 * so the upgrade could never run again.
	 *
	 * The test above passes on that bug because its fixture is customised, so its
	 * template differs from the default and the write lands. A fixture that
	 * differs from the defaults cannot see a defect that only shows when it does
	 * not.
	 *
	 * The assertions read the raw row for the same reason: through the registered
	 * default, a row that was never written is indistinguishable from one holding
	 * the defaults.
	 */
	public function test_the_shipped_settings_survive_the_admin_path() {
		wp_set_current_user( $this->create_admin() );

		$this->install_legacy(
			array(
				'post_text'   => self::STOCK_POST,
				'page_text'   => self::STOCK_PAGE,
				'print_style' => WP_Print_Options::LEGACY_STYLE_ICON_TEXT,
			)
		);

		WP_Print_Settings::register();
		WP_Print_Options::maybe_upgrade();

		$raw = get_option( WP_Print_Options::OPTION, false );

		$this->assertIsArray( $raw, 'The migration wrote no settings row at all.' );
		$this->assertSame(
			WP_Print_Options::default_template(),
			isset( $raw['print_html'] ) ? $raw['print_html'] : null,
			'The shipped template is not on the row the migration left behind.'
		);
		$this->assertFalse(
			get_option( WP_Print_Options::LEGACY_OPTION ),
			'The legacy row is gone, so whatever the migration failed to carry across is gone with it.'
		);
	}

	/**
	 * The other entry point: activation, which is what reactivating runs and what
	 * an update through the Plugins screen never fires.
	 */
	public function test_activation_migrates_the_link_settings_too() {
		$this->install_legacy(
			array(
				'post_text'   => 'Take this away with you',
				'page_text'   => 'Take this page away',
				'print_style' => WP_Print_Options::LEGACY_STYLE_TEXT,
			)
		);

		WP_Print::activate();

		$this->assertSame(
			'<a href="%PRINT_URL%" rel="nofollow" title="Take this away with you">Take this away with you</a>',
			$this->stored_template(),
			'Activation migrates the link settings too, not only the content ones.'
		);
		$this->assertFalse( get_option( WP_Print_Options::LEGACY_OPTION ), 'Activation migrates the link settings and deletes the legacy row.' );
	}

	/**
	 * Owners deactivate and reactivate to fix things, sometimes twice, and an
	 * update lands an admin_init pass on top. Every pass after the first has to be
	 * a bystander.
	 */
	public function test_the_migration_is_idempotent() {
		$this->install_legacy(
			array(
				'post_text'   => 'Take this away with you',
				'page_text'   => 'Take this page away',
				'print_style' => WP_Print_Options::LEGACY_STYLE_ICON,
			)
		);

		WP_Print::activate();

		$once = get_option( WP_Print_Options::OPTION );

		WP_Print_Options::maybe_upgrade();
		WP_Print::activate();
		WP_Print_Options::maybe_upgrade();

		$this->assertSame( $once, get_option( WP_Print_Options::OPTION ), 'Running the migration twice leaves the row as it was.' );
	}

	/**
	 * And it is idempotent even when it is made to run again: the settings it
	 * reads are retired, so the second pass finds nothing to synthesise from and
	 * must not overwrite the template the first pass wrote.
	 */
	public function test_a_forced_second_pass_does_not_rewrite_the_template() {
		$this->install_legacy(
			array(
				'post_text'   => 'Take this away with you',
				'print_style' => WP_Print_Options::LEGACY_STYLE_TEXT,
			)
		);

		WP_Print_Options::maybe_upgrade();

		$first = $this->stored_template();

		// The markers gate maybe_upgrade(), so clearing them is the only way to
		// ask the question this test is asking.
		delete_option( WP_Print_Options::VERSION );
		WP_Print_Options::maybe_upgrade();

		$this->assertSame( $first, $this->stored_template(), 'And a forced second pass does not rewrite the template it already wrote.' );
	}

	/**
	 * A fresh install has nothing to migrate and must not have a template written
	 * into its row: a synthesised copy of the default would stop tracking the
	 * default the moment the shipped one changed.
	 */
	public function test_a_fresh_install_gets_no_synthesised_template() {
		WP_Print_Options::maybe_upgrade();

		$this->assertNull( $this->stored_template(), 'A fresh install synthesises no template; there was nothing to migrate from.' );
		$this->assertSame( WP_Print_Options::default_template(), WP_Print_Options::get( 'print_html' ), 'A fresh install gets the shipped template rather than one synthesised from nothing.' );
	}

	/**
	 * An install already stamped with this version is left alone entirely, stale
	 * keys and all. Returning early is what stops every admin request being an
	 * option write.
	 */
	public function test_an_install_already_on_this_version_is_untouched() {
		$stale = array(
			'post_text'  => 'Untouched',
			'print_html' => '<a href="%PRINT_URL%">%PRINT_TEXT%</a>',
		);

		update_option( WP_Print_Options::OPTION, $stale );
		update_option(
			WP_Print_Options::VERSION,
			array(
				'plugin' => WP_PRINT_VERSION,
				'db'     => WP_PRINT_DB_VERSION,
			)
		);

		WP_Print_Options::maybe_upgrade();

		$this->assertSame( $stale, get_option( WP_Print_Options::OPTION ), 'An install already on this version is untouched, stale row and all.' );
	}
}
