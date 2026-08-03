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
class WP_Print_Source_Test extends WP_Print_TestCase {

	/**
	 * The uninstaller lifts WP_Site_Query's default cap of 100.
	 *
	 * Source-level on purpose: see the class docblock.
	 */
	public function test_uninstall_lifts_the_site_query_cap() {
		$this->assertMatchesRegularExpression( "/'number'\s*=>\s*0/", wp_print_test_code( 'uninstall.php' ), 'uninstall.php lifts the site query cap, or a network past the default is half-uninstalled.' );
	}

	/**
	 * The uninstaller asks only for the IDs the loop actually uses.
	 */
	public function test_uninstall_queries_ids_only() {
		$this->assertMatchesRegularExpression( "/'fields'\s*=>\s*'ids'/", wp_print_test_code( 'uninstall.php' ), 'uninstall.php asks for ids only, which is what makes the unlimited query affordable.' );
	}

	/**
	 * The deprecated wp_get_sites() is not called.
	 *
	 * It was deprecated in WordPress 4.6 and is capped at 100 sites, so a call
	 * uninstalls a larger network in part and still reports success.
	 */
	public function test_uninstall_does_not_call_wp_get_sites() {
		$this->assertStringNotContainsString( 'wp_get_sites', wp_print_test_code( 'uninstall.php' ), 'uninstall.php does not call the removed wp_get_sites(), which capped a network at 100 sites.' );
	}

	/**
	 * The blog is restored inside the loop: switch_to_blog() pushes onto
	 * a stack, so restoring once afterwards leaves it unwound by all but one entry.
	 */
	public function test_uninstall_restores_the_blog_inside_the_loop() {
		$this->assertMatchesRegularExpression(
			'/switch_to_blog\([^;]*;.*?restore_current_blog\(\);.*?\}/s',
			wp_print_test_code( 'uninstall.php' ),
			'The restore sits inside the loop; once after it leaves the stack unwound by one.'
		);
	}

	/**
	 * The same three properties hold for the activation fan-out.
	 */
	public function test_activation_fan_out_matches_uninstall() {
		$code = wp_print_test_code( 'includes/class-wp-print.php' );

		$this->assertMatchesRegularExpression( "/'number'\s*=>\s*0/", $code, 'Activation lifts the same site query cap uninstall does.' );
		$this->assertMatchesRegularExpression( "/'fields'\s*=>\s*'ids'/", $code, 'Activation asks for ids only, as uninstall does.' );
		$this->assertStringNotContainsString( 'wp_get_sites', $code, 'Nor does activation, so the two agree on how a network is walked.' );
		$this->assertMatchesRegularExpression(
			'/switch_to_blog\([^;]*;.*?restore_current_blog\(\);.*?\}/s',
			$code,
			'Activation restores inside its loop, as uninstall does.'
		);
	}

	/**
	 * The uninstaller refuses to run unless WordPress invoked it.
	 */
	public function test_uninstall_guards_on_the_constant() {
		$this->assertStringContainsString( 'WP_UNINSTALL_PLUGIN', wp_print_test_code( 'uninstall.php' ), 'uninstall.php refuses to run outside the uninstall context.' );
	}

	/**
	 * The uninstaller removes all four rows the plugin has ever owned: the two
	 * prefixed ones and the two the migration folds in, which are left behind on
	 * a site that was upgraded and then deleted without wp-admin being opened in
	 * between.
	 */
	public function test_uninstall_removes_every_option_the_plugin_owns() {
		$code = wp_print_test_code( 'uninstall.php' );

		foreach ( array( 'wp_print_options', 'wp_print_version', 'print_options', 'print_db_version' ) as $row ) {
			$this->assertStringContainsString( "delete_option( '{$row}' )", $code, 'uninstall.php never deletes the ' . $row . ' row it owns.' );
		}
	}

	/**
	 * Every shipped PHP file refuses to run when loaded directly.
	 *
	 * The index.php silence guard contains nothing to protect, and
	 * uninstall.php guards on WP_UNINSTALL_PLUGIN instead.
	 * Both are therefore skipped.
	 */
	public function test_every_file_refuses_direct_access() {
		foreach ( wp_print_test_php_files() as $relative ) {
			if ( in_array( $relative, array( 'index.php', 'includes/index.php', 'uninstall.php' ), true ) ) {
				continue;
			}

			$this->assertStringContainsString(
				'ABSPATH',
				wp_print_test_code( $relative ),
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
		// Built by concatenation on purpose. The release pre-flight greps the whole
		// repo for this symbol, and a test asserting its absence would otherwise be
		// counted as a call to it - the same false positive a docblock mentioning a
		// removed function produces.
		$loader = 'load_plugin' . '_textdomain'; // phpcs:ignore Generic.Strings.UnnecessaryStringConcat.Found -- Split deliberately; see above.

		foreach ( wp_print_test_php_files() as $relative ) {
			$this->assertStringNotContainsString(
				$loader,
				wp_print_test_code( $relative ),
				"$relative still loads a textdomain"
			);
		}
	}

	/**
	 * The plugin directory name is not guaranteed - a user may rename the folder -
	 * so paths and URLs are resolved from the main file rather than spelled out.
	 */
	public function test_no_hardcoded_plugin_directory() {
		foreach ( wp_print_test_php_files() as $relative ) {
			$code = wp_print_test_code( $relative );

			$this->assertStringNotContainsString( "'wp-print/", $code, "$relative hardcodes the plugin directory" );
			$this->assertStringNotContainsString( '/wp-print/', $code, "$relative hardcodes the plugin directory" );
		}
	}

	/**
	 * The print view carries no inline event handlers, in either template.
	 */
	public function test_templates_have_no_inline_handlers() {
		foreach ( array( 'includes/print-posts.php', 'includes/print-comments.php' ) as $template ) {
			$source = wp_print_test_code( $template );

			$this->assertStringNotContainsString( 'onclick=', $source, "$template still uses an inline onclick" );
			$this->assertStringNotContainsString( 'javascript:', $source, "$template still uses a javascript: URL" );
		}
	}
}
