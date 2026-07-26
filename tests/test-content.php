<?php
/**
 * Printable content: footnote numbering, URL resolution, media toggles, shortcodes.
 *
 * @package WP-Print
 */

/**
 * Tests for the printable content pipeline.
 *
 * @covers Print_Content
 * @covers ::print_content
 * @covers ::print_comments_content
 */
class Test_Print_Content extends WP_UnitTestCase {

	/**
	 * Known options and a clean footnote counter for every test.
	 */
	public function set_up() {
		parent::set_up();

		// Fixtures are written as a user who may post unfiltered HTML. With no user
		// set, wp_insert_post() runs the content through KSES, which rewrites an
		// href carrying a second scheme in its query string - so the fixture would
		// never reach the code under test intact.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->set_permalink_structure( '/%postname%/' );

		update_option(
			Print_Options::OPTION_NAME,
			array_merge(
				Print_Options::get_defaults(),
				array(
					'links'  => 1,
					'images' => 1,
					'videos' => 1,
				)
			)
		);

		Print_Content::reset();

		// print_content() swaps [donotprint] and [print_link] for callbacks that
		// render nothing, and never swaps them back - on a real request the print
		// view exits immediately afterwards, so it never needs to. Inside a suite
		// that leaks into the next test, so restore them here.
		add_shortcode( 'donotprint', array( 'Print_Link', 'donotprint_shortcode' ) );
		add_shortcode( 'print_link', array( 'Print_Link', 'shortcode' ) );
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

		Print_Content::reset();

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
		$this->assertSame( 1, substr_count( Print_Content::links_text(), 'https://example.com/one<' ) );
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
			Print_Content::links_text()
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

		$this->assertStringContainsString( 'Image: <strong>', Print_Content::links_text() );
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
		update_option( Print_Options::OPTION_NAME, array_merge( Print_Options::get_defaults(), array( 'images' => 0 ) ) );

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
		update_option( Print_Options::OPTION_NAME, array_merge( Print_Options::get_defaults(), array( 'videos' => 0 ) ) );

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
		update_option( Print_Options::OPTION_NAME, array_merge( Print_Options::get_defaults(), array( 'links' => 0 ) ) );

		$out = $this->render( '<a href="https://example.com/one">One</a>' );

		$this->assertStringNotContainsString( '<sup>[', $out );
		$this->assertSame( '', Print_Content::links_text() );
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

		Print_Content::reset();
		print_content( false );

		$GLOBALS['comment'] = get_comment( $comment_id );
		$out                = print_comments_content( false );

		$this->assertStringContainsString( '<sup>[2]</sup>', $out );
		$this->assertStringNotContainsString( '<sup>[1]</sup>', $out );
		$this->assertStringContainsString( 'https://example.com/two', Print_Content::links_text() );
	}

	/**
	 * Resetting clears the counter, so a second document starts at 1 rather than
	 * carrying the previous one's numbering.
	 */
	public function test_reset_clears_the_numbering() {
		$this->render( '<a href="https://example.com/one">One</a>' );
		$out = $this->render( '<a href="https://example.com/other">Other</a>' );

		$this->assertStringContainsString( '<sup>[1]</sup>', $out );
		$this->assertSame( 1, substr_count( Print_Content::links_text(), '<p style=' ) );
	}
}
