<?php
/**
 * The rendered print document.
 *
 * WP_Print_Template::render() ends in exit(), so it cannot be called from a test.
 * These tests set the query up the way it does and require the same template, so
 * the assertions are against the document a reader actually gets.
 *
 * @package WP-Print
 */

/**
 * Tests for the rendered print document.
 *
 * @covers WP_Print_Template
 */
class WP_Print_Render_Test extends WP_Print_TestCase {

	/**
	 * Pretty permalinks, a known option row, and content worth printing.
	 */
	public function set_up() {
		parent::set_up();

		// Fixtures are written as a user who may post unfiltered HTML; see the note
		// in WP_Print_Content_Test::set_up().
		wp_set_current_user( $this->create_admin() );

		$this->set_permalink_structure( '/%postname%/' );

		update_option(
			WP_Print_Options::OPTION,
			array_merge(
				WP_Print_Options::get_defaults(),
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

		WP_Print_Content::reset();
		$this->register_assets();

		add_filter( 'wp_title', array( 'WP_Print_Template', 'page_title' ) );
		add_filter( 'comments_template', array( 'WP_Print_Template', 'comments_template' ) );
		add_filter( 'comments_array', array( 'WP_Print_Template', 'hide_protected_comments' ), 10, 2 );

		// The template reads $print_options directly for the disclaimer, so it has
		// to be in scope for the require - exactly as WP_Print_Template::render() does.
		$print_options = WP_Print_Options::get();

		ob_start();
		require WP_Print_Template::locate( 'print-posts.php' );
		$html = ob_get_clean();

		remove_filter( 'wp_title', array( 'WP_Print_Template', 'page_title' ) );
		remove_filter( 'comments_template', array( 'WP_Print_Template', 'comments_template' ) );
		remove_filter( 'comments_array', array( 'WP_Print_Template', 'hide_protected_comments' ), 10 );

		return $html;
	}

	/**
	 * Register the print view's handles against an empty dependency registry.
	 *
	 * WP_Dependencies remembers what it has already printed, so a second render
	 * inside one PHP process would emit no stylesheet and no script at all. A
	 * real request renders one document and exits; the suite renders several, so
	 * the registries are rebuilt for each one.
	 *
	 * @return void
	 */
	private function register_assets() {
		$GLOBALS['wp_styles']  = null;
		$GLOBALS['wp_scripts'] = null;

		WP_Print_Template::register_assets();
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

		$this->assertStringContainsString( '<!DOCTYPE html>', $html, 'The printout is a whole document rather than a fragment.' );
		$this->assertStringContainsString( '</html>', $html, 'Closed.' );
		$this->assertStringContainsString( 'noindex, nofollow', $html, 'Asking not to be indexed, since it duplicates the post.' );
		$this->assertStringContainsString( 'rel="canonical"', $html, 'And pointing at the post it duplicates.' );
		$this->assertStringContainsString( 'css/wp-print.css', $html, 'With its own stylesheet, since the theme is not loaded.' );
	}

	/**
	 * No PHP diagnostic leaks into the page.
	 */
	public function test_the_document_is_clean() {
		$html = $this->render_document( $this->make_post() );

		foreach ( array( 'Warning:', 'Notice:', 'Deprecated:', 'Fatal error', 'Undefined' ) as $noise ) {
			$this->assertStringNotContainsString( $noise, $html, 'The rendered document carries a PHP ' . $noise . ' diagnostic.' );
		}

		// A /* translators: */ comment that drifts into HTML context gets printed.
		$this->assertStringNotContainsString( 'translators:', $html, 'No translator comment leaked into the document.' );
		$this->assertStringNotContainsString( '<?php', $html, 'And no PHP tag, which would mean a template was echoed unparsed.' );
	}

	/**
	 * The content, the byline and the footer all render.
	 */
	public function test_the_document_carries_the_post() {
		$html = $this->render_document( $this->make_post() );

		$this->assertStringContainsString( 'Render Post', $html, 'The document carries the title.' );
		$this->assertStringContainsString( 'Posted By', $html, 'The byline.' );
		$this->assertStringContainsString( 'Article printed from', $html, 'The provenance line.' );
		$this->assertStringContainsString( 'URL to article', $html, 'The source URL.' );
		$this->assertStringContainsString( 'RENDERDISCLAIMER', $html, 'The configured disclaimer.' );
		$this->assertStringContainsString( 'to print.', $html, 'And the instruction to print.' );
	}

	/**
	 * Links are footnoted and the URL list is printed, which is the whole point of
	 * a printable view.
	 */
	public function test_the_document_footnotes_its_links() {
		$html = $this->render_document( $this->make_post() );

		$this->assertStringContainsString( '<sup>[1]</sup>', $html, 'A link in the body is footnoted.' );
		$this->assertStringContainsString( 'URLs in this post:', $html, 'Under a heading.' );
		$this->assertStringContainsString( 'https://example.com/one', $html, 'With the URL spelled out, since a printout cannot be clicked.' );
	}

	/**
	 * Content inside [donotprint] never reaches the page.
	 */
	public function test_the_document_drops_donotprint_content() {
		$this->assertStringNotContainsString( 'SECRET', $this->render_document( $this->make_post() ), 'Content marked donotprint is dropped from the document.' );
	}

	/**
	 * The title carries the print suffix exactly once - the wp_title filter adds
	 * it, so a template appending it too produced "... &raquo; Print &raquo; Print".
	 */
	public function test_the_title_carries_the_suffix_once() {
		$html = $this->render_document( $this->make_post() );

		preg_match( '#<title>(.*?)</title>#s', $html, $matches );

		$this->assertNotEmpty( $matches, 'The document has no title element.' );
		$this->assertSame( 1, substr_count( $matches[1], '&raquo; Print' ), 'The suffix is appended to the title once, not once per filter pass.' );
	}

	/**
	 * Behaviour comes from the script, never from inline attributes - two of which
	 * previously carried a javascript: label.
	 */
	public function test_the_document_uses_no_inline_handlers() {
		$html = $this->render_document( $this->make_post() );

		$this->assertStringNotContainsString( 'onclick=', $html, 'The document uses no inline handler.' );
		$this->assertStringNotContainsString( 'javascript:', $html, 'And no javascript URL.' );
		$this->assertStringContainsString( 'js/wp-print.js', $html, 'The behaviour is in a file.' );
		$this->assertStringContainsString( 'data-print-action="print"', $html, 'Bound by data attribute, which is what lets the CSP stay strict.' );
	}

	/**
	 * The comments template runs when comments are switched on, and not otherwise.
	 */
	public function test_comments_render_only_when_switched_on() {
		$post_id = $this->make_post();

		$with = $this->render_document( $post_id );
		$this->assertStringContainsString( 'comments_box', $with, 'With comments on, the thread is printed.' );
		$this->assertStringContainsString( 'Ada', $with, 'Naming the commenters.' );
		$this->assertStringContainsString( 'data-print-action="open"', $with, 'And the control to expand it is bound by data attribute too.' );

		update_option(
			WP_Print_Options::OPTION,
			array_merge( WP_Print_Options::get(), array( 'comments' => 0 ) )
		);

		$without = $this->render_document( $post_id );
		$this->assertStringNotContainsString( 'comments_box', $without, 'With comments off, no thread is printed at all.' );
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

		$this->assertStringContainsString( 'awaiting moderation', $html, 'A comment awaiting moderation is marked as such rather than printed as approved.' );
	}

	/**
	 * A password-protected post prints neither its body nor its thread.
	 *
	 * The print view is a second route to the same post, and for the plugin's whole
	 * life it was the route that did not lock: the body was withheld and the count
	 * said "Comments Hidden", so the plugin knew the state perfectly well, and then
	 * printed every comment behind the lock underneath the notice. Anyone who could
	 * guess a URL could read the discussion of a post they could not read.
	 */
	public function test_a_locked_post_prints_neither_its_body_nor_its_thread() {
		$post_id = self::factory()->post->create(
			array(
				'post_name'     => 'locked-post',
				'post_content'  => 'The secret body.',
				'post_password' => 'letmein',
			)
		);

		self::factory()->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_author'  => 'Ada',
				'comment_content' => 'A secret comment.',
			)
		);

		$html = $this->render_document( $post_id );

		$this->assertStringNotContainsString( 'The secret body', $html, 'A password-protected post prints no body.' );
		$this->assertStringNotContainsString( 'A secret comment', $html, 'And no comment from its thread.' );
		$this->assertStringNotContainsString( 'Ada', $html, 'The thread leaked who is talking behind the lock.' );

		// The count is a sentence rather than a number for the same reason: a
		// number would itself say how much discussion there is.
		$this->assertStringContainsString( 'Comments Hidden', $html, 'Saying so, rather than printing an empty section.' );
		$this->assertStringNotContainsString( 'comments_box', $html, 'And the thread markup is absent entirely.' );
	}

	/**
	 * The bundled comments template withholds a locked thread on its own.
	 *
	 * Separate from the test above because the comments_array filter empties the
	 * array before any template runs, so that test cannot tell whether the guard
	 * inside the file works. The file is one of the two a theme customises by
	 * copying, and a theme's copy is exactly where the filter is the only thing
	 * left -- so both have to be asserted, and this is the half a copy carries.
	 */
	public function test_the_comments_template_withholds_a_locked_thread_on_its_own() {
		$post_id = self::factory()->post->create(
			array(
				'post_name'     => 'locked-thread',
				'post_password' => 'letmein',
			)
		);

		self::factory()->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_author'  => 'Ada',
				'comment_content' => 'A secret comment.',
			)
		);

		$this->go_to( get_permalink( $post_id ) );
		the_post();

		// The comment loop primed the way comments_template() primes it, with the
		// filter deliberately not in the way.
		global $wp_query;

		$wp_query->comments      = get_comments( array( 'post_id' => $post_id ) );
		$wp_query->comment_count = count( $wp_query->comments );

		ob_start();
		require WP_PRINT_DIR . 'includes/print-comments.php';
		$html = ob_get_clean();

		$this->assertSame( '', trim( $html ), 'The comments template withholds a locked thread even when rendered on its own.' );
	}

	/**
	 * The filter answers for the post it was asked about, and leaves every other
	 * thread alone.
	 */
	public function test_only_a_locked_thread_is_withheld() {
		$open_id = self::factory()->post->create( array( 'post_name' => 'open-post' ) );

		$locked_id = self::factory()->post->create(
			array(
				'post_name'     => 'locked-filter',
				'post_password' => 'letmein',
			)
		);

		$comments = array( (object) array( 'comment_ID' => 1 ) );

		$this->assertSame( $comments, WP_Print_Template::hide_protected_comments( $comments, $open_id ), 'An open thread is returned whole.' );
		$this->assertSame( array(), WP_Print_Template::hide_protected_comments( $comments, $locked_id ), 'While only the locked one is withheld.' );
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

		$this->assertStringContainsString( 'lang="' . $expected . '"', $html, 'The document declares the ' . $expected . ' language.' );
		$this->assertStringNotContainsString( 'lang=""', $html, 'Never an empty one, which is worse than none.' );
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
	 * A right-to-left locale sets dir="rtl" and changes nothing else.
	 *
	 * That one attribute is the whole mechanism now. The stylesheet uses logical
	 * properties -- margin-inline-start, float: inline-start, text-align: end --
	 * so the browser mirrors the layout off the dir attribute and the second
	 * stylesheet that used to do it by hand is gone. The old template read a
	 * $text_direction global that no longer existed, so RTL never triggered at
	 * all; this asserts it does, without a mirrored sheet behind it.
	 */
	public function test_a_right_to_left_locale_flips_the_document() {
		global $wp_locale;

		$was                       = $wp_locale->text_direction;
		$wp_locale->text_direction = 'rtl';

		try {
			$html = $this->render_document( $this->make_post() );

			$this->assertStringContainsString( 'dir="rtl"', $html, 'A right-to-left locale flips the document.' );
			$this->assertStringNotContainsString( '-rtl.css', $html, 'No plugin in this family ships a mirrored stylesheet.' );
			$this->assertStringContainsString( 'css/wp-print.css', $html, 'The one sheet serves both directions.' );
		} finally {
			$wp_locale->text_direction = $was;
		}
	}

	/**
	 * ...and a left-to-right one loads exactly the same stylesheet.
	 */
	public function test_a_left_to_right_locale_loads_the_same_stylesheet() {
		$html = $this->render_document( $this->make_post() );

		$this->assertStringContainsString( 'dir="ltr"', $html, 'A left-to-right locale declares its direction too.' );
		$this->assertStringNotContainsString( '-rtl.css', $html, 'Without loading a second stylesheet.' );
		$this->assertStringContainsString( 'css/wp-print.css', $html, 'The one file serves both directions, through logical properties.' );
	}

	/**
	 * The document carries the root class every rule in the sheet is scoped to.
	 *
	 * A theme that copied print-posts.php before 3.0.0 has a <body> with no
	 * class on it, and would get an unstyled print view -- which is why the
	 * upgrade notice says so.
	 */
	public function test_the_document_body_carries_the_root_class() {
		$this->assertStringContainsString( '<body class="wp-print">', $this->render_document( $this->make_post() ), 'The body carries the root class every rule is scoped under.' );
	}

	/**
	 * Nothing in the document styles itself inline.
	 */
	public function test_the_document_uses_no_inline_styles() {
		$html = $this->render_document( $this->make_post() );

		$this->assertStringNotContainsString( 'style="', $html, 'Styling belongs in css/wp-print.css.' );
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

		update_option( WP_Print_Options::OPTION, array_merge( WP_Print_Options::get(), array( 'thumbnail' => 0 ) ) );
		$this->assertStringNotContainsString( 'class="thumbnail"', $this->render_document( $post_id ), 'With the thumbnail off, none is printed.' );

		update_option( WP_Print_Options::OPTION, array_merge( WP_Print_Options::get(), array( 'thumbnail' => 1 ) ) );
		$this->assertStringContainsString( 'class="thumbnail"', $this->render_document( $post_id ), 'And with it on, one is.' );
	}

	/**
	 * With nothing to show, the document says so instead of rendering an empty
	 * shell - and still closes its tags.
	 */
	public function test_an_empty_query_renders_the_fallback() {
		$this->go_to( home_url( '/?p=999999' ) );

		WP_Print_Content::reset();
		$this->register_assets();
		$print_options = WP_Print_Options::get();

		ob_start();
		require WP_Print_Template::locate( 'print-posts.php' );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'No posts matched your criteria.', $html, 'An empty query prints the fallback rather than an empty page.' );
		$this->assertStringContainsString( 'RENDERDISCLAIMER', $html, 'With the disclaimer still on it.' );
		$this->assertStringContainsString( '</html>', $html, 'And the document still closed.' );
	}
}
