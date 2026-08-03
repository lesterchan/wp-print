<?php
/**
 * Print link markup: the template, its placeholders, and the URL it points at.
 *
 * The markup is asserted byte-for-byte because it is the plugin's most visible
 * output and themes style it by class.
 *
 * There is one template now where there were three fixed markups and a fourth
 * custom one. The three are not gone so much as written down: the migration
 * turns each of them into the template it always was underneath, so the shapes
 * they rendered are asserted here as templates.
 *
 * @package WP-Print
 */

/**
 * Tests for the print link.
 *
 * @covers WP_Print_Link
 * @covers ::print_link
 */
class WP_Print_Link_Test extends WP_Print_TestCase {

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
		// WP_Print_Content_Test::set_up().
		add_shortcode( 'print_link', array( 'WP_Print_Link', 'shortcode' ) );
	}

	/**
	 * The printer glyph, as a reader receives it.
	 *
	 * Not WP_Print_Link::icon() as the plugin builds it. The stored template meets
	 * wp_kses() inside render(), so every route out of the plugin gets the glyph
	 * through the allow-list -- and kses is a normaliser rather than a
	 * pass-through: among other things it lowercases every attribute name, so the
	 * glyph's viewBox is printed as viewbox. An HTML parser maps the lowercase
	 * spelling back for SVG attributes and nothing is lost by it. The byte-for-byte
	 * assertions below are about the template substitution rather than about the
	 * glyph, which test_the_icon_is_an_inline_svg_that_inherits_its_colour covers
	 * on its own.
	 *
	 * @return string
	 */
	private function icon() {
		return wp_kses( WP_Print_Link::icon(), WP_Print_Link::allowed_html() );
	}

	/**
	 * The shipped template: one link carrying the glyph and the words.
	 */
	public function test_the_shipped_template_renders_the_icon_and_the_words() {
		$this->go_to( get_permalink( self::$post_id ) );
		the_post();

		$url = esc_url( get_permalink( self::$post_id ) . 'print/' );

		$this->assertSame(
			'<a href="' . $url . '" rel="nofollow" title="Print This Post">' . $this->icon() . ' Print This Post</a>',
			print_link( '', '', false )
		);
	}

	/**
	 * Every placeholder the plugin knows is substituted, wherever it appears.
	 */
	public function test_the_template_substitutes_every_placeholder() {
		$this->go_to( get_permalink( self::$post_id ) );
		the_post();

		update_option(
			WP_Print_Options::OPTION,
			array_merge(
				WP_Print_Options::get_defaults(),
				array( 'print_html' => '[%PRINT_URL%][%POST_TYPE%][%PRINT_ICON%]' )
			)
		);

		$this->assertSame(
			'[' . esc_url( get_permalink( self::$post_id ) . 'print/' ) . '][Post][' . $this->icon() . ']',
			print_link( '', '', false )
		);
	}

	/**
	 * A placeholder the plugin does not know is left in the markup as written.
	 *
	 * Deliberate, and load-bearing for the upgrade: %PRINT_TEXT% is retired, and a
	 * template that still carries it is left saying so on the page. Blanking it
	 * instead would leave a link with no words and nothing to explain why, which is
	 * the failure nobody reports because nobody notices.
	 */
	public function test_an_unrecognised_placeholder_is_left_as_written() {
		$this->go_to( get_permalink( self::$post_id ) );
		the_post();

		update_option(
			WP_Print_Options::OPTION,
			array_merge(
				WP_Print_Options::get_defaults(),
				array( 'print_html' => '<a href="%PRINT_URL%">%PRINT_TEXT% %WHATEVER%</a>' )
			)
		);

		$output = print_link( '', '', false );

		$this->assertStringContainsString( '%PRINT_TEXT%', $output );
		$this->assertStringContainsString( '%WHATEVER%', $output );
	}

	/**
	 * %POST_TYPE% is the post type's own singular label, which is what lets one
	 * template say the right thing on a post and on a page.
	 */
	public function test_post_type_resolves_to_the_singular_label() {
		update_option(
			WP_Print_Options::OPTION,
			array_merge(
				WP_Print_Options::get_defaults(),
				array( 'print_html' => '<a href="%PRINT_URL%">Print This %POST_TYPE%</a>' )
			)
		);

		$this->go_to( get_permalink( self::$post_id ) );
		the_post();
		$this->assertStringContainsString( '>Print This Post</a>', print_link( '', '', false ) );

		$this->go_to( get_permalink( self::$page_id ) );
		the_post();
		$this->assertStringContainsString( '>Print This Page</a>', print_link( '', '', false ) );
	}

	/**
	 * And on a custom post type it says what that type calls itself, which neither
	 * of the two link labels it replaced could ever have said.
	 */
	public function test_post_type_resolves_to_a_custom_types_label() {
		register_post_type(
			'print_test_recipe',
			array(
				'public'   => true,
				'labels'   => array(
					'name'          => 'Recipes',
					'singular_name' => 'Recipe',
				),
				'rewrite'  => array( 'slug' => 'recipes' ),
				'supports' => array( 'title', 'editor' ),
			)
		);

		$this->set_permalink_structure( '/%postname%/' );

		$recipe_id = self::factory()->post->create(
			array(
				'post_type'  => 'print_test_recipe',
				'post_title' => 'Link Recipe',
				'post_name'  => 'link-recipe',
			)
		);

		update_option(
			WP_Print_Options::OPTION,
			array_merge(
				WP_Print_Options::get_defaults(),
				array( 'print_html' => '<a href="%PRINT_URL%">Print This %POST_TYPE%</a>' )
			)
		);

		$this->go_to( get_permalink( $recipe_id ) );
		the_post();

		$this->assertStringContainsString( '>Print This Recipe</a>', print_link( '', '', false ) );

		unregister_post_type( 'print_test_recipe' );
	}

	/**
	 * Outside the loop there is no post to ask, and the wording the plugin has
	 * shipped since its first release is what it falls back to.
	 */
	public function test_post_type_falls_back_outside_the_loop() {
		$this->assertSame( 'Post', WP_Print_Link::post_type_label() );
	}

	/**
	 * The template tag's first two arguments no longer do anything.
	 *
	 * They overrode the two link labels, and there are no link labels. They stay in
	 * the signature because every theme that has ever called this tag passes them,
	 * and dropping them would turn a settings change into a fatal error on somebody
	 * else's site.
	 */
	public function test_the_retired_text_arguments_are_ignored() {
		$this->go_to( get_permalink( self::$post_id ) );
		the_post();

		$this->assertSame(
			print_link( '', '', false ),
			print_link( 'Mine', 'Theirs', false )
		);
	}

	/**
	 * An icon-only template has no visible text, so it needs a name of its own or a
	 * screen reader announces the URL. This is the shape the migration writes for a
	 * site that was on the icon-only style.
	 */
	public function test_an_icon_only_template_carries_an_accessible_name() {
		$this->go_to( get_permalink( self::$post_id ) );
		the_post();

		update_option(
			WP_Print_Options::OPTION,
			array_merge(
				WP_Print_Options::get_defaults(),
				array(
					'print_html' => WP_Print_Options::link_template(
						WP_Print_Options::LEGACY_STYLE_ICON,
						'Print This %POST_TYPE%'
					),
				)
			)
		);

		$output = print_link( '', '', false );

		$this->assertStringContainsString( 'aria-label="Print This Post"', $output );
		$this->assertStringContainsString( 'WP-PrintIcon', $output );
		$this->assertSame( 1, substr_count( $output, '<a href=' ) );
	}

	/**
	 * Echo mode appends exactly one newline, and returns nothing.
	 */
	public function test_echo_mode_appends_one_newline() {
		// A template with no glyph in it, so the comparison below can be made byte
		// for byte. The echoing half filters through wp_kses(), which lowercases
		// every attribute name -- so the glyph's viewBox comes back as viewbox and
		// two identical links differ by one character. An HTML parser maps the
		// lowercase spelling back for SVG attributes and nothing is lost by it, but
		// it is not what this test is here to measure; the test above it compares
		// element by element for exactly that reason.
		update_option(
			WP_Print_Options::OPTION,
			array_merge(
				WP_Print_Options::get_defaults(),
				array( 'print_html' => '<a href="%PRINT_URL%" rel="nofollow">Print This %POST_TYPE%</a>' )
			)
		);

		$this->go_to( get_permalink( self::$post_id ) );
		the_post();

		$expected = print_link( '', '', false );

		ob_start();
		$returned = print_link( '', '', true );
		$echoed   = ob_get_clean();

		$this->assertNull( $returned, 'With echo on, nothing is returned; the markup went to the output buffer.' );
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
	 * Every route out of render() produces the same link, whatever the template
	 * holds.
	 *
	 * There are three: the tag printing, the tag returning, and the shortcode.
	 * They did not always agree -- the template met the allow-list on the way out
	 * of the tag and nowhere else, so the shortcode, which is the route every
	 * documented install uses, emitted the stored template as markup. The
	 * filtering moved into render(), and this is the pin that all three stay one
	 * string.
	 *
	 * The second half is the other risk that move carries. The glyph is svg and
	 * wp_kses_post() has never allowed svg, which is why the list is a closed one
	 * of the plugin's own; a list that stopped covering svg or path would now
	 * drop the icon from every route at once rather than from one of them.
	 *
	 * @dataProvider data_templates
	 *
	 * @param string $template The stored link template.
	 */
	public function test_every_route_out_of_render_produces_the_same_link( $template ) {
		$this->go_to( get_permalink( self::$post_id ) );
		the_post();

		update_option(
			WP_Print_Options::OPTION,
			array_merge( WP_Print_Options::get_defaults(), array( 'print_html' => $template ) )
		);

		$returned = print_link( '', '', false );

		ob_start();
		print_link();
		$echoed = ob_get_clean();

		$this->assertSame( $returned . "\n", $echoed, 'Printing the link is not returning it plus a newline.' );
		$this->assertSame(
			$returned,
			do_shortcode( '[print_link]' ),
			'The shortcode and the template tag do not produce the same link.'
		);

		if ( false !== strpos( $template, '%PRINT_ICON%' ) ) {
			$this->assertStringContainsString( 'WP-PrintIcon', $returned, 'The allow-list has dropped the glyph.' );
			$this->assertSame( 3, substr_count( $returned, '<path' ), 'The allow-list has dropped part of the glyph.' );
		}
	}

	/**
	 * Every element in a fragment, as tag name plus attributes.
	 *
	 * Attribute names arrive lowercased because that is how DOMDocument's HTML
	 * parser reports them, which is the one difference wp_kses() introduces and
	 * the only one being tolerated. Values are compared as they are. Names and
	 * values are asked of XPath rather than read off the node as ->nodeValue,
	 * because DOM spells its properties in camel case and the coding standard
	 * requires snake_case for every property read.
	 *
	 * @param string $html Markup fragment.
	 * @return array List of arrays keyed tag and attributes.
	 */
	private function elements( $html ) {
		$doc = new DOMDocument();
		$use = libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8" ?><div id="print-link-root">' . $html . '</div>' );
		libxml_clear_errors();
		libxml_use_internal_errors( $use );

		$xpath = new DOMXPath( $doc );
		$found = array();

		foreach ( $xpath->query( '//*[@id="print-link-root"]//*' ) as $element ) {
			$attributes = array();

			foreach ( $xpath->query( '@*', $element ) as $attribute ) {
				$name = (string) $xpath->evaluate( 'name(.)', $attribute );

				$attributes[ $name ] = (string) $xpath->evaluate( 'string(.)', $attribute );
			}

			ksort( $attributes );

			$found[] = array(
				'tag'        => (string) $xpath->evaluate( 'name(.)', $element ),
				'attributes' => $attributes,
			);
		}

		return $found;
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_templates() {
		return array(
			'the shipped one' => array( '<a href="%PRINT_URL%" rel="nofollow" title="Print This %POST_TYPE%">%PRINT_ICON% Print This %POST_TYPE%</a>' ),
			'icon only'       => array( '<a href="%PRINT_URL%" rel="nofollow" title="Print" aria-label="Print">%PRINT_ICON%</a>' ),
			'text only'       => array( '<a href="%PRINT_URL%" rel="nofollow" title="Print">Print This %POST_TYPE%</a>' ),
			'a site\'s own'   => array( '<span class="mine"><a href="%PRINT_URL%">%PRINT_ICON%</a> <em>Print</em></span>' ),
		);
	}

	/**
	 * A hostile stored template is text on the page, by every route out of render().
	 *
	 * The link template is the one thing WP-Print reads out of its own row and
	 * puts on a public page as markup, so it is the plugin's stored-XSS surface
	 * and §7.2.4 asks for exactly this. Both routes are asserted because for most
	 * of 3.0.0's life they disagreed: the template tag filtered on the way out and
	 * the shortcode did not, so one stored value was inert through the tag a theme
	 * calls and live through the shortcode the readme documents.
	 *
	 * Written straight into the row rather than through the settings screen.
	 * Sanitising on the way in is the assumption under test, not a step to
	 * reproduce: this is the row a pre-3.0.0 release, a WP-CLI one-liner or a
	 * compromised install has already left behind.
	 *
	 * Both halves are asserted. Nothing that runs may survive, and the wording
	 * must -- escaping that swallowed the payload whole would satisfy the first
	 * half while losing the site the words it wrote, and a print link whose text
	 * silently vanished is its own bug.
	 *
	 * @dataProvider data_hostile_templates
	 *
	 * @param string $template The stored link template.
	 * @param string $survives Text the reader must still be shown.
	 */
	public function test_a_hostile_template_is_inert_through_the_tag_and_the_shortcode( $template, $survives ) {
		$this->go_to( get_permalink( self::$post_id ) );
		the_post();

		update_option(
			WP_Print_Options::OPTION,
			array_merge( WP_Print_Options::get_defaults(), array( 'print_html' => $template ) )
		);

		$routes = array(
			'the template tag' => print_link( '', '', false ),
			'the shortcode'    => do_shortcode( '[print_link]' ),
		);

		$this->assertSame(
			$routes['the template tag'],
			$routes['the shortcode'],
			'The shortcode and the template tag do not escape the same stored value alike.'
		);

		foreach ( $routes as $route => $output ) {
			$this->assertStringNotContainsString( '<script', $output, "A script element survived $route." );
			$this->assertStringNotContainsString( 'javascript:', $output, "A javascript: URL survived $route." );

			/*
			 * The elements are parsed rather than searched for as text, because a
			 * payload that is doing its job appears in the output twice over: once
			 * as the inert words the reader sees, and once -- if the escaping
			 * failed -- as an attribute. A substring search cannot tell those
			 * apart, and would call the first one a failure.
			 */
			foreach ( $this->elements( $output ) as $element ) {
				$this->assertNotSame( 'script', $element['tag'], "A script element survived $route." );

				foreach ( array_keys( $element['attributes'] ) as $attribute ) {
					$this->assertNotSame(
						'on',
						substr( $attribute, 0, 2 ),
						"The $attribute handler survived $route."
					);
				}
			}

			$this->assertStringContainsString(
				$survives,
				wp_strip_all_tags( $output ),
				"The escaping ate the link wording in $route."
			);
		}
	}

	/**
	 * Data provider.
	 *
	 * The three canonical payloads of §7.2.4 -- a script element, an error-firing
	 * image and an attribute breakout -- plus the breakout again in the place a
	 * template most plausibly puts it, inside an attribute of its own.
	 *
	 * @return array
	 */
	public function data_hostile_templates() {
		return array(
			'a script element in the wording'  => array(
				'<a href="%PRINT_URL%" rel="nofollow">Print <script>window.__pwned = 1;</script></a>',
				'window.__pwned = 1;',
			),
			'an error-firing image'            => array(
				'<a href="%PRINT_URL%" rel="nofollow">Print <img src=x onerror="window.__pwned = 1"></a>',
				'Print',
			),
			'a breakout in the wording'        => array(
				'<a href="%PRINT_URL%" rel="nofollow">Print " onmouseover="window.__pwned = 1</a>',
				'Print " onmouseover="window.__pwned = 1',
			),
			'a breakout inside an attribute'   => array(
				'<a href="%PRINT_URL%" rel="nofollow" title="Print " onmouseover="window.__pwned = 1">Print</a>',
				'Print',
			),
			'a javascript URL in the template' => array(
				'<a href="javascript:window.__pwned = 1" rel="nofollow">Print</a>',
				'Print',
			),
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
		update_option( WP_Print_Options::OPTION, array( 'links' => 1 ) );

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
