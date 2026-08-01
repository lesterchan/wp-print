<?php
/**
 * The release invariants, asserted from the source and from the stored rows.
 *
 * These are the house rules every plugin in this family shares, and every one of
 * them has been broken by an ordinary edit at some point: a header field that
 * drifted out of the canonical order, a new directory shipped without its
 * silence guard, a version bumped in one file of three, a readme header line
 * that lost the two trailing spaces holding it apart from the next.
 *
 * They are the things a restructuring quietly breaks and nothing notices until a
 * release fails its pre-flight months later, so catching them here is far
 * cheaper than catching them there.
 *
 * @package WP-Print
 */

/**
 * @coversNothing
 */
class WP_Print_Metadata_Test extends WP_Print_TestCase {

	const VERSION = '3.0.0';

	/**
	 * The main plugin file.
	 *
	 * @return string
	 */
	protected function plugin_file() {
		return wp_print_test_read( 'wp-print.php' );
	}

	/**
	 * The readme.
	 *
	 * @return string
	 */
	protected function readme() {
		return wp_print_test_read( 'README.md' );
	}

	/**
	 * A field from the main plugin file's header docblock.
	 *
	 * @param string $field Field name.
	 * @return string
	 */
	protected function header_field( $field ) {
		$data = get_file_data( WP_PRINT_MAIN_FILE, array( $field => $field ) );

		return $data[ $field ];
	}

	/**
	 * A field from the readme's header block.
	 *
	 * @param string $field Field name.
	 * @return string
	 */
	protected function readme_field( $field ) {
		preg_match( '/^' . preg_quote( $field, '/' ) . ':\s*(.+?)\s*$/m', $this->readme(), $matches );

		return isset( $matches[1] ) ? $matches[1] : '';
	}

	/**
	 * Every directory in the repo that holds at least one PHP file.
	 *
	 * @return string[] Absolute paths, plugin root included.
	 */
	protected function php_directories() {
		$found = array();

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( untrailingslashit( WP_PRINT_DIR ), FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			$path = $file->getPathname();

			// vendor/ and node_modules/ are not ours and never ship, and
			// artifacts/ is whatever the last failing Playwright run left behind.
			if ( false !== strpos( $path, '/vendor/' )
				|| false !== strpos( $path, '/node_modules/' )
				|| false !== strpos( $path, '/artifacts/' ) ) {
				continue;
			}

			if ( 'php' === strtolower( $file->getExtension() ) ) {
				$found[ dirname( $path ) ] = true;
			}
		}

		return array_keys( $found );
	}

	/**
	 * Header lines need two trailing spaces to render as separate lines.
	 *
	 * Markdown joins consecutive lines into one paragraph unless each is ended
	 * with a hard line break, so a missing pair renders as
	 * "License: GPLv2 or later License URI: https://..." on wordpress.org. It is
	 * invisible in the source and in a diff, which is exactly why it wants a
	 * test. WP-Print shipped without it on the License line. The last line needs
	 * none, having nothing after it to run into.
	 */
	public function test_every_readme_header_line_keeps_its_line_break() {
		$header = substr( $this->readme(), 0, (int) strpos( $this->readme(), "\n\n" ) );
		$lines  = explode( "\n", $header );

		// The first line is the "# WP-Print" heading, not a header field.
		$fields = array_slice( $lines, 1 );
		$last   = array_pop( $fields );

		$this->assertCount( 8, $fields, 'The readme header is nine fields.' );

		foreach ( $fields as $line ) {
			$this->assertStringEndsWith(
				'  ',
				$line,
				"Needs two trailing spaces or it merges with the line below: {$line}"
			);
		}

		$this->assertStringStartsWith( 'License URI:', $last );
		$this->assertSame( rtrim( $last ), $last, 'The last header line takes no trailing spaces.' );
	}

	/**
	 * The four canonical URLs, which have drifted to http over twenty years.
	 */
	public function test_canonical_lesterchan_urls() {
		$this->assertSame(
			'https://lesterchan.net/portfolio/programming/php/',
			$this->header_field( 'Plugin URI' )
		);
		$this->assertSame( 'https://lesterchan.net', $this->header_field( 'Author URI' ) );
		$this->assertSame( 'https://lesterchan.net/site/donation/', $this->readme_field( 'Donate link' ) );
		$this->assertSame(
			'https://www.gnu.org/licenses/gpl-2.0.html',
			$this->header_field( 'License URI' )
		);
		$this->assertSame( 'https://www.gnu.org/licenses/gpl-2.0.html', $this->readme_field( 'License URI' ) );
	}

	/**
	 * One name, in every plugin.
	 *
	 * A second contributor has to be added on wordpress.org as well, so a name
	 * here that is not on the listing silently does nothing.
	 */
	public function test_contributors_is_gamerz_only() {
		$this->assertSame( 'GamerZ', $this->readme_field( 'Contributors' ) );
	}

	/**
	 * Exactly five, which is what the listing shows.
	 */
	public function test_the_readme_lists_five_tags() {
		$this->assertCount( 5, explode( ',', $this->readme_field( 'Tags' ) ) );
	}

	public function test_text_domain_is_the_plugin_slug() {
		$this->assertSame( 'wp-print', $this->header_field( 'Text Domain' ) );
		$this->assertSame( '/languages', $this->header_field( 'Domain Path' ) );
		$this->assertSame( 'wp-print', WP_PRINT_SLUG );
	}

	/**
	 * The header, the readme and the constant agree.
	 *
	 * This is the check the release pre-flight makes, kept here so a mismatch
	 * fails in CI first.
	 */
	public function test_version_matches_everywhere() {
		$this->assertSame( self::VERSION, $this->header_field( 'Version' ) );
		$this->assertSame( self::VERSION, $this->readme_field( 'Stable tag' ) );
		$this->assertSame( self::VERSION, WP_PRINT_VERSION );
		$this->assertStringContainsString( '### ' . self::VERSION . "\n", $this->readme() );
	}

	public function test_requires_headers_match_readme() {
		$this->assertSame( '6.8', $this->header_field( 'Requires at least' ) );
		$this->assertSame( '8.2', $this->header_field( 'Requires PHP' ) );
		$this->assertSame( '6.8', $this->readme_field( 'Requires at least' ) );
		$this->assertSame( '8.2', $this->readme_field( 'Requires PHP' ) );
	}

	/**
	 * The order is neither alphabetical nor intuitive -- Requires at least and
	 * Requires PHP sit before Author -- so it is copied, never composed.
	 */
	public function test_the_plugin_header_fields_are_in_the_canonical_order() {
		$expected = array(
			'Plugin Name',
			'Plugin URI',
			'Description',
			'Version',
			'Requires at least',
			'Requires PHP',
			'Author',
			'Author URI',
			'License',
			'License URI',
			'Text Domain',
			'Domain Path',
		);

		preg_match( '#^<\?php\s*/\*\*(.+?)\*/#s', $this->plugin_file(), $matches );
		$this->assertNotEmpty( $matches, 'The plugin file must open with a docblock header.' );

		preg_match_all( '/^\s*\*\s*([A-Z][A-Za-z ]*?):\s/m', $matches[1], $fields );

		$this->assertSame( $expected, $fields[1] );
	}

	/**
	 * The licence block is the "or later" variant, matching the header above it.
	 *
	 * Five plugins in this family carry a version-2-only block directly beneath a
	 * "GPLv2 or later" header and a GPL-2.0-or-later composer.json, which is a
	 * self-contradicting licence statement. WP-Print is not one of them, and this
	 * is here so it does not become one.
	 */
	public function test_the_licence_block_is_the_or_later_variant() {
		$this->assertSame( 'GPLv2 or later', $this->header_field( 'License' ) );
		$this->assertStringContainsString(
			'either version 2 of the License, or',
			$this->plugin_file()
		);
		$this->assertStringContainsString( '(at your option) any later version.', $this->plugin_file() );
	}

	/**
	 * The second-level headings are a closed set in a fixed order.
	 *
	 * Third-level ones are not: Features, the usage subsections and every
	 * changelog version live below these.
	 */
	public function test_readme_sections_are_the_canonical_set() {
		preg_match_all( '/^## (.+?)\s*$/m', $this->readme(), $sections );

		$this->assertSame(
			array(
				'Description',
				'Usage',
				'Frequently Asked Questions',
				'Screenshots',
				'Changelog',
				'Upgrade Notice',
			),
			$sections[1]
		);
	}

	/**
	 * The donations paragraph is the family's exact wording, and the last h3 of
	 * the description.
	 */
	public function test_the_donations_paragraph_is_the_family_wording() {
		$this->assertStringContainsString(
			'I spent most of my free time creating, updating, maintaining and supporting'
				. ' these plugins, if you really love my plugins and could spare me a couple of'
				. ' bucks, I will really appreciate it. If not feel free to use it without any'
				. ' obligations.',
			$this->readme()
		);
		$this->assertStringNotContainsString( 'as my school allowance', $this->readme() );
	}

	/**
	 * Five prefixes, and nothing else.
	 *
	 * The listing on wordpress.org renders the changelog verbatim, so a stray
	 * "REMOVED:" or a lowercase "New:" is visible to every reader of it.
	 */
	public function test_changelog_prefixes_are_canonical() {
		$readme    = $this->readme();
		$changelog = substr( $readme, (int) strpos( $readme, '## Changelog' ) );
		$changelog = substr( $changelog, 0, (int) strpos( $changelog, "\n## Upgrade Notice" ) );

		preg_match_all( '/^\* (.+?):/m', $changelog, $bullets );

		$this->assertNotEmpty( $bullets[1], 'The changelog must carry bullets.' );

		foreach ( $bullets[1] as $prefix ) {
			$this->assertContains(
				$prefix . ':',
				array( 'BREAKING:', 'NEW:', 'CHANGED:', 'FIXED:', 'NOTE:' ),
				"'{$prefix}:' is not one of the five allowed changelog prefixes."
			);
		}
	}

	/**
	 * Bare versions: "### 3.0.0", never "### Version 3.0.0".
	 */
	public function test_every_changelog_heading_is_a_bare_version() {
		$this->assertSame( 0, preg_match( '/^### Version /m', $this->readme() ) );
	}

	/**
	 * The raised floors are the break most likely to bite, so they are named in
	 * the upgrade notice rather than left to the changelog.
	 */
	public function test_the_upgrade_notice_names_the_raised_floors() {
		$notice = substr( $this->readme(), (int) strpos( $this->readme(), '## Upgrade Notice' ) );

		$this->assertStringContainsString( 'WordPress 6.8', $notice );
		$this->assertStringContainsString( 'PHP 8.2', $notice );
	}

	/**
	 * Nothing the plugin registers depends on jQuery, and no script file
	 * mentions it.
	 */
	public function test_no_jquery_is_enqueued() {
		WP_Print_Template::register_assets();
		WP_Print_Admin::enqueue( 'settings_page_' . WP_Print_Admin::PAGE );

		foreach ( wp_scripts()->registered as $handle => $script ) {
			if ( 0 !== strpos( $handle, 'wp-print' ) ) {
				continue;
			}

			$this->assertNotContains( 'jquery', (array) $script->deps, "{$handle} depends on jQuery." );
		}

		$this->assertStringNotContainsStringIgnoringCase( 'jquery', wp_print_test_source_code() );

		foreach ( (array) glob( WP_PRINT_DIR . 'js/*.js' ) as $script ) {
			$this->assertStringNotContainsStringIgnoringCase(
				'jquery',
				(string) file_get_contents( $script ),
				basename( $script ) . ' still refers to jQuery.'
			);
		}
	}

	public function test_every_directory_has_an_index_php() {
		foreach ( $this->php_directories() as $directory ) {
			$this->assertFileExists(
				$directory . '/index.php',
				"{$directory} ships PHP and so needs an index.php silence guard."
			);
		}
	}

	/**
	 * The silence guards use the docblock form, which phpcbf can keep tidy.
	 */
	public function test_the_guards_use_the_docblock_form() {
		foreach ( $this->php_directories() as $directory ) {
			$guard = (string) file_get_contents( $directory . '/index.php' );

			$this->assertStringContainsString( '/**', $guard, "{$directory}/index.php must use the docblock form." );
			$this->assertStringContainsString( 'Silence is golden.', $guard );
		}
	}

	/**
	 * Deleting the plugin leaves nothing behind.
	 *
	 * The assertion is deliberately a LIKE over wp_options rather than a list of
	 * delete_option() checks: a row added later and forgotten in uninstall.php is
	 * exactly the failure this is here to catch. The multisite config runs the
	 * same test through the get_sites() branch.
	 */
	public function test_uninstall_removes_every_option_row() {
		$this->set_options();
		WP_Print_Options::maybe_upgrade();
		update_option( WP_Print_Options::LEGACY_OPTION, array( 'post_text' => 'Legacy' ) );
		update_option( 'an_unrelated_option', 'keep me' );

		$this->assertNotEmpty(
			$this->stored_option_names(),
			'There should be rows to remove before uninstall runs.'
		);

		$this->run_uninstall();

		wp_cache_flush();

		$this->assertSame(
			array(),
			$this->stored_option_names(),
			'uninstall.php must remove every row the plugin has ever owned.'
		);
		$this->assertSame( 'keep me', get_option( 'an_unrelated_option' ), 'And nothing else.' );
	}

	/**
	 * The upgrade markers live in their own row, holding those two keys and no
	 * others. Anything else in here means a marker has drifted back into the
	 * settings array, which is the bug this shape exists to make impossible.
	 */
	public function test_version_row_holds_exactly_plugin_and_db() {
		WP_Print_Options::maybe_upgrade();

		$markers = get_option( WP_Print_Options::VERSION );

		$this->assertIsArray( $markers, 'wp_print_version must be an array.' );

		$keys = array_keys( $markers );
		sort( $keys );

		$this->assertSame( array( 'db', 'plugin' ), $keys );
		$this->assertSame( WP_PRINT_VERSION, $markers['plugin'] );
		$this->assertSame( WP_PRINT_DB_VERSION, $markers['db'] );
	}

	/**
	 * The sanitizer never stores a version marker.
	 *
	 * A sanitize_callback is a function from what the form posted to what gets
	 * stored, and the settings form never posts a marker. This fails the moment
	 * someone moves one back into the settings array, which is the wp-useronline
	 * bug the separate row exists to make impossible.
	 */
	public function test_settings_sanitizer_never_stores_version_markers() {
		$clean = WP_Print_Settings::sanitize(
			array(
				'print_html' => '<a href="%PRINT_URL%">Print This</a>',
				'comments'   => 1,
				'version'    => '9.9.9',
				'db_version' => '99',
				'versions'   => array( 'plugin' => '9.9.9' ),
			)
		);

		foreach ( array( 'version', 'db_version', 'versions' ) as $key ) {
			$this->assertArrayNotHasKey( $key, $clean, "The settings row must never carry a {$key} key." );
		}
	}

	/**
	 * No plugin in this family ships a second, mirrored stylesheet: the front end
	 * uses CSS logical properties instead, so one sheet serves both directions.
	 * WP-Print carried one of the last two.
	 */
	public function test_no_rtl_stylesheet_is_registered() {
		$this->assertSame( array(), (array) glob( WP_PRINT_DIR . '*-rtl.css' ) );
		$this->assertSame( array(), (array) glob( WP_PRINT_DIR . 'css/*-rtl.css' ) );

		$this->assertStringNotContainsString(
			'wp_style_add_data',
			wp_print_test_source_code(),
			"No plugin registers 'rtl' style data."
		);

		WP_Print_Template::register_assets();

		foreach ( wp_styles()->registered as $handle => $style ) {
			if ( 0 !== strpos( $handle, 'wp-print' ) ) {
				continue;
			}

			$this->assertStringNotContainsString( '-rtl.css', (string) $style->src, "{$handle} loads a mirrored sheet." );
			$this->assertArrayNotHasKey( 'rtl', (array) $style->extra, "{$handle} registers rtl style data." );
		}
	}

	/**
	 * No raster image ships, in any directory.
	 */
	public function test_no_raster_images_ship() {
		// The shipped directories, named rather than walked: node_modules/ is a
		// direct child of the plugin root when the dev tooling is installed, and
		// a glob over */ would rake through every dependency's artwork.
		$directories = array( '', 'bin/', 'css/', 'images/', 'includes/', 'js/' );
		$found       = array();

		foreach ( $directories as $directory ) {
			foreach ( array( 'gif', 'png', 'jpg', 'jpeg', 'bmp', 'ico' ) as $extension ) {
				$found = array_merge( $found, (array) glob( WP_PRINT_DIR . $directory . '*.' . $extension ) );
			}
		}

		$this->assertSame( array(), array_filter( $found ), 'Raster images are replaced by inline SVG.' );
		$this->assertDirectoryDoesNotExist( WP_PRINT_DIR . 'images' );
	}

	/**
	 * The catalogue is built by translate.wordpress.org, and Travis has been dead
	 * for these repos for years.
	 */
	public function test_no_abandoned_build_or_translation_artefacts_ship() {
		$this->assertFileDoesNotExist( WP_PRINT_DIR . '.travis.yml' );
		$this->assertFileDoesNotExist( WP_PRINT_DIR . '.wp-env.override.json' );
		$this->assertDirectoryDoesNotExist( WP_PRINT_DIR . 'languages' );

		foreach ( array( 'pot', 'po', 'mo' ) as $extension ) {
			$this->assertSame(
				array(),
				(array) glob( WP_PRINT_DIR . '*.' . $extension ),
				"No .{$extension} files: translate.wordpress.org builds the catalogue."
			);
		}
	}

	/**
	 * Only the three files the standard allows sit at the plugin root.
	 */
	public function test_the_plugin_root_holds_no_loose_files() {
		$allowed = array( 'wp-print.php', 'index.php', 'uninstall.php' );

		foreach ( (array) glob( WP_PRINT_DIR . '*.php' ) as $file ) {
			$this->assertContains( basename( $file ), $allowed, basename( $file ) . ' belongs in includes/.' );
		}

		$this->assertSame( array(), (array) glob( WP_PRINT_DIR . '*.css' ), 'Stylesheets live in css/.' );

		/*
		 * Tool configuration is not a script the plugin ships.
		 *
		 * The rule is about source scripts loose in the root, and playwright.config.js
		 * has to be there -- Playwright resolves testDir and every other path relative
		 * to it. bin/verify.py draws the same line, allowing *.config.js and
		 * *.config.mjs at the root and nothing else.
		 */
		$scripts = array_values(
			array_filter(
				(array) glob( WP_PRINT_DIR . '*.js' ),
				static function ( $file ) {
					return ! preg_match( '/\.config\.js$/', basename( $file ) );
				}
			)
		);

		$this->assertSame( array(), $scripts, 'Scripts live in js/. Only *.config.js may sit at the root.' );
	}

	/**
	 * Every fired hook carries the plugin's prefix.
	 *
	 * The query var does not, and must not: /a-post/print/ is the public URL of
	 * every printable page and predates the rule by twenty years.
	 */
	public function test_every_hook_the_plugin_fires_is_prefixed() {
		preg_match_all(
			"/(?:apply_filters|do_action)(?:_ref_array)?\(\s*'([a-z0-9_]+)'/",
			wp_print_test_source_code(),
			$hooks
		);

		$this->assertNotEmpty( $hooks[1], 'The plugin fires at least one hook of its own.' );

		$consumed = array( 'the_content', 'comment_text' );

		foreach ( $hooks[1] as $hook ) {
			if ( in_array( $hook, $consumed, true ) ) {
				continue;
			}

			$this->assertStringStartsWith( 'wp_print_', $hook, "{$hook} is not prefixed." );
		}
	}

	/**
	 * The GPL text ships alongside the header that points at it.
	 */
	public function test_the_gpl_licence_is_shipped() {
		$licence = wp_print_test_read( 'LICENSE' );

		$this->assertStringContainsString( 'GNU GENERAL PUBLIC LICENSE', $licence );
		$this->assertStringContainsString( 'Version 2, June 1991', $licence );
	}
}
