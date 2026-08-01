/**
 * Shared steps for the WP-Print end-to-end suite.
 *
 * WP-Print is two surfaces that share one option row. On an ordinary page it
 * prints a link; at /print/ it takes the request over entirely and renders a
 * standalone document that never calls wp_head(). Neither is reachable from
 * PHPUnit in the shape a reader meets it -- one is markup a shortcode dropped
 * into somebody else's content, the other is a whole HTML document with its own
 * <head> -- so both are driven here through a browser.
 *
 * Fixtures come from two places. Posts and comments go through requestUtils,
 * because they are things a person creates. The option row goes in through
 * WP-CLI: the settings screen has its own file, and putting every fixture
 * through it would make a form regression look like a rendering regression --
 * and for the escaping tests the whole point is a row the sanitizer never saw.
 */

const { execFileSync } = require( 'child_process' );
const path = require( 'path' );

const { expect } = require( '@wordpress/e2e-test-utils-playwright' );

/** The plugin root, which is where wp-env reads .wp-env.json from. */
const PLUGIN_ROOT = path.join( __dirname, '../..' );

const SETTINGS_URL = '/wp-admin/options-general.php?page=wp-print';
const PLUGINS_URL = '/wp-admin/plugins.php';

/** The option row every setting lives in. */
const OPTION = 'wp_print_options';

/** The option row holding the two upgrade markers. */
const VERSION_OPTION = 'wp_print_version';

/** The settings row every release up to 2.58.3 used. */
const LEGACY_OPTION = 'print_options';

/** The schema counter every release up to 2.58.3 used. */
const LEGACY_VERSION_OPTION = 'print_db_version';

/** The two tabs the settings screen is split across. */
const TAB = {
	settings: 'settings',
	templates: 'templates',
};

/**
 * The URL of one tab of the settings screen.
 *
 * @param {string} tab Tab slug.
 * @return {string} The admin URL for that tab.
 */
function tabUrl( tab ) {
	return `${ SETTINGS_URL }&tab=${ tab }`;
}

/**
 * Run PHP inside the tests environment and hand back what it printed.
 *
 * The code is base64'd rather than passed as itself: a disclaimer holding
 * quotes, angle brackets and a script tag is exactly the sort of string that
 * arrives at the other end subtly different, and a fixture that is not the
 * payload byte for byte proves nothing about escaping it.
 *
 * @param {string} code PHP to evaluate, without an opening tag.
 * @return {string} Whatever the code echoed between its markers.
 */
function wpEval( code ) {
	const encoded = Buffer.from( code, 'utf8' ).toString( 'base64' );

	const output = execFileSync(
		'npx',
		[
			'--yes',
			'@wordpress/env',
			'run',
			'tests-cli',
			'wp',
			'eval',
			`eval( base64_decode( '${ encoded }' ) );`,
		],
		{ cwd: PLUGIN_ROOT, encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'pipe' ] },
	);

	// wp-env prints its own progress around the command's output, so the code
	// wraps what it wants to return in markers rather than the caller trying to
	// tell the two apart by position.
	const matched = output.match( /<<<([\s\S]*?)>>>/ );

	return matched ? matched[ 1 ] : '';
}

/**
 * Write the settings row exactly as given, without going near the sanitizer.
 *
 * Only the keys handed in are stored. WP_Print_Options::get() merges the
 * defaults on read -- which is what lets a row written before `thumbnail`
 * existed pick the key up -- so a partial row is a shape real installs have, and
 * an unsanitised one is the shape a compromised install has.
 *
 * @param {Object} options Option keys to store.
 * @return {void}
 */
function setOptions( options ) {
	const data = Buffer.from( JSON.stringify( options ), 'utf8' ).toString( 'base64' );

	wpEval(
		`update_option( '${ OPTION }', json_decode( base64_decode( '${ data }' ), true ) );
		echo '<<<done>>>';`,
	);
}

/**
 * The settings row as the database holds it, before any defaults are merged in.
 *
 * @return {Object|false} The stored array, or false when there is no row.
 */
function getStoredOptions() {
	return JSON.parse( wpEval( `echo '<<<' . wp_json_encode( get_option( '${ OPTION }' ) ) . '>>>';` ) );
}

/**
 * Remove the settings row entirely, which is the state a fresh install is in.
 *
 * @return {void}
 */
function deleteOptions() {
	wpEval( `delete_option( '${ OPTION }' ); echo '<<<done>>>';` );
}

/**
 * The options as the plugin reads them, with the defaults merged in.
 *
 * Not the same thing as the stored row, and the difference matters more here
 * than it looks. register_setting() is passed a 'default', which installs a
 * default_option_wp_print_options filter -- so on an admin request get_option()
 * answers with the defaults when there is no row, and update_option() declines
 * to write a value identical to what it already reads. A save whose result
 * happens to equal the defaults therefore leaves no row at all, which is
 * indistinguishable from a fresh install to every reader in the plugin and
 * completely different to a test looking at the raw row.
 *
 * So: assert on the stored row when the question is "was it written", and on
 * this when the question is "what will the plugin use".
 *
 * @return {Object} The merged option array.
 */
function effectiveOptions() {
	return JSON.parse( wpEval( "echo '<<<' . wp_json_encode( WP_Print_Options::get() ) . '>>>';" ) );
}

/**
 * The defaults the running code would fall back to.
 *
 * Asked of the install rather than transcribed, because two of them -- the
 * disclaimer's year and the site name -- are only knowable at run time.
 *
 * @return {Object} The default option array.
 */
function defaultOptions() {
	return JSON.parse(
		wpEval( `echo '<<<' . wp_json_encode( WP_Print_Options::get_defaults() ) . '>>>';` ),
	);
}

/**
 * The upgrade markers, as the database holds them.
 *
 * @return {Object|false} The stored array, or false when there is no row.
 */
function getVersionRow() {
	return JSON.parse(
		wpEval( `echo '<<<' . wp_json_encode( get_option( '${ VERSION_OPTION }' ) ) . '>>>';` ),
	);
}

/**
 * The version numbers the running code expects to find stamped.
 *
 * @return {{plugin: string, db: string}} The two markers.
 */
function runningVersions() {
	return JSON.parse(
		wpEval(
			`echo '<<<' . wp_json_encode( array(
				'plugin' => WP_PRINT_VERSION,
				'db'     => WP_PRINT_DB_VERSION,
			) ) . '>>>';`,
		),
	);
}

/**
 * Put the install back into the shape a pre-3.0.0 site is in.
 *
 * The prefixed rows go away entirely and the two unprefixed ones take their
 * place, because that is what the migration has to meet: a row called
 * `print_options`, a bare version string in `print_db_version`, and nothing
 * else.
 *
 * @param {Object} legacy  The legacy settings row, exactly as given.
 * @param {string} version The legacy schema counter.
 * @return {void}
 */
function installLegacyRows( legacy, version = '2.58.3' ) {
	const data = Buffer.from( JSON.stringify( legacy ), 'utf8' ).toString( 'base64' );

	wpEval(
		`delete_option( '${ OPTION }' );
		delete_option( '${ VERSION_OPTION }' );
		update_option( '${ LEGACY_OPTION }', json_decode( base64_decode( '${ data }' ), true ) );
		update_option( '${ LEGACY_VERSION_OPTION }', '${ version }' );
		echo '<<<done>>>';`,
	);
}

/**
 * Whether either pre-3.0.0 row is still there.
 *
 * @return {{options: *, version: *}} Each false once the migration has run.
 */
function getLegacyRows() {
	return JSON.parse(
		wpEval(
			`echo '<<<' . wp_json_encode( array(
				'options' => get_option( '${ LEGACY_OPTION }' ),
				'version' => get_option( '${ LEGACY_VERSION_OPTION }' ),
			) ) . '>>>';`,
		),
	);
}

/**
 * Set one of the options the tests-only mu-plugin reads its answers from.
 *
 * @param {string} name  Option name.
 * @param {*}      value Anything JSON can carry.
 * @return {void}
 */
function setFixtureOption( name, value ) {
	const data = Buffer.from( JSON.stringify( value ), 'utf8' ).toString( 'base64' );

	wpEval(
		`update_option( '${ name }', json_decode( base64_decode( '${ data }' ), true ), false );
		echo '<<<done>>>';`,
	);
}

/**
 * Read one of those options back, and forget it.
 *
 * Read-and-forget rather than read, because the mu-plugin uses these to record
 * that a hook fired: a value left behind would let the next test see the last
 * one's evidence.
 *
 * @param {string} name Option name.
 * @return {*} The stored value, or false when the hook never fired.
 */
function takeFixtureOption( name ) {
	const value = JSON.parse(
		wpEval( `echo '<<<' . wp_json_encode( get_option( '${ name }' ) ) . '>>>';` ),
	);

	wpEval( `delete_option( '${ name }' ); echo '<<<done>>>';` );

	return value;
}

/**
 * Stop one of the fixture filters answering.
 *
 * @param {string} name Option name.
 * @return {void}
 */
function clearFixtureOption( name ) {
	wpEval( `delete_option( '${ name }' ); echo '<<<done>>>';` );
}

/**
 * Deactivate and reactivate the plugin, which is the path that fires activate().
 *
 * Genuinely a different entry point from the admin_init one: updating through
 * the Plugins screen never fires the activation hook, and activation is the only
 * path that seeds the defaults and flushes the rewrite rules.
 *
 * @return {void}
 */
function reactivatePlugin() {
	wpEval(
		`require_once ABSPATH . 'wp-admin/includes/plugin.php';
		deactivate_plugins( 'wp-print/wp-print.php' );
		activate_plugin( 'wp-print/wp-print.php' );
		echo '<<<done>>>';`,
	);
}

/**
 * Put the plugin back on, whatever a test left behind.
 *
 * @return {void}
 */
function ensurePluginActive() {
	wpEval(
		`require_once ABSPATH . 'wp-admin/includes/plugin.php';
		if ( ! is_plugin_active( 'wp-print/wp-print.php' ) ) {
			activate_plugin( 'wp-print/wp-print.php' );
		}
		echo '<<<done>>>';`,
	);
}

/**
 * Put a copy of one of the three overridable files into the active theme.
 *
 * The documented way to restyle the print view is to drop print-posts.php,
 * print-comments.php or print-css.css into the theme, and the lookup order --
 * child theme, parent theme, then the plugin -- is a promise about upgrades that
 * only a real file on disk can test.
 *
 * @param {string} file     Theme-side file name.
 * @param {string} contents What to write into it.
 * @return {void}
 */
function writeThemeOverride( file, contents ) {
	const data = Buffer.from( contents, 'utf8' ).toString( 'base64' );

	wpEval(
		`file_put_contents(
			get_stylesheet_directory() . '/${ file }',
			base64_decode( '${ data }' )
		);
		echo '<<<done>>>';`,
	);
}

/**
 * Take a theme override off disk again.
 *
 * The theme directory outlives the test, and an override left behind would
 * replace the print view for every test after it.
 *
 * @param {string} file Theme-side file name.
 * @return {void}
 */
function removeThemeOverride( file ) {
	wpEval(
		`$path = get_stylesheet_directory() . '/${ file }';
		if ( file_exists( $path ) ) {
			unlink( $path );
		}
		echo '<<<done>>>';`,
	);
}

/**
 * Change the site's permalink structure and rebuild the rules.
 *
 * The print view is a rewrite endpoint under pretty permalinks and a query var
 * without them, and those are two different code paths through
 * WP_Print_Link::url(). Flushing is not optional: an endpoint registered against
 * rules that were never rebuilt 404s.
 *
 * @param {string} structure A permalink structure, or '' for plain.
 * @return {void}
 */
function setPermalinkStructure( structure ) {
	wpEval(
		`global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '${ structure }' );
		WP_Print::add_endpoint();
		$wp_rewrite->flush_rules( true );
		echo '<<<' . get_option( 'permalink_structure' ) . '>>>';`,
	);
}

/**
 * The site's current permalink structure.
 *
 * @return {string} The structure, or '' when permalinks are plain.
 */
function permalinkStructure() {
	return wpEval( "echo '<<<' . get_option( 'permalink_structure' ) . '>>>';" );
}

/**
 * Give a post a featured image, and hand back the attachment's id.
 *
 * Built inside the install from a file WordPress already ships rather than
 * uploaded from here, so the suite needs no binary fixture of its own -- §11
 * keeps raster images out of these repositories, and a PNG committed under
 * tests/ would be the only one in the collection.
 *
 * @param {number} postId Post to attach it to.
 * @return {number} The attachment id, for cleaning up afterwards.
 */
function attachThumbnail( postId ) {
	return parseInt(
		wpEval(
			`require_once ABSPATH . 'wp-admin/includes/image.php';
			$upload = wp_upload_dir();
			$path   = trailingslashit( $upload['path'] ) . 'wp-print-e2e-thumbnail.png';
			copy( ABSPATH . 'wp-includes/images/w-logo-blue.png', $path );
			$id = wp_insert_attachment(
				array(
					'post_mime_type' => 'image/png',
					'post_title'     => 'WP-Print E2E thumbnail',
					'post_status'    => 'inherit',
				),
				$path,
				${ postId }
			);
			wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $path ) );
			set_post_thumbnail( ${ postId }, $id );
			echo '<<<' . (int) $id . '>>>';`,
		),
		10,
	);
}

/**
 * Delete an attachment and the file behind it.
 *
 * @param {number} attachmentId Attachment to remove.
 * @return {void}
 */
function deleteAttachment( attachmentId ) {
	wpEval( `wp_delete_attachment( ${ attachmentId }, true ); echo '<<<done>>>';` );
}

/**
 * Publish a post carrying the print-link shortcode.
 *
 * @param {Object} requestUtils The e2e-test-utils request helper.
 * @param {Object} overrides    Anything to override on the post.
 * @return {Promise<Object>} The created post.
 */
function createPrintablePost( requestUtils, overrides = {} ) {
	return requestUtils.createPost( {
		title: uniqueTitle( 'Printable' ),
		content: 'The body of the post.\n\n[print_link]',
		status: 'publish',
		...overrides,
	} );
}

/**
 * The print view's URL for a post.
 *
 * Built from the link WordPress handed back rather than from a structure
 * assembled here, because the two forms differ and only one of them is right for
 * the permalink setting in force.
 *
 * @param {string} postLink The post's permalink.
 * @return {string} The printable URL.
 */
function printUrl( postLink ) {
	return postLink.includes( '?' ) ? `${ postLink }&print=1` : `${ postLink }print/`;
}

/**
 * Open one tab of the settings screen.
 *
 * Defaults to the Settings tab, which is what a bare visit to the screen lands
 * on. The Templates tab is a query argument on the same page, not a page of its
 * own -- one option row, one registered setting, two tabs over it.
 *
 * @param {import('@playwright/test').Page} page Page under test.
 * @param {string}                          tab  Tab slug.
 * @return {Promise<void>} Resolves once the screen is up.
 */
async function openSettings( page, tab = TAB.settings ) {
	await page.goto( tab === TAB.settings ? SETTINGS_URL : tabUrl( tab ) );

	// The h1 specifically: a bare role query would also match the section
	// headings inside the form, and strict mode refuses to guess which was meant.
	await expect( page.getByRole( 'heading', { level: 1, name: 'Print Settings' } ) ).toBeVisible();

	// And the tab that was asked for is the one drawn, or every assertion after
	// this is about the other tab's fields being absent for the wrong reason.
	await expect( page.locator( '.nav-tab-active' ) ).toHaveAttribute( 'href', new RegExp( `tab=${ tab }` ) );
}

/**
 * Save the settings form and wait for WordPress to confirm it.
 *
 * @param {import('@playwright/test').Page} page Page under test.
 * @return {Promise<void>} Resolves once "Settings saved." is on screen.
 */
async function saveSettings( page ) {
	await page.getByRole( 'button', { name: 'Save Changes' } ).click();

	// The notice rather than the redirect: options.php sends the browser back
	// whether or not anything was written, so arriving here again says nothing.
	await expect( page.locator( '#setting-error-settings_updated' ) ).toContainText( 'Settings saved.' );
}

/**
 * The id a settings field renders with.
 *
 * @param {string} key Option key.
 * @return {string} A CSS id selector.
 */
function field( key ) {
	return `#wp-print-${ key }`;
}

/**
 * A title no earlier run can have used.
 *
 * @param {string} base What the title should say.
 * @return {string} That, plus enough to tell this run from the last.
 */
function uniqueTitle( base ) {
	return `${ base } ${ Date.now().toString( 36 ) }`;
}

module.exports = {
	LEGACY_OPTION,
	LEGACY_VERSION_OPTION,
	OPTION,
	PLUGINS_URL,
	SETTINGS_URL,
	TAB,
	VERSION_OPTION,
	attachThumbnail,
	clearFixtureOption,
	createPrintablePost,
	defaultOptions,
	deleteAttachment,
	deleteOptions,
	effectiveOptions,
	ensurePluginActive,
	field,
	getLegacyRows,
	getStoredOptions,
	getVersionRow,
	installLegacyRows,
	openSettings,
	permalinkStructure,
	printUrl,
	reactivatePlugin,
	removeThemeOverride,
	runningVersions,
	saveSettings,
	setFixtureOption,
	setOptions,
	setPermalinkStructure,
	tabUrl,
	takeFixtureOption,
	uniqueTitle,
	wpEval,
	writeThemeOverride,
};
