<?php
/**
 * The rendered print document.
 *
 * Print_Template::render() ends in exit(), so it cannot be called from a test.
 * These tests set the query up the way it does and require the same template, so
 * the assertions are against the document a reader actually gets.
 *
 * @package WP-Print
 */

/**
 * Tests for the rendered print document.
 *
 * @covers Print_Template
 */
class Test_Print_Render extends WP_UnitTestCase {

	/**
	 * Pretty permalinks, a known option row, and content worth printing.
	 */
	public function set_up() {
		parent::set_up();

		// Fixtures are written as a user who may post unfiltered HTML; see the note
		// in Test_Print_Content::set_up().
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->set_permalink_structure( '/%postname%/' );

		update_option(
			Print_Options::OPTION_NAME,
			array_merge(
				Print_Options::get_defaults(),
				array(
					'comments'   => 1,
					'links'      => 1,
					'images'     => 1,
					'videos'     => 1,
					'disclaimer' => 'RENDERDISCLAIMER All rights reserved.',
				)
			)
		);
	}

	/**
	 * Render the print document for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function render_document( $post_id ) {
		$this->go_to( get_permalink( $post_id ) );

		Print_Content::reset();

		add_filter( 'wp_title', array( 'Print_Template', 'page_title' ) );
		add_filter( 'comments_template', array( 'Print_Template', 'comments_template' ) );

		// The template reads $print_options directly for the disclaimer, so it has
		// to be in scope for the require - exactly as Print_Template::render() does.
		$print_options = Print_Options::get();

		ob_start();
		require Print_Template::locate( 'print-posts.php' );
		$html = ob_get_clean();

		remove_filter( 'wp_title', array( 'Print_Template', 'page_title' ) );
		remove_filter( 'comments_template', array( 'Print_Template', 'comments_template' ) );

		return $html;
	}

	/**
	 * A post with a link, an image and a comment.
	 *
	 * @return int
	 */
	private function make_post() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Render Post',
				'post_name'    => 'render-post',
				'post_content' => 'Body with <a href="https://example.com/one">a link</a> and '
					. '<img src="https://example.com/p.png" alt="pic" /> and '
					. '[donotprint]SECRET[/donotprint]',
			)
		);

		self::factory()->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_author'  => 'Ada',
				'comment_content' => 'A comment.',
			)
		);

		return $post_id;
	}

	/**
	 * The document is a standalone page, not the theme's.
	 */
	public function test_the_document_is_standalone() {
		$html = $this->render_document( $this->make_post() );

		$this->assertStringContainsString( '<!DOCTYPE html>', $html );
		$this->assertStringContainsString( '</html>', $html );
		$this->assertStringContainsString( 'noindex, nofollow', $html );
		$this->assertStringContainsString( 'rel="canonical"', $html );
		$this->assertStringContainsString( 'css/wp-print.css', $html );
	}

	/**
	 * No PHP diagnostic leaks into the page.
	 */
	public function test_the_document_is_clean() {
		$html = $this->render_document( $this->make_post() );

		foreach ( array( 'Warning:', 'Notice:', 'Deprecated:', 'Fatal error', 'Undefined' ) as $noise ) {
			$this->assertStringNotContainsString( $noise, $html );
		}

		// A /* translators: */ comment that drifts into HTML context gets printed.
		$this->assertStringNotContainsString( 'translators:', $html );
		$this->assertStringNotContainsString( '<?php', $html );
	}

	/**
	 * The content, the byline and the footer all render.
	 */
	public function test_the_document_carries_the_post() {
		$html = $this->render_document( $this->make_post() );

		$this->assertStringContainsString( 'Render Post', $html );
		$this->assertStringContainsString( 'Posted By', $html );
		$this->assertStringContainsString( 'Article printed from', $html );
		$this->assertStringContainsString( 'URL to article', $html );
		$this->assertStringContainsString( 'RENDERDISCLAIMER', $html );
		$this->assertStringContainsString( 'to print.', $html );
	}

	/**
	 * Links are footnoted and the URL list is printed, which is the whole point of
	 * a printable view.
	 */
	public function test_the_document_footnotes_its_links() {
		$html = $this->render_document( $this->make_post() );

		$this->assertStringContainsString( '<sup>[1]</sup>', $html );
		$this->assertStringContainsString( 'URLs in this post:', $html );
		$this->assertStringContainsString( 'https://example.com/one', $html );
	}

	/**
	 * Content inside [donotprint] never reaches the page.
	 */
	public function test_the_document_drops_donotprint_content() {
		$this->assertStringNotContainsString( 'SECRET', $this->render_document( $this->make_post() ) );
	}

	/**
	 * The title carries the print suffix exactly once - the wp_title filter adds
	 * it, so a template appending it too produced "... &raquo; Print &raquo; Print".
	 */
	public function test_the_title_carries_the_suffix_once() {
		$html = $this->render_document( $this->make_post() );

		preg_match( '#<title>(.*?)</title>#s', $html, $matches );

		$this->assertNotEmpty( $matches, 'The document has no title element.' );
		$this->assertSame( 1, substr_count( $matches[1], '&raquo; Print' ) );
	}

	/**
	 * Behaviour comes from the script, never from inline attributes - two of which
	 * previously carried a javascript: label.
	 */
	public function test_the_document_uses_no_inline_handlers() {
		$html = $this->render_document( $this->make_post() );

		$this->assertStringNotContainsString( 'onclick=', $html );
		$this->assertStringNotContainsString( 'javascript:', $html );
		$this->assertStringContainsString( 'js/wp-print.js', $html );
		$this->assertStringContainsString( 'data-print-action="print"', $html );
	}

	/**
	 * The comments template runs when comments are switched on, and not otherwise.
	 */
	public function test_comments_render_only_when_switched_on() {
		$post_id = $this->make_post();

		$with = $this->render_document( $post_id );
		$this->assertStringContainsString( 'comments_box', $with );
		$this->assertStringContainsString( 'Ada', $with );
		$this->assertStringContainsString( 'data-print-action="open"', $with );

		update_option(
			Print_Options::OPTION_NAME,
			array_merge( Print_Options::get(), array( 'comments' => 0 ) )
		);

		$without = $this->render_document( $post_id );
		$this->assertStringNotContainsString( 'comments_box', $without );
	}

	/**
	 * A comment awaiting moderation says so rather than being shown as approved.
	 */
	public function test_an_unapproved_comment_is_marked() {
		$post_id = self::factory()->post->create( array( 'post_name' => 'moderated-post' ) );

		// Authored by the current user: comments_template() only pulls in an
		// unapproved comment for whoever wrote it, so any other author's would
		// never reach the template and the test would pass for the wrong reason.
		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_content'  => 'Held back.',
				'comment_approved' => '0',
				'user_id'          => get_current_user_id(),
			)
		);

		$html = $this->render_document( $post_id );

		$this->assertStringContainsString( 'awaiting moderation', $html );
	}

	/**
	 * The language attribute is populated, including for a locale that carries no
	 * region - strpos() returns false for those and substr( $s, 0, false ) is ''.
	 *
	 * @dataProvider data_locales
	 *
	 * @param string $locale   Locale to force.
	 * @param string $expected Expected lang attribute.
	 */
	public function test_the_language_attribute( $locale, $expected ) {
		$force = static function () use ( $locale ) {
			return $locale;
		};

		add_filter( 'locale', $force );
		add_filter( 'determine_locale', $force );

		$html = $this->render_document( $this->make_post() );

		remove_filter( 'locale', $force );
		remove_filter( 'determine_locale', $force );

		$this->assertStringContainsString( 'lang="' . $expected . '"', $html );
		$this->assertStringNotContainsString( 'lang=""', $html );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_locales() {
		return array(
			'with a region'    => array( 'en_US', 'en' ),
			'without a region' => array( 'ca', 'ca' ),
			'other region'     => array( 'pt_BR', 'pt' ),
		);
	}

	/**
	 * A right-to-left locale flips the document direction. The old template read a
	 * $text_direction global that no longer exists, so RTL never triggered.
	 */
	public function test_a_right_to_left_locale_flips_the_document() {
		global $wp_locale;

		$was                       = $wp_locale->text_direction;
		$wp_locale->text_direction = 'rtl';

		try {
			$html = $this->render_document( $this->make_post() );

			$this->assertStringContainsString( 'dir="rtl"', $html );
			$this->assertStringContainsString( 'wp-print-rtl.css', $html );
			$this->assertStringContainsString( 'text-align: left', $html );
		} finally {
			$wp_locale->text_direction = $was;
		}
	}

	/**
	 * ...and a left-to-right one does not.
	 */
	public function test_a_left_to_right_locale_leaves_the_document_alone() {
		$html = $this->render_document( $this->make_post() );

		$this->assertStringContainsString( 'dir="ltr"', $html );
		$this->assertStringNotContainsString( 'wp-print-rtl.css', $html );
	}

	/**
	 * The featured image is printed only when the option is on.
	 */
	public function test_the_thumbnail_follows_its_option() {
		$post_id = $this->make_post();

		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'           => 'thumb.jpg',
				'post_parent'    => $post_id,
				'post_mime_type' => 'image/jpeg',
			)
		);
		set_post_thumbnail( $post_id, $attachment_id );

		update_option( Print_Options::OPTION_NAME, array_merge( Print_Options::get(), array( 'thumbnail' => 0 ) ) );
		$this->assertStringNotContainsString( 'class="thumbnail"', $this->render_document( $post_id ) );

		update_option( Print_Options::OPTION_NAME, array_merge( Print_Options::get(), array( 'thumbnail' => 1 ) ) );
		$this->assertStringContainsString( 'class="thumbnail"', $this->render_document( $post_id ) );
	}

	/**
	 * With nothing to show, the document says so instead of rendering an empty
	 * shell - and still closes its tags.
	 */
	public function test_an_empty_query_renders_the_fallback() {
		$this->go_to( home_url( '/?p=999999' ) );

		Print_Content::reset();
		$print_options = Print_Options::get();

		ob_start();
		require Print_Template::locate( 'print-posts.php' );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'No posts matched your criteria.', $html );
		$this->assertStringContainsString( 'RENDERDISCLAIMER', $html );
		$this->assertStringContainsString( '</html>', $html );
	}
}
