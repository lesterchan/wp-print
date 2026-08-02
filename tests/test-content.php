<?php
/**
 * Printable content: footnote numbering, URL resolution, media toggles, shortcodes.
 *
 * @package WP-Print
 */

/**
 * Tests for the printable content pipeline.
 *
 * @covers WP_Print_Content
 * @covers ::print_content
 * @covers ::print_comments_content
 */
class WP_Print_Content_Test extends WP_Print_TestCase {

	/**
	 * Known options and a clean footnote counter for every test.
	 */
	public function set_up() {
		parent::set_up();

		// Fixtures are written as a user who may post unfiltered HTML. With no user
		// set, wp_insert_post() runs the content through KSES, which rewrites an
		// href carrying a second scheme in its query string - so the fixture would
		// never reach the code under test intact.
		wp_set_current_user( $this->create_admin() );

		$this->set_permalink_structure( '/%postname%/' );

		update_option(
			WP_Print_Options::OPTION,
			array_merge(
				WP_Print_Options::get_defaults(),
				array(
					'links'  => 1,
					'images' => 1,
					'videos' => 1,
				)
			)
		);

		WP_Print_Content::reset();

		// print_content() swaps [donotprint] and [print_link] for callbacks that
		// render nothing, and never swaps them back - on a real request the print
		// view exits immediately afterwards, so it never needs to. Inside a suite
		// that leaks into the next test, so restore them here.
		add_shortcode( 'donotprint', array( 'WP_Print_Link', 'donotprint_shortcode' ) );
		add_shortcode( 'print_link', array( 'WP_Print_Link', 'shortcode' ) );
	}

	/**
	 * Render a post's content through the plugin.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	private function render( $content ) {
		$post_id = self::factory()->post->create( array( 'post_content' => $content ) );

		$this->go_to( get_permalink( $post_id ) );
		the_post();

		WP_Print_Content::reset();

		return print_content( false );
	}

	/**
	 * Each distinct link gets its own number, in the order it appears.
	 */
	public function test_links_are_numbered_in_order() {
		$out = $this->render(
			'<a href="https://example.com/one">One</a> <a href="https://example.com/two">Two</a>'
		);

		$this->assertStringContainsString( 'One</a> <sup>[1]</sup>', $out );
		$this->assertStringContainsString( 'Two</a> <sup>[2]</sup>', $out );
	}

	/**
	 * A repeated URL reuses its first number and is listed only once, so the
	 * printed list is a list of URLs rather than of links.
	 */
	public function test_a_repeated_url_reuses_its_number() {
		$out = $this->render(
			'<a href="https://example.com/one">First</a> <a href="https://example.com/one">Again</a>'
		);

		$this->assertStringContainsString( 'First</a> <sup>[1]</sup>', $out );
		$this->assertStringContainsString( 'Again</a> <sup>[1]</sup>', $out );
		$this->assertSame( 1, substr_count( WP_Print_Content::links_text(), 'https://example.com/one<' ) );
	}

	/**
	 * A site-relative link is expanded, because a printed URL has to stand alone.
	 */
	public function test_a_relative_url_is_expanded() {
		$out = $this->render( '<a href="/relative/path">Relative</a>' );

		$this->assertStringContainsString( 'href="' . get_option( 'home' ) . '/relative/path"', $out );
	}

	/**
	 * A protocol-relative link gets a scheme.
	 */
	public function test_a_protocol_relative_url_gets_a_scheme() {
		$out = $this->render( '<a href="//example.com/x">Proto</a>' );

		$this->assertStringContainsString( 'href="http://example.com/x"', $out );
	}

	/**
	 * An absolute URL is left alone, whatever its scheme.
	 *
	 * @dataProvider data_absolute_urls
	 *
	 * @param string $url URL.
	 */
	public function test_an_absolute_url_is_left_alone( $url ) {
		$out = $this->render( '<a href="' . $url . '">Link</a>' );

		$this->assertStringContainsString( 'href="' . esc_url( $url ) . '"', $out );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_absolute_urls() {
		return array(
			'https'  => array( 'https://example.com/x' ),
			'http'   => array( 'http://example.com/x' ),
			'mailto' => array( 'mailto:someone@example.com' ),
		);
	}

	/**
	 * A relative URL that merely contains a scheme in its query string is still
	 * expanded - the scheme test must not be fooled by it.
	 */
	public function test_a_scheme_inside_a_query_string_is_not_a_scheme() {
		$this->render( '<a href="/go?to=https://example.com">Go</a>' );

		// Asserted against the printed URL list rather than the href: the list is
		// escaped with esc_html(), while esc_url() applies protocol filtering that
		// rewrites a URL carrying a second scheme and would obscure what is being
		// tested here - that the path was expanded rather than treated as absolute.
		$this->assertStringContainsString(
			esc_html( get_option( 'home' ) . '/go?to=https://example.com' ),
			WP_Print_Content::links_text()
		);
	}

	/**
	 * An in-page anchor is not expanded, or it would stop being an anchor.
	 */
	public function test_an_anchor_is_not_expanded() {
		$out = $this->render( '<a href="#section">Section</a>' );

		$this->assertStringContainsString( 'href="#section"', $out );
	}

	/**
	 * The first link still gets a marker when it sits at offset 0 of the filtered
	 * content, which is what happens on a theme that turns wpautop off. Testing
	 * strpos() for truthiness rather than against false silently skipped it.
	 */
	public function test_a_link_at_offset_zero_is_still_numbered() {
		remove_filter( 'the_content', 'wpautop' );
		remove_filter( 'the_content', 'shortcode_unautop' );

		$out = $this->render( '<a href="https://example.com/zero">Zero</a> tail' );

		add_filter( 'the_content', 'wpautop' );
		add_filter( 'the_content', 'shortcode_unautop' );

		$this->assertSame( 0, strpos( trim( $out ), '<a' ), 'Fixture did not reproduce the offset-0 case: ' . $out );
		$this->assertStringContainsString( '<sup>[1]</sup>', $out );
	}

	/**
	 * A link whose text is an image is labelled rather than listed with markup.
	 */
	public function test_an_image_link_is_labelled() {
		$this->render( '<a href="https://example.com/img"><img src="https://example.com/p.png" /></a>' );

		$this->assertStringContainsString( 'Image: <strong>', WP_Print_Content::links_text() );
	}

	/**
	 * A URL taken from post markup is escaped when written back out.
	 */
	public function test_a_javascript_url_does_not_survive() {
		$out = $this->render( '<a href="javascript:alert(1)">X</a>' );

		$this->assertStringNotContainsString( 'href="javascript:', $out );
	}

	/**
	 * A URL cannot break out of the attribute it is written into.
	 */
	public function test_a_url_cannot_break_out_of_the_attribute() {
		$out = $this->render( '<a href="https://example.com/x" onmouseover="alert(1)">X</a>' );

		// The rewritten anchor is rebuilt from the URL and the link text alone, so
		// the original attributes - including the handler - are dropped entirely.
		$this->assertStringNotContainsString( 'onmouseover', $out );
	}

	/**
	 * Images are stripped when the option is off.
	 */
	public function test_images_are_stripped_when_switched_off() {
		update_option( WP_Print_Options::OPTION, array_merge( WP_Print_Options::get_defaults(), array( 'images' => 0 ) ) );

		$this->assertStringNotContainsString( '<img', $this->render( '<img src="https://example.com/p.png" alt="x" />' ) );
	}

	/**
	 * Embedded video is stripped when the option is off - iframe, object and embed.
	 *
	 * @dataProvider data_video_markup
	 *
	 * @param string $markup Video markup.
	 * @param string $tag    Tag that must be gone.
	 */
	public function test_video_is_stripped_when_switched_off( $markup, $tag ) {
		update_option( WP_Print_Options::OPTION, array_merge( WP_Print_Options::get_defaults(), array( 'videos' => 0 ) ) );

		$this->assertStringNotContainsString( $tag, $this->render( $markup ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_video_markup() {
		return array(
			'iframe' => array( '<iframe src="https://example.com/e"></iframe>', '<iframe' ),
			'object' => array( '<object data="https://example.com/o"></object>', '<object' ),
			'embed'  => array( '<embed src="https://example.com/e"></embed>', '<embed' ),
		);
	}

	/**
	 * With links off, nothing is numbered and nothing is collected.
	 */
	public function test_links_are_not_numbered_when_switched_off() {
		update_option( WP_Print_Options::OPTION, array_merge( WP_Print_Options::get_defaults(), array( 'links' => 0 ) ) );

		$out = $this->render( '<a href="https://example.com/one">One</a>' );

		$this->assertStringNotContainsString( '<sup>[', $out );
		$this->assertSame( '', WP_Print_Content::links_text() );
	}

	/**
	 * [donotprint] content is dropped in the print view but kept everywhere else.
	 */
	public function test_donotprint_is_dropped_in_the_print_view_only() {
		$this->assertSame( 'kept', do_shortcode( '[donotprint]kept[/donotprint]' ) );

		$this->assertStringNotContainsString( 'SECRET', $this->render( 'before [donotprint]SECRET[/donotprint] after' ) );
	}

	/**
	 * An embedded [print_link] renders nothing in the print view - a link to the
	 * page being printed is noise on paper.
	 */
	public function test_embedded_print_link_renders_nothing() {
		$this->assertStringNotContainsString( 'WP-PrintIcon', $this->render( 'body [print_link]' ) );
	}

	/**
	 * A post split with <!--nextpage--> prints every page, not just the first.
	 * Printing page one of five and calling it the article is the failure this
	 * guards against.
	 */
	public function test_a_multipage_post_prints_every_page() {
		$out = $this->render( 'Page one.<!--nextpage-->Page two.<!--nextpage-->Page three.' );

		$this->assertStringContainsString( 'Page one.', $out );
		$this->assertStringContainsString( 'Page two.', $out );
		$this->assertStringContainsString( 'Page three.', $out );
	}

	/**
	 * Links keep numbering across the page break rather than restarting.
	 */
	public function test_a_multipage_post_numbers_links_continuously() {
		$out = $this->render(
			'<a href="https://example.com/one">One</a><!--nextpage--><a href="https://example.com/two">Two</a>'
		);

		$this->assertStringContainsString( 'One</a> <sup>[1]</sup>', $out );
		$this->assertStringContainsString( 'Two</a> <sup>[2]</sup>', $out );
	}

	/**
	 * Called outside the loop there is no $pages global to read, which used to be
	 * an unguarded array index. It has to degrade to empty rather than warn.
	 */
	public function test_content_outside_the_loop_is_empty_not_fatal() {
		$this->go_to( home_url( '/' ) );

		unset( $GLOBALS['pages'], $GLOBALS['multipage'], $GLOBALS['numpages'] );

		WP_Print_Content::reset();

		$this->assertSame( '', print_content( false ) );
	}

	/**
	 * A shortcode nested inside [donotprint] is dropped with its wrapper on the
	 * print view, rather than leaking its output.
	 */
	public function test_a_shortcode_nested_in_donotprint_is_dropped() {
		add_shortcode(
			'harness_inner',
			static function () {
				return 'INNERTEXT';
			}
		);

		$this->assertStringContainsString( 'INNERTEXT', do_shortcode( '[donotprint][harness_inner][/donotprint]' ) );
		$this->assertStringNotContainsString( 'INNERTEXT', $this->render( 'a [donotprint][harness_inner][/donotprint] b' ) );

		remove_shortcode( 'harness_inner' );
	}

	/**
	 * A password-protected post shows the form, never the body.
	 */
	public function test_a_protected_post_withholds_its_body() {
		$post_id = self::factory()->post->create(
			array(
				'post_content'  => 'CLASSIFIED',
				'post_password' => 'letmein',
			)
		);

		$this->go_to( get_permalink( $post_id ) );
		the_post();

		$out = print_content( false );

		$this->assertStringNotContainsString( 'CLASSIFIED', $out );
		$this->assertStringContainsString( 'post_password', $out );
	}

	/**
	 * The printed password form still has somewhere to type the password.
	 *
	 * The form is the one thing in the printed document that survives the trip to
	 * the page as a form. It is built by core, returned by post_content() and then
	 * filtered like everything else -- and the list that filtering is built on,
	 * wp_kses_allowed_html( 'post' ), has never allowed `form` or `input`. So the
	 * sentence and the Password label arrived and the field they point at did not,
	 * and the reader was told to enter a password into nothing at all.
	 *
	 * Asserted against the echoed output rather than the returned one, because the
	 * filtering is what print_content() does on the way to the page and the return
	 * value never sees it.
	 */
	public function test_a_protected_post_prints_a_field_to_type_the_password_into() {
		$post_id = self::factory()->post->create(
			array(
				'post_content'  => 'CLASSIFIED',
				'post_password' => 'letmein',
			)
		);

		$this->go_to( get_permalink( $post_id ) );
		the_post();

		ob_start();
		print_content();
		$html = ob_get_clean();

		$this->assertStringNotContainsString( 'CLASSIFIED', $html );
		$this->assertStringContainsString( '<form', $html );
		$this->assertStringContainsString( 'name="post_password"', $html );
		$this->assertStringContainsString( 'type="password"', $html );

		// The label is only worth having if it still points at something.
		preg_match( '#<label for="([^"]+)"#', $html, $label );

		$this->assertNotEmpty( $label, 'The password form printed without its label.' );
		$this->assertStringContainsString( 'id="' . $label[1] . '"', $html );
	}

	/**
	 * A form stored in a post body is still printed as text.
	 *
	 * The widening above is scoped to the locked-post path, and this is the reason
	 * it has to be. Everything else the print view prints is somebody's stored
	 * content; a list that allowed a form everywhere would let one be stored in a
	 * post and printed as a working form on a page whose whole appeal is that it
	 * looks like plain paper.
	 */
	public function test_a_form_in_a_post_body_is_not_printed_as_a_form() {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => '<form action="https://example.com/collect" method="post">'
					. '<input type="password" name="post_password" /></form>Body text.',
			)
		);

		$this->go_to( get_permalink( $post_id ) );
		the_post();

		ob_start();
		print_content();
		$html = ob_get_clean();

		$this->assertStringNotContainsString( '<form', $html );
		$this->assertStringNotContainsString( '<input', $html );
		$this->assertStringNotContainsString( 'example.com/collect', $html );
		$this->assertStringContainsString( 'Body text.', $html );
	}

	/**
	 * Comment links continue the post's numbering rather than restarting, so a
	 * footnote number means one thing across the whole printed document.
	 */
	public function test_comment_links_continue_the_numbering() {
		$post_id    = self::factory()->post->create(
			array( 'post_content' => '<a href="https://example.com/one">One</a>' )
		);
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_content' => 'See <a href="https://example.com/two">two</a>.',
			)
		);

		$this->go_to( get_permalink( $post_id ) );
		the_post();

		WP_Print_Content::reset();
		print_content( false );

		$GLOBALS['comment'] = get_comment( $comment_id );
		$out                = print_comments_content( false );

		$this->assertStringContainsString( '<sup>[2]</sup>', $out );
		$this->assertStringNotContainsString( '<sup>[1]</sup>', $out );
		$this->assertStringContainsString( 'https://example.com/two', WP_Print_Content::links_text() );
	}

	/**
	 * Resetting clears the counter, so a second document starts at 1 rather than
	 * carrying the previous one's numbering.
	 */
	public function test_reset_clears_the_numbering() {
		$this->render( '<a href="https://example.com/one">One</a>' );
		$out = $this->render( '<a href="https://example.com/other">Other</a>' );

		$this->assertStringContainsString( '<sup>[1]</sup>', $out );
		$this->assertSame( 1, substr_count( WP_Print_Content::links_text(), '<p class="wp-print-url">' ) );
	}
}
