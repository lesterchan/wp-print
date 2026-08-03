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

		wp_set_current_user( $this->create_admin() );

		$this->set_options();

		require_once ABSPATH . 'wp-admin/includes/template.php';
		WP_Print_Settings::register();
	}

	/**
	 * Render one tab's fields, which is what do_settings_sections() does on the
	 * screen.
	 *
	 * @param string $tab Tab slug.
	 * @return string
	 */
	private function render_tab( $tab ) {
		ob_start();
		do_settings_sections( WP_Print_Settings::tab_page( $tab ) );

		return ob_get_clean();
	}

	/**
	 * Both tabs' fields, for the assertions that do not care which tab a control
	 * is on.
	 *
	 * @return string
	 */
	private function render_fields() {
		$html = '';

		foreach ( array_keys( WP_Print_Settings::tabs() ) as $tab ) {
			$html .= $this->render_tab( $tab );
		}

		return $html;
	}

	/**
	 * The setting is registered in the group the form posts, with the sanitize
	 * callback attached.
	 */
	public function test_the_setting_is_registered_in_the_right_group() {
		global $wp_registered_settings;

		$this->assertArrayHasKey( WP_Print_Options::OPTION, $wp_registered_settings, 'The settings row is registered, so its sanitise callback is attached.' );

		$registered = $wp_registered_settings[ WP_Print_Options::OPTION ];

		$this->assertSame( WP_Print_Settings::GROUP, $registered['group'], 'The setting is registered in the group the form posts.' );
		$this->assertSame( array( 'WP_Print_Settings', 'sanitize' ), $registered['sanitize_callback'], 'Naming the sanitiser, so a save cannot bypass it.' );
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
		$this->assertStringContainsString( WP_Print_Settings::GROUP, $fields, 'The form posts the registered group.' );
		$this->assertStringContainsString( "name='option_page'", $fields, 'With the option_page field core checks it against.' );
		$this->assertStringContainsString( '_wpnonce', $fields, 'And a nonce.' );
	}

	/**
	 * Each section is registered against the tab that draws it.
	 *
	 * A section registered against the wrong page renders nothing and says nothing
	 * about it, and with two tabs there are now two wrong pages to choose from.
	 */
	public function test_each_section_is_registered_against_its_own_tab() {
		global $wp_settings_sections;

		$settings  = WP_Print_Settings::tab_page( 'settings' );
		$templates = WP_Print_Settings::tab_page( 'templates' );

		$this->assertArrayHasKey( $settings, $wp_settings_sections, 'The settings tab has its own registered section page.' );
		$this->assertArrayHasKey( $templates, $wp_settings_sections, 'The templates tab has its own registered section page.' );

		$this->assertSame(
			array( WP_Print_Settings::SECTION_CONTENT ),
			array_keys( $wp_settings_sections[ $settings ] ),
			'The settings tab carries the content section and no other.'
		);
		$this->assertSame(
			array( WP_Print_Settings::SECTION_LINK ),
			array_keys( $wp_settings_sections[ $templates ] ),
			'And the templates tab carries the link section.'
		);
	}

	/**
	 * The tabs are the family's two, spelled the family's way.
	 *
	 * Not "Print Settings" and "Print Templates": the h1 above them has already
	 * said which plugin this is, and a tab that repeats it is a tab that does not
	 * fit beside the same two in the eighteen other plugins.
	 */
	public function test_the_screen_has_the_two_family_tabs() {
		$this->assertSame(
			array(
				'settings'  => 'Settings',
				'templates' => 'Templates',
			),
			WP_Print_Settings::tabs(),
			'The screen has the two family tabs, in order and labelled.'
		);
	}

	/**
	 * An unknown, absent or hostile ?tab= draws the first tab rather than an empty
	 * form.
	 */
	public function test_an_unknown_tab_falls_back_to_the_first() {
		$this->assertSame( 'settings', WP_Print_Settings::current_tab(), 'With no tab asked for, the first opens.' );

		$_GET['tab'] = 'templates';
		$this->assertSame( 'templates', WP_Print_Settings::current_tab(), 'A tab asked for by name opens.' );

		$_GET['tab'] = 'nonsense';
		$this->assertSame( 'settings', WP_Print_Settings::current_tab(), 'And an unknown one falls back rather than drawing an empty screen.' );

		unset( $_GET['tab'] );
	}

	/**
	 * Each tab draws its own controls and none of the other's.
	 *
	 * A tab drawing both would post both, which is the failure that makes the
	 * split pointless; a tab drawing neither is a blank screen.
	 */
	public function test_each_tab_draws_only_its_own_fields() {
		$settings  = $this->render_tab( 'settings' );
		$templates = $this->render_tab( 'templates' );

		$this->assertStringContainsString( WP_Print_Options::OPTION . '[print_html]', $templates, 'The template field is on the templates tab.' );
		$this->assertStringNotContainsString( WP_Print_Options::OPTION . '[print_html]', $settings, 'And not on the settings tab, so neither carries the other fields.' );

		foreach ( array( 'comments', 'links', 'images', 'thumbnail', 'videos', 'disclaimer' ) as $key ) {
			$this->assertStringContainsString( WP_Print_Options::OPTION . '[' . $key . ']', $settings, 'The ' . $key . ' field is missing from the settings tab it belongs to.' );
			$this->assertStringNotContainsString( WP_Print_Options::OPTION . '[' . $key . ']', $templates, 'The ' . $key . ' field leaked onto the templates tab.' );
		}
	}

	/**
	 * Saving one tab must not blank what the other tab owns.
	 *
	 * The Settings API hands the sanitize callback only the fields the submitting
	 * form posted, so a sanitizer returning just what it was given would wipe a
	 * site's link template the first time anybody saved a toggle -- silently, and
	 * with no way back. Both directions, because only one of them destroys
	 * something a site spent time on and it is not the obvious one.
	 */
	public function test_saving_one_tab_keeps_the_other_tabs_values() {
		$this->set_options(
			array(
				'print_html' => '<a href="%PRINT_URL%">mine</a>',
				'disclaimer' => 'MY DISCLAIMER',
				'comments'   => 0,
			)
		);

		// The Settings tab: every control it owns, and not print_html.
		update_option(
			WP_Print_Options::OPTION,
			array(
				'comments'   => 1,
				'links'      => 0,
				'images'     => 0,
				'thumbnail'  => 1,
				'videos'     => 1,
				'disclaimer' => 'MY DISCLAIMER',
			)
		);

		$this->assertSame( '<a href="%PRINT_URL%">mine</a>', WP_Print_Options::get( 'print_html' ), 'Saving the settings tab leaves the template alone.' );
		$this->assertSame( 1, WP_Print_Options::can( 'comments' ), 'And its own toggle is written.' );

		// And the Templates tab: print_html, and nothing else.
		update_option(
			WP_Print_Options::OPTION,
			array( 'print_html' => '<a href="%PRINT_URL%">%POST_TYPE%</a>' )
		);

		$this->assertSame( '<a href="%PRINT_URL%">%POST_TYPE%</a>', WP_Print_Options::get( 'print_html' ), 'Saving the templates tab writes the template.' );
		$this->assertSame( 'MY DISCLAIMER', WP_Print_Options::get( 'disclaimer' ), 'While the settings tab disclaimer survives.' );
		$this->assertSame( 1, WP_Print_Options::can( 'comments' ), 'And its toggles.' );
		$this->assertSame( 0, WP_Print_Options::can( 'links' ), 'Including the ones that were off.' );
		$this->assertSame( 1, WP_Print_Options::can( 'thumbnail' ), 'So one tab cannot blank another.' );
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
				'comments'    => '1',
			)
		);

		$this->assertSame( 1, WP_Print_Options::can( 'comments' ), 'Writing the option runs the sanitiser, which normalises the toggle.' );
		$this->assertArrayNotHasKey( 'print_style', (array) get_option( WP_Print_Options::OPTION ), 'Writing the option runs the sanitiser, which drops the retired print_style key.' );
	}

	/**
	 * Every field renders and nests its name under the option key.
	 */
	public function test_every_field_renders() {
		$html = $this->render_fields();

		foreach ( array( 'print_html', 'comments', 'links', 'images', 'thumbnail', 'videos', 'disclaimer' ) as $key ) {
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

		$this->assertStringNotContainsString( 'onclick=', $html, 'The fields carry no inline click handler.' );
		$this->assertStringNotContainsString( 'onchange=', $html, 'And no change handler.' );
		$this->assertStringContainsString( 'data-print-restore', $html, 'The restore control is bound by data attribute instead.' );
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
				'print_html' => '</textarea><script>alert(1)</script>',
				'disclaimer' => '</textarea><script>alert(1)</script>',
			)
		);

		$html = $this->render_fields();

		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html, 'Stored markup is escaped into the form.' );
		$this->assertStringNotContainsString( '</textarea><script', $html, 'And cannot close the textarea early to escape it.' );
	}

	/**
	 * The link template is always on screen, and nothing hides it.
	 *
	 * It used to be revealed by the Custom entry of a four-way style dropdown, and
	 * the other three entries were templates too -- written in PHP rather than in
	 * the box. There is one template now and it is on a tab of its own, so there is
	 * nothing left to reveal it from.
	 */
	public function test_the_template_is_not_hidden_behind_anything() {
		$templates = $this->render_tab( 'templates' );

		$this->assertStringContainsString( 'id="wp-print-html"', $templates, 'The template field is on the screen.' );
		$this->assertStringNotContainsString( 'hidden', $templates, 'Not hidden behind anything.' );
		$this->assertStringNotContainsString( 'print_style', $templates, 'And with no style selector left to gate it.' );
	}

	/**
	 * There is no icon picker any more, and no field for the setting behind it.
	 *
	 * One inline SVG replaced the two bundled GIFs, so the choice it offered no
	 * longer exists. A field left behind would write a key nothing reads.
	 */
	public function test_the_screen_offers_no_icon_setting() {
		$html = $this->render_fields();

		$this->assertStringNotContainsString( 'print_icon', $html, 'The withdrawn icon setting is gone from the screen.' );
		$this->assertStringNotContainsString( 'images/', $html, 'Along with the image path it used to point at.' );
	}

	/**
	 * The placeholder documentation is emitted as code spans rather than inside a
	 * translatable string, so phpcbf cannot renumber %PRINT_URL% as a printf
	 * placeholder and show users "%1$PRINT_URL%".
	 */
	public function test_placeholders_are_documented_outside_translatable_strings() {
		$html = $this->render_fields();

		$this->assertStringContainsString( '<code>%PRINT_URL%</code>', $html, 'The URL placeholder is documented as code.' );
		$this->assertStringContainsString( '<code>%POST_TYPE%</code>', $html, 'The post type placeholder.' );
		$this->assertStringContainsString( '<code>%PRINT_ICON%</code>', $html, 'And the icon placeholder.' );
		$this->assertStringNotContainsString( '%1$PRINT', $html, 'With no printf specifier left in the documentation.' );
		$this->assertStringNotContainsString( '%1$POST', $html, 'In either placeholder.' );

		// The retired two are not offered. %PRINT_ICON_URL% named a URL and the
		// glyph is inline; %PRINT_TEXT% named a link label and there are no link
		// labels, only the wording written into the template itself.
		$this->assertStringNotContainsString( '%PRINT_ICON_URL%', $html, 'The withdrawn icon URL placeholder is not documented.' );
		$this->assertStringNotContainsString( '<code>%PRINT_TEXT%</code>', $html, 'Nor the withdrawn text one.' );
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

		$this->assertSame( 1, $clean['comments'], 'A ticked toggle normalises to one.' );
		$this->assertSame( 0, $clean['images'], 'And an unticked one to zero.' );
	}

	/**
	 * Every retired key is dropped on any write, however it got there.
	 *
	 * `print_icon` chose between two bundled GIFs and there is one inline SVG;
	 * `print_style`, `post_text` and `page_text` were the link's four settings and
	 * there is one template. A row carrying any of them would be storing a setting
	 * nothing reads, and the "keep what this screen does not render" merge would
	 * otherwise preserve it for ever.
	 */
	public function test_sanitize_drops_every_retired_key() {
		$this->set_options(
			array(
				'print_icon'  => 'print.gif',
				'print_style' => 3,
				'post_text'   => 'Print This Post',
				'page_text'   => 'Print This Page',
			)
		);

		$clean = WP_Print_Settings::sanitize( array( 'comments' => 1 ) );

		foreach ( WP_Print_Options::retired_keys() as $key ) {
			$this->assertArrayNotHasKey( $key, $clean, "{$key} survived a save." );
		}
	}

	/**
	 * And a retired key posted at the sanitizer is not stored either, so a
	 * hand-made POST cannot put one back.
	 */
	public function test_sanitize_does_not_store_a_posted_retired_key() {
		$clean = WP_Print_Settings::sanitize(
			array(
				'print_style' => 3,
				'post_text'   => 'Mine',
				'page_text'   => 'Theirs',
			)
		);

		foreach ( WP_Print_Options::retired_keys() as $key ) {
			$this->assertArrayNotHasKey( $key, $clean, 'The sanitiser stored the retired key ' . $key . ' back out of the post.' );
		}
	}

	/**
	 * An HTML value must not come back slashed either.
	 */
	public function test_sanitize_does_not_slash_html_values() {
		$clean = WP_Print_Settings::sanitize( array( 'disclaimer' => "O'Reilly & Sons <strong>2026</strong>" ) );

		$this->assertSame( "O'Reilly & Sons <strong>2026</strong>", $clean['disclaimer'], 'The sanitiser does not slash an HTML value, so an apostrophe survives as typed.' );
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

		$this->assertStringNotContainsString( '<script', $clean['disclaimer'], 'Without unfiltered_html a script is stripped from the disclaimer.' );
		$this->assertStringContainsString( '<strong>ok</strong>', $clean['disclaimer'], 'While the markup a site may use survives.' );
		$this->assertStringNotContainsString( 'onclick', $clean['print_html'], 'And a handler is stripped from the template.' );
	}

	/**
	 * A key already in the row that this screen does not render survives a save,
	 * so a save cannot silently drop a third party's or a filter's addition.
	 */
	public function test_sanitize_keeps_stored_keys_it_does_not_render() {
		/*
		 * Seeded with the sanitiser detached, which is the only way to get an
		 * unrendered key into the row at all. register_setting() hangs
		 * WP_Print_Settings::sanitize() on sanitize_option_wp_print_options, so it
		 * runs on every update_option() for that row and not merely on a form
		 * save -- and dropping a posted key this screen does not render is exactly
		 * what it is supposed to do, as the next test asserts. So writing the key
		 * through set_options() would have it stripped on the way in, and the
		 * assertion below would be reading a row that never held it. In real life
		 * such a key gets there by a filter or an older version writing it
		 * directly, which is what detaching reproduces.
		 */
		$hook = 'sanitize_option_' . WP_Print_Options::OPTION;

		remove_filter( $hook, array( 'WP_Print_Settings', 'sanitize' ) );
		$this->set_options( array( 'third_party' => 'keep me' ) );
		add_filter( $hook, array( 'WP_Print_Settings', 'sanitize' ) );

		$this->assertSame(
			'keep me',
			WP_Print_Options::get( 'third_party' ),
			'The fixture did not land, so the assertion below would prove nothing.'
		);

		$clean = WP_Print_Settings::sanitize( array( 'comments' => 1 ) );

		$this->assertSame( 'keep me', $clean['third_party'], 'A save dropped a stored key the screen does not render.' );
	}

	/**
	 * The other direction: an unexpected posted key is not stored, or the row
	 * becomes a dumping ground for whatever a request carries.
	 */
	public function test_sanitize_drops_an_unexpected_posted_key() {
		$clean = WP_Print_Settings::sanitize(
			array(
				'comments'     => 1,
				'injected_key' => 'nope',
			)
		);

		$this->assertArrayNotHasKey( 'injected_key', $clean, 'A key the sanitiser does not know is dropped rather than stored.' );
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
				'disclaimer' => 'MY DISCLAIMER',
				'print_html' => '<a href="%PRINT_URL%">mine</a>',
				'thumbnail'  => 1,
			)
		);

		$clean = WP_Print_Settings::sanitize( array( 'comments' => 1 ) );

		$this->assertSame( 'MY DISCLAIMER', $clean['disclaimer'], 'A partial update keeps the disclaimer.' );
		$this->assertSame( '<a href="%PRINT_URL%">mine</a>', $clean['print_html'], 'The template.' );
		$this->assertSame( 1, $clean['thumbnail'], 'And every toggle.' );
		$this->assertSame( 1, $clean['comments'], 'Not only the ones the submission named.' );
	}

	/**
	 * Emptying an HTML field is still allowed - a site may not want a disclaimer.
	 * That is a submitted empty value, not an absent key.
	 */
	public function test_an_html_field_can_be_emptied_deliberately() {
		$this->set_options( array( 'disclaimer' => 'MY DISCLAIMER' ) );

		$this->assertSame( '', WP_Print_Settings::sanitize( array( 'disclaimer' => '' ) )['disclaimer'], 'While an HTML field can still be emptied deliberately, rather than taking its default back.' );
	}
}
