<?php
/**
 * The print template, the remaining template tags, and the print endpoint.
 *
 * @package WP-Print
 */

/**
 * Tests for the print template and the endpoint.
 *
 * @covers WP_Print_Template
 * @covers WP_Print
 */
class WP_Print_Template_Test extends WP_Print_TestCase {

	/**
	 * Pretty permalinks and a known option row.
	 */
	public function set_up() {
		parent::set_up();

		$this->set_structure( '/%postname%/' );
		update_option( WP_Print_Options::OPTION, WP_Print_Options::get_defaults() );
	}

	/**
	 * Change the permalink structure and re-register the print endpoint.
	 *
	 * WP_Rewrite::init() empties $wp_rewrite->endpoints, and set_permalink_structure()
	 * calls it - so a test that changes the structure loses the endpoint the plugin
	 * registered on `init`, which does not fire a second time. On a real request the
	 * order is the other way round: `init` runs, and any later flush regenerates the
	 * rules with the endpoint still registered. Re-registering here reproduces that.
	 *
	 * @param string $structure Permalink structure.
	 */
	private function set_structure( $structure ) {
		$this->set_permalink_structure( $structure );

		WP_Print::add_endpoint();
		flush_rewrite_rules( false );
	}

	/**
	 * With no theme override, the bundled templates are used.
	 */
	public function test_locate_falls_back_to_the_plugin_copy() {
		$path = WP_Print_Template::locate( 'print-posts.php' );

		$this->assertFileExists( $path );
		$this->assertStringContainsString( plugin_dir_path( WP_PRINT_MAIN_FILE ), $path );
	}

	/**
	 * The comments template path resolves to a readable file.
	 */
	public function test_comments_template_is_readable() {
		$path = print_template_comments();

		$this->assertFileExists( $path );
		$this->assertStringEndsWith( 'print-comments.php', $path );
	}

	/**
	 * A theme's copy wins, which is the documented way to restyle the print view
	 * without losing the change on upgrade.
	 */
	public function test_a_theme_copy_takes_precedence() {
		$theme_file = get_stylesheet_directory() . '/print-posts.php';

		if ( file_exists( $theme_file ) ) {
			$this->markTestSkipped( 'The active theme already ships a print-posts.php.' );
		}

		$theme_css = get_stylesheet_directory() . '/print-css.css';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture in the test theme directory.
		file_put_contents( $theme_file, '<?php // fixture' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture in the test theme directory.
		file_put_contents( $theme_css, '/* fixture */' );

		try {
			$this->assertSame( $theme_file, WP_Print_Template::locate( 'print-posts.php' ) );
			$this->assertSame(
				get_stylesheet_directory_uri() . '/print-css.css',
				WP_Print_Template::asset_url( 'print-css.css' )
			);
		} finally {
			wp_delete_file( $theme_file );
			wp_delete_file( $theme_css );
		}
	}

	/**
	 * Assets fall back to the plugin's own copy.
	 */
	public function test_asset_url_falls_back_to_the_plugin() {
		$this->assertSame(
			plugins_url( 'css/wp-print.css', WP_PRINT_MAIN_FILE ),
			WP_Print_Template::asset_url( 'print-css.css' )
		);
	}

	/**
	 * Plugin-only assets always resolve inside the plugin, whatever the theme has.
	 */
	public function test_plugin_url_resolves_inside_the_plugin() {
		$this->assertSame(
			plugins_url( 'js/wp-print.js', WP_PRINT_MAIN_FILE ),
			WP_Print_Template::plugin_url( 'js/wp-print.js' )
		);
	}

	/**
	 * The title suffix is appended once. It comes from the wp_title filter, so a
	 * template that also appends it by hand produced "... &raquo; Print &raquo; Print".
	 */
	public function test_page_title_appends_the_suffix_once() {
		$this->assertSame( 'Title &raquo; Print', print_pagetitle( 'Title' ) );
		$this->assertSame( 1, substr_count( print_pagetitle( 'Title' ), 'Print' ) );
	}

	/**
	 * Categories are separated with a comma and a space, and each is wrapped in the
	 * caller's markup rather than the whole list being wrapped once.
	 */
	public function test_categories_are_separated_and_wrapped_individually() {
		$a = self::factory()->category->create( array( 'name' => 'Alpha' ) );
		$b = self::factory()->category->create( array( 'name' => 'Beta' ) );

		$post_id = self::factory()->post->create( array( 'post_category' => array( $a, $b ) ) );

		$this->go_to( get_permalink( $post_id ) );
		the_post();

		ob_start();
		print_categories();
		$plain = ob_get_clean();

		$this->assertStringContainsString( 'Alpha, Beta', $plain );
		$this->assertStringNotContainsString( '<', $plain );

		ob_start();
		print_categories( '<span>', '</span>' );
		$wrapped = ob_get_clean();

		$this->assertSame( '<span>Alpha</span>, <span>Beta</span>', $wrapped );
	}

	/**
	 * The comment count reads as a sentence, in each of its four states.
	 */
	public function test_comments_number_covers_every_state() {
		$post_id = self::factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );
		the_post();
		$this->assertSame( 'No Comments', $this->comments_number() );

		self::factory()->comment->create_many( 2, array( 'comment_post_ID' => $post_id ) );
		$this->go_to( get_permalink( $post_id ) );
		the_post();
		$this->assertSame( '2 Comments', $this->comments_number() );

		$one_id = self::factory()->post->create();
		self::factory()->comment->create( array( 'comment_post_ID' => $one_id ) );
		$this->go_to( get_permalink( $one_id ) );
		the_post();
		$this->assertSame( '1 Comment', $this->comments_number() );

		$closed_id = self::factory()->post->create( array( 'comment_status' => 'closed' ) );
		$this->go_to( get_permalink( $closed_id ) );
		the_post();
		$this->assertSame( 'Comments Disabled', $this->comments_number() );

		$pw_id = self::factory()->post->create( array( 'post_password' => 'x' ) );
		$this->go_to( get_permalink( $pw_id ) );
		the_post();
		$this->assertSame( 'Comments Hidden', $this->comments_number() );
	}

	/**
	 * Capture print_comments_number().
	 *
	 * @return string
	 */
	private function comments_number() {
		ob_start();
		print_comments_number();

		return ob_get_clean();
	}

	/**
	 * The URL list prints its heading only when there is something to head.
	 */
	public function test_links_list_prints_only_when_populated() {
		WP_Print_Content::reset();

		ob_start();
		print_links();
		$this->assertSame( '', ob_get_clean() );

		$GLOBALS['links_text'] = '<p>entry</p>';

		ob_start();
		print_links();
		$this->assertSame( 'URLs in this post:<p>entry</p>', ob_get_clean() );

		ob_start();
		print_links( 'Mine:' );
		$this->assertSame( 'Mine:<p>entry</p>', ob_get_clean() );
	}

	/**
	 * The disclaimer is printed with its permitted HTML intact and script removed.
	 */
	public function test_disclaimer_keeps_markup_but_not_script() {
		update_option(
			WP_Print_Options::OPTION,
			array_merge( WP_Print_Options::get_defaults(), array( 'disclaimer' => '<strong>Mine</strong><script>alert(1)</script>' ) )
		);

		ob_start();
		print_disclaimer();
		$out = ob_get_clean();

		$this->assertStringContainsString( '<strong>Mine</strong>', $out );
		$this->assertStringNotContainsString( '<script', $out );
	}

	/**
	 * The endpoint and its query var are registered, and the rewrite rules carry a
	 * print rule for posts as well as for pages.
	 */
	public function test_the_print_endpoint_is_registered() {
		global $wp_rewrite, $wp;

		$names = array();
		foreach ( (array) $wp_rewrite->endpoints as $endpoint ) {
			$names[] = $endpoint[1];
		}

		$this->assertContains( 'print', $names );
		$this->assertContains( 'print', $wp->public_query_vars );

		$print_rules = array_filter(
			array_keys( $wp_rewrite->wp_rewrite_rules() ),
			static function ( $pattern ) {
				return false !== strpos( $pattern, 'print' );
			}
		);

		$this->assertNotEmpty( $print_rules );
	}

	/**
	 * A print URL routes to the print view rather than to the ordinary post, under
	 * every permalink structure - not just the default one.
	 *
	 * @dataProvider data_permalink_structures
	 *
	 * @param string $structure Permalink structure.
	 */
	public function test_a_print_url_routes_to_the_print_view( $structure ) {
		$this->set_structure( $structure );

		$post_id = self::factory()->post->create( array( 'post_name' => 'routed-post' ) );

		$this->go_to( trailingslashit( get_permalink( $post_id ) ) . 'print/' );

		$this->assertArrayHasKey( 'print', $GLOBALS['wp_query']->query_vars );
		$this->assertFalse( is_404() );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_permalink_structures() {
		return array(
			'postname'   => array( '/%postname%/' ),
			'day-name'   => array( '/%year%/%monthnum%/%day%/%postname%/' ),
			'month-name' => array( '/%year%/%monthnum%/%postname%/' ),
			'numeric'    => array( '/archives/%post_id%' ),
		);
	}

	/**
	 * A page's print URL routes too.
	 */
	public function test_a_page_print_url_routes() {
		$page_id = self::factory()->post->create(
			array(
				'post_type' => 'page',
				'post_name' => 'routed-page',
			)
		);

		$this->go_to( trailingslashit( get_permalink( $page_id ) ) . 'print/' );

		$this->assertArrayHasKey( 'print', $GLOBALS['wp_query']->query_vars );
		$this->assertFalse( is_404() );
	}

	/**
	 * An ordinary post URL must not be treated as a print request, or every page
	 * on the site would render as the print view.
	 */
	public function test_an_ordinary_url_is_not_a_print_request() {
		$post_id = self::factory()->post->create();

		$this->go_to( get_permalink( $post_id ) );

		$this->assertArrayNotHasKey( 'print', $GLOBALS['wp_query']->query_vars );
	}
}
