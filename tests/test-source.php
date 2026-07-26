<?php
/**
 * Source-level guards.
 *
 * A single-site suite cannot build a 101-site network, so the multisite fan-out
 * arguments are asserted against the source instead. That is deliberate rather
 * than lazy: the failure mode is silent - uninstall reports success and leaves
 * rows behind on every site past the hundredth - so the argument must not be
 * removable without a test going red.
 *
 * @package WP-Print
 */

/**
 * Tests asserted against the plugin source.
 *
 * @coversNothing
 */
class Test_Print_Source extends WP_UnitTestCase {

	/**
	 * The plugin root, with a trailing slash.
	 *
	 * @var string
	 */
	private $dir;

	/**
	 * Resolve the plugin root.
	 */
	public function set_up() {
		parent::set_up();

		$this->dir = plugin_dir_path( WP_PRINT_MAIN_FILE );
	}

	/**
	 * Read a plugin file with its comments stripped.
	 *
	 * Grepping raw source for a legacy symbol gives false positives from the very
	 * comment that explains why the symbol is gone, so these assertions only ever
	 * look at live code.
	 *
	 * @param string $relative Path relative to the plugin root.
	 * @return string
	 */
	private function code( $relative ) {
		$source = file_get_contents( $this->dir . $relative ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading the plugin's own source.
		$code   = '';

		foreach ( token_get_all( (string) $source ) as $token ) {
			if ( is_array( $token ) ) {
				if ( T_COMMENT === $token[0] || T_DOC_COMMENT === $token[0] ) {
					continue;
				}
				$code .= $token[1];
			} else {
				$code .= $token;
			}
		}

		return $code;
	}

	/**
	 * Every PHP file the plugin ships.
	 *
	 * @return array
	 */
	private function php_files() {
		$files = array_merge(
			(array) glob( $this->dir . '*.php' ),
			(array) glob( $this->dir . 'includes/*.php' )
		);

		return array_map(
			function ( $path ) {
				return str_replace( $this->dir, '', $path );
			},
			array_filter( $files )
		);
	}

	/**
	 * The uninstaller lifts WP_Site_Query's default cap of 100.
	 *
	 * Source-level on purpose: see the class docblock.
	 */
	public function test_uninstall_lifts_the_site_query_cap() {
		$this->assertMatchesRegularExpression( "/'number'\s*=>\s*0/", $this->code( 'uninstall.php' ) );
	}

	/**
	 * The uninstaller asks only for the IDs the loop actually uses.
	 */
	public function test_uninstall_queries_ids_only() {
		$this->assertMatchesRegularExpression( "/'fields'\s*=>\s*'ids'/", $this->code( 'uninstall.php' ) );
	}

	/**
	 * The removed wp_get_sites() is not called: it went in WordPress 5.1, so a call
	 * fatals on a multisite uninstall rather than merely skipping sites.
	 */
	public function test_uninstall_does_not_call_wp_get_sites() {
		$this->assertStringNotContainsString( 'wp_get_sites', $this->code( 'uninstall.php' ) );
	}

	/**
	 * The blog is restored inside the loop: switch_to_blog() pushes onto
	 * a stack, so restoring once afterwards leaves it unwound by all but one entry.
	 */
	public function test_uninstall_restores_the_blog_inside_the_loop() {
		$this->assertMatchesRegularExpression(
			'/switch_to_blog\([^;]*;.*?restore_current_blog\(\);.*?\}/s',
			$this->code( 'uninstall.php' )
		);
	}

	/**
	 * The same three properties hold for the activation fan-out.
	 */
	public function test_activation_fan_out_matches_uninstall() {
		$code = $this->code( 'includes/class-print-core.php' );

		$this->assertMatchesRegularExpression( "/'number'\s*=>\s*0/", $code );
		$this->assertMatchesRegularExpression( "/'fields'\s*=>\s*'ids'/", $code );
		$this->assertStringNotContainsString( 'wp_get_sites', $code );
		$this->assertMatchesRegularExpression(
			'/switch_to_blog\([^;]*;.*?restore_current_blog\(\);.*?\}/s',
			$code
		);
	}

	/**
	 * The uninstaller refuses to run unless WordPress invoked it.
	 */
	public function test_uninstall_guards_on_the_constant() {
		$this->assertStringContainsString( 'WP_UNINSTALL_PLUGIN', $this->code( 'uninstall.php' ) );
	}

	/**
	 * The uninstaller removes both rows the plugin owns, including the schema version
	 * one, which is easy to forget because nothing reads it after uninstall.
	 */
	public function test_uninstall_removes_every_option_the_plugin_owns() {
		$code = $this->code( 'uninstall.php' );

		$this->assertStringContainsString( "delete_option( 'print_options' )", $code );
		$this->assertStringContainsString( "delete_option( 'print_db_version' )", $code );
	}

	/**
	 * Every shipped PHP file refuses to run when loaded directly.
	 *
	 * The index.php silence guard contains nothing to protect, and
	 * uninstall.php guards on WP_UNINSTALL_PLUGIN instead.
	 * Both are therefore skipped.
	 */
	public function test_every_file_refuses_direct_access() {
		foreach ( $this->php_files() as $relative ) {
			if ( in_array( $relative, array( 'index.php', 'includes/index.php', 'uninstall.php' ), true ) ) {
				continue;
			}

			$this->assertStringContainsString(
				'ABSPATH',
				$this->code( $relative ),
				"$relative has no ABSPATH guard"
			);
		}
	}

	/**
	 * Since WordPress 6.7 a textdomain loaded before init triggers
	 * _doing_it_wrong, and translate.wordpress.org has served these translations
	 * automatically since 4.6.
	 */
	public function test_no_textdomain_loader() {
		foreach ( $this->php_files() as $relative ) {
			$this->assertStringNotContainsString(
				'load_plugin_textdomain',
				$this->code( $relative ),
				"$relative still loads a textdomain"
			);
		}
	}

	/**
	 * The plugin directory name is not guaranteed - a user may rename the folder -
	 * so paths and URLs are resolved from the main file rather than spelled out.
	 */
	public function test_no_hardcoded_plugin_directory() {
		foreach ( $this->php_files() as $relative ) {
			$code = $this->code( $relative );

			$this->assertStringNotContainsString( "'wp-print/", $code, "$relative hardcodes the plugin directory" );
			$this->assertStringNotContainsString( '/wp-print/', $code, "$relative hardcodes the plugin directory" );
		}
	}

	/**
	 * The print view carries no inline event handlers, in either template.
	 */
	public function test_templates_have_no_inline_handlers() {
		foreach ( array( 'print-posts.php', 'print-comments.php' ) as $template ) {
			$source = $this->code( $template );

			$this->assertStringNotContainsString( 'onclick=', $source, "$template still uses an inline onclick" );
			$this->assertStringNotContainsString( 'javascript:', $source, "$template still uses a javascript: URL" );
		}
	}

	/**
	 * No script is enqueued with a jQuery dependency.
	 */
	public function test_no_jquery_dependency() {
		foreach ( $this->php_files() as $relative ) {
			$this->assertStringNotContainsString( "'jquery'", $this->code( $relative ), "$relative depends on jQuery" );
		}
	}

	/**
	 * The version constant, the plugin header and the readme all agree - the check
	 * the release pre-flight makes, kept here so a mismatch fails in CI first.
	 */
	public function test_the_version_agrees_everywhere() {
		$main   = file_get_contents( $this->dir . 'wp-print.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading the plugin's own source.
		$readme = file_get_contents( $this->dir . 'README.md' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading the plugin's own readme.

		preg_match( '/^\s*\*\s*Version:\s*([0-9.]+)/m', $main, $header );
		preg_match( '/^Stable tag:\s*([0-9.]+)/m', $readme, $stable );

		$this->assertSame( WP_PRINT_VERSION, $header[1], 'Header Version does not match the constant' );
		$this->assertSame( WP_PRINT_VERSION, $stable[1], 'Stable tag does not match the constant' );
	}
}
