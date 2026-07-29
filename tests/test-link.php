<?php
/**
 * Print link markup: all four styles, and the URL it points at.
 *
 * The markup is asserted byte-for-byte because it is the plugin's most visible
 * output and themes style it by class.
 *
 * @package WP-Print
 */

/**
 * Tests for the print link.
 *
 * @covers WP_Print_Link
 * @covers ::print_link
 */
class Test_Print_Link extends WP_UnitTestCase {

	/**
	 * Post fixture.
	 *
	 * @var int
	 */
	private static $post_id;

	/**
	 * Page fixture.
	 *
	 * @var int
	 */
	private static $page_id;

	/**
	 * Create the fixtures once.
	 *
	 * @param WP_UnitTest_Factory $factory Factory.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$post_id = $factory->post->create(
			array(
				'post_title' => 'Link Post',
				'post_name'  => 'link-post',
			)
		);
		self::$page_id = $factory->post->create(
			array(
				'post_title' => 'Link Page',
				'post_name'  => 'link-page',
				'post_type'  => 'page',
			)
		);
	}

	/**
	 * Pretty permalinks, and a known option row.
	 */
	public function set_up() {
		parent::set_up();

		$this->set_permalink_structure( '/%postname%/' );
		update_option( WP_Print_Options::OPTION, WP_Print_Options::get_defaults() );

		// A print_content() call in another test leaves the print-view stand-in
		// registered for [print_link]; restore the real callback. See the note in
		// Test_Print_Content::set_up().
		add_shortcode( 'print_link', array( 'WP_Print_Link', 'shortcode' ) );
	}

	/**
	 * The printer glyph, as the plugin builds it.
	 *
	 * @return string
	 */
	private function icon() {
		return WP_Print_Link::icon();
	}

	/**
	 * Style 1: icon plus text link.
	 */
	public function test_style_one_is_icon_then_text() {
		$this->go_to( get_permalink( self::$post_id ) );
		the_post();

		update_option( WP_Print_Options::OPTION, array_merge( WP_Print_Options::get_defaults(), array( 'print_style' => 1 ) ) );

		$url  = esc_url( get_permalink( self::$post_id ) . 'print/' );
		$icon = $this->icon();
		$text = 'Print This Post';

		$this->assertSame(
			'<a href="' . $url . '" title="' . $text . '" rel="nofollow">' . $icon . '</a>&nbsp;<a href="' . $url . '" title="' . $text . '" rel="nofollow">' . $text . '</a>',
			print_link( '', '', false )
		);
	}

	/**
	 * Style 2: icon only.
	 */
	public function test_style_two_is_icon_only() {
		$this->go_to( get_permalink( self::$post_id ) );
		the_post();

		update_option( WP_Print_Options::OPTION, array_merge( WP_Print_Options::get_defaults(), array( 'print_style' => 2 ) ) );

		$output = print_link( '', '', false );

		$this->assertStringContainsString( 'WP-PrintIcon', $output );
		$this->assertSame( 1, substr_count( $output, '<a href=' ) );
		$this->assertStringNotContainsString( '&nbsp;', $output );
	}

	/**
	 * Style 3: text only.
	 */
	public function test_style_three_is_text_only() {
		$this->go_to( get_permalink( self::$post_id ) );
		the_post();

		update_option( WP_Print_Options::OPTION, array_merge( WP_Print_Options::get_defaults(), array( 'print_style' => 3 ) ) );

		$url = esc_url( get_permalink( self::$post_id ) . 'print/' );

		$this->assertSame(
			'<a href="' . $url . '" title="Print This Post" rel="nofollow">Print This Post</a>',
			print_link( '', '', false )
		);
	}

	/**
	 * Style 4 substitutes all three placeholders.
	 */
	public function test_style_four_substitutes_every_placeholder() {
		$this->go_to( get_permalink( self::$post_id ) );
		the_post();

		update_option(
			WP_Print_Options::OPTION,
			array_merge(
				WP_Print_Options::get_defaults(),
				array(
					'print_style' => 4,
					'print_html'  => '[%PRINT_URL%][%PRINT_TEXT%][%PRINT_ICON%]',
				)
			)
		);

		$this->assertSame(
			'[' . esc_url( get_permalink( self::$post_id ) . 'print/' ) . '][Print This Post][' . $this->icon() . ']',
			print_link( '', '', false )
		);
	}

	/**
	 * A page uses the page text; a post uses the post text.
	 */
	public function test_page_uses_the_page_text() {
		update_option( WP_Print_Options::OPTION, array_merge( WP_Print_Options::get_defaults(), array( 'print_style' => 3 ) ) );

		$this->go_to( get_permalink( self::$page_id ) );
		the_post();

		$this->assertStringContainsString( '>Print This Page</a>', print_link( '', '', false ) );
	}

	/**
	 * Explicit arguments win over the stored options.
	 */
	public function test_explicit_text_overrides_the_option() {
		update_option( WP_Print_Options::OPTION, array_merge( WP_Print_Options::get_defaults(), array( 'print_style' => 3 ) ) );

		$this->go_to( get_permalink( self::$post_id ) );
		the_post();
		$this->assertStringContainsString( '>Mine</a>', print_link( 'Mine', '', false ) );

		$this->go_to( get_permalink( self::$page_id ) );
		the_post();
		$this->assertStringContainsString( '>Theirs</a>', print_link( '', 'Theirs', false ) );
	}

	/**
	 * Echo mode appends exactly one newline, and returns nothing.
	 */
	public function test_echo_mode_appends_one_newline() {
		update_option( WP_Print_Options::OPTION, array_merge( WP_Print_Options::get_defaults(), array( 'print_style' => 3 ) ) );

		$this->go_to( get_permalink( self::$post_id ) );
		the_post();

		$expected = print_link( '', '', false );

		ob_start();
		$returned = print_link( '', '', true );
		$echoed   = ob_get_clean();

		$this->assertNull( $returned );
		$this->assertSame( $expected . "\n", $echoed );
	}

	/**
	 * The glyph is an inline SVG that takes the surrounding text colour.
	 *
	 * Inheriting the colour is the whole point of dropping the two GIFs: an icon
	 * that matches whatever the theme paints its links, at whatever size the
	 * text is, on whatever pixel density. A GIF could do none of the three.
	 */
	public function test_the_icon_is_an_inline_svg_that_inherits_its_colour() {
		$icon = WP_Print_Link::icon();

		$this->assertStringStartsWith( '<svg', $icon );
		$this->assertStringContainsString( 'fill="currentColor"', $icon );
		$this->assertStringContainsString( 'aria-hidden="true"', $icon );
		$this->assertStringNotContainsString( '.gif', $icon, 'The glyph must not reference a raster file.' );
	}

	/**
	 * Icon-only leaves the link with no visible text, so it needs a name of its
	 * own or a screen reader announces the URL.
	 */
	public function test_the_icon_only_style_carries_an_accessible_name() {
		$this->go_to( get_permalink( self::$post_id ) );
		the_post();

		update_option( WP_Print_Options::OPTION, array_merge( WP_Print_Options::get_defaults(), array( 'print_style' => 2 ) ) );

		$this->assertStringContainsString( 'aria-label="Print This Post"', print_link( '', '', false ) );
	}

	/**
	 * Echoing and returning produce the same markup, in every style.
	 *
	 * The echoing half filters on the way out with a closed tag list, and that is
	 * the only place the plugin prints markup it has built itself. Should the
	 * list ever stop covering what the link contains -- the glyph is svg, and
	 * wp_kses_post() has never allowed svg -- the icon styles would quietly lose
	 * their icon, and only in the half of the API that prints.
	 *
	 * @dataProvider data_styles
	 *
	 * @param int $style Print style.
	 */
	public function test_the_echoed_link_matches_the_returned_one( $style ) {
		$this->go_to( get_permalink( self::$post_id ) );
		the_post();

		update_option(
			WP_Print_Options::OPTION,
			array_merge(
				WP_Print_Options::get_defaults(),
				array(
					'print_style' => $style,
					'print_html'  => '<a href="%PRINT_URL%" rel="nofollow">%PRINT_ICON% %PRINT_TEXT%</a>',
				)
			)
		);

		$returned = print_link( '', '', false );

		ob_start();
		print_link();
		$echoed = ob_get_clean();

		$this->assertSame( $returned . "\n", $echoed, "Style {$style} does not survive the output filter intact." );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_styles() {
		return array(
			'icon and text' => array( WP_Print_Link::STYLE_ICON_TEXT ),
			'icon only'     => array( WP_Print_Link::STYLE_ICON ),
			'text only'     => array( WP_Print_Link::STYLE_TEXT ),
			'custom'        => array( WP_Print_Link::STYLE_CUSTOM ),
		);
	}

	/**
	 * Without pretty permalinks the endpoint becomes a query argument.
	 */
	public function test_url_falls_back_to_a_query_argument() {
		$this->set_permalink_structure( '' );

		$this->go_to( get_permalink( self::$post_id ) );
		the_post();

		$this->assertStringContainsString( 'print=1', WP_Print_Link::url() );
	}

	/**
	 * With pretty permalinks the URL is the permalink plus the endpoint, and it is
	 * always slashed exactly once however the permalink ends.
	 */
	public function test_url_appends_the_endpoint_once() {
		$this->go_to( get_permalink( self::$post_id ) );
		the_post();

		$url = WP_Print_Link::url();

		$this->assertStringEndsWith( '/print/', $url );
		$this->assertStringNotContainsString( '//print/', str_replace( array( 'http://', 'https://' ), '', $url ) );
	}

	/**
	 * On a static front page the permalink is the site root, which the endpoint
	 * cannot usefully be appended to, so the page's own URL is used instead.
	 */
	public function test_a_static_front_page_uses_its_own_url() {
		$front_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Front',
				'post_name'  => 'front',
			)
		);

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front_id );

		$this->go_to( home_url( '/' ) );
		the_post();

		$url = WP_Print_Link::url();

		update_option( 'show_on_front', 'posts' );
		update_option( 'page_on_front', 0 );

		$this->assertStringContainsString( 'front', $url );
		$this->assertStringEndsWith( '/print/', $url );
	}

	/**
	 * A row missing the keys a later version added still renders a usable link,
	 * rather than an empty one, and raises no diagnostic.
	 */
	public function test_link_survives_a_row_missing_keys() {
		update_option( WP_Print_Options::OPTION, array( 'print_style' => 1 ) );

		$this->go_to( get_permalink( self::$post_id ) );
		the_post();

		$output = print_link( '', '', false );

		$this->assertStringContainsString( 'Print This Post', $output );
		$this->assertStringContainsString( 'WP-PrintIcon', $output );
	}

	/**
	 * In a feed the shortcode explains itself instead of emitting a link, because
	 * a feed reader is not where printing happens.
	 */
	public function test_shortcode_explains_itself_in_a_feed() {
		$this->go_to( get_permalink( self::$post_id ) );
		the_post();

		$this->assertStringContainsString( 'WP-PrintIcon', do_shortcode( '[print_link]' ) );

		global $wp_query;
		$wp_query->is_feed = true;

		$this->assertStringContainsString( 'please visit this post', do_shortcode( '[print_link]' ) );
	}
}
