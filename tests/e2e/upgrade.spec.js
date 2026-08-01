/**
 * The pre-3.0.0 migration, run the way a real site runs it.
 *
 * Activation does not fire when a plugin is merely updated -- a site that
 * updates from the Plugins screen never calls activate() -- so the migration
 * also hangs off admin_init. That is the hook every real upgrade actually goes
 * through, and loading an admin page in a browser is the only way to reach it.
 *
 * Every test then checks the far end rather than the row alone: a setting that
 * survived the migration but no longer reaches the page is a migration that
 * passed and a plugin that broke.
 *
 * The link settings are the interesting half. A 2.58.3 site had four of them --
 * a style out of four, and two link labels -- and 3.0.0 has one HTML template,
 * so the migration has to hand every one of those sites a template that renders
 * what it was already rendering. Only a browser can say whether it did.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	createPrintablePost,
	defaultOptions,
	deleteOptions,
	effectiveOptions,
	ensurePluginActive,
	getLegacyRows,
	getStoredOptions,
	getVersionRow,
	installLegacyRows,
	openSettings,
	printUrl,
	reactivatePlugin,
	runningVersions,
	setOptions,
	uniqueTitle,
	wpEval,
} = require( './helpers.js' );

test.describe( 'The pre-3.0.0 upgrade', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
	} );

	test.afterEach( async () => {
		deleteOptions();

		// This is the only file that ever switches the plugin off, and only for
		// as long as it takes to prove the activation hook runs. A failure part
		// way through would otherwise hand every later test a site with no
		// plugin, and the run would report a hundred symptoms of one cause.
		ensurePluginActive();
	} );

	test( 'the unprefixed rows are folded in, unslashed, deleted and stamped', async ( {
		page,
		requestUtils,
	} ) => {
		// The row as 2.58.3 wrote it: unprefixed, and slashed, because the admin
		// screen ran addslashes on the way in and every reader called
		// stripslashes on the way out. 3.0.0 stores clean and reads as-is, so an
		// un-migrated row would show its backslashes to every visitor.
		installLegacyRows( {
			post_text: "Print O\\'Brien\\'s post",
			page_text: "Print O\\'Brien\\'s page",
			print_style: 3,
			links: 0,
		} );

		// The fixture really is a pre-3.0.0 install: old rows present, new ones
		// absent. Without this the assertions below could be describing a site
		// that was already migrated.
		expect( getLegacyRows().options ).not.toBe( false );
		expect( getVersionRow() ).toBe( false );

		await openSettings( page );

		const legacy = getLegacyRows();

		// Both old rows gone, not merely superseded: a row left behind is a row
		// the next release has to keep thinking about.
		expect( legacy.options ).toBe( false );
		expect( legacy.version ).toBe( false );

		const stored = getStoredOptions();

		// The four link settings became one template, carrying this site's own
		// wording -- unslashed, or every visitor would be shown the backslashes --
		// and the text-only shape it chose, which is to say no glyph.
		expect( stored.print_html ).toContain( "Print O'Brien's post" );
		expect( stored.print_html ).not.toContain( '%PRINT_ICON%' );
		expect( stored.print_html ).not.toContain( '\\' );
		expect( stored.links ).toBe( 0 );

		// And the settings the template replaced are off the row, not merely
		// unread. A retired key left behind is one the next release has to keep
		// thinking about.
		expect( stored.print_style ).toBeUndefined();
		expect( stored.post_text ).toBeUndefined();
		expect( stored.page_text ).toBeUndefined();

		// One write, both markers, matching the code that is running.
		expect( getVersionRow() ).toEqual( runningVersions() );

		// Present is not alive. The migrated settings have to be the ones the
		// front end acts on.
		const post = await createPrintablePost( requestUtils );

		await page.goto( post.link );

		await expect(
			page.locator( '.entry-content' ).getByRole( 'link', { name: "Print O'Brien's post" } ),
		).toBeVisible();
		await expect( page.locator( '.entry-content svg.WP-PrintIcon' ) ).toHaveCount( 0 );
	} );

	test( 'a site on the shipped settings gets the shipped template', async ( {
		page,
		requestUtils,
	} ) => {
		// The commonest install of all: never touched a link setting in twenty
		// years. Its two labels said Post on posts and Page on pages, and one
		// template with %POST_TYPE% in it says both -- which is the whole reason
		// the four settings could become one.
		installLegacyRows( {
			post_text: 'Print This Post',
			page_text: 'Print This Page',
			print_style: 1,
		} );

		await openSettings( page );

		expect( getStoredOptions().print_html ).toBe( defaultOptions().print_html );

		const post = await createPrintablePost( requestUtils );
		const pageFixture = await requestUtils.createPage( {
			title: uniqueTitle( 'Printable page' ),
			content: '[print_link]',
			status: 'publish',
		} );

		await page.goto( post.link );
		await expect( page.locator( '.entry-content' ) ).toContainText( 'Print This Post' );
		await expect( page.locator( '.entry-content svg.WP-PrintIcon' ) ).toHaveCount( 1 );

		// The half no unit test can reach: is_page() and the post type only mean
		// anything inside a real request.
		await page.goto( pageFixture.link );
		await expect( page.locator( '.entry-content' ) ).toContainText( 'Print This Page' );
	} );

	test( 'an icon-only site stays icon-only', async ( { page, requestUtils } ) => {
		installLegacyRows( {
			post_text: 'Print This Post',
			page_text: 'Print This Page',
			print_style: 2,
		} );

		await openSettings( page );

		const post = await createPrintablePost( requestUtils );

		await page.goto( post.link );

		const link = page.locator( '.entry-content a[rel="nofollow"]' );

		// The glyph, no words, and an accessible name -- which is what style 2
		// rendered, down to the aria-label it needed because the glyph is hidden
		// from assistive technology.
		await expect( link ).toHaveCount( 1 );
		await expect( link.locator( 'svg.WP-PrintIcon' ) ).toHaveCount( 1 );
		await expect( link ).toHaveText( '' );
		await expect( link ).toHaveAttribute( 'aria-label', 'Print This Post' );
	} );

	test( 'a custom template built round the old icon URL keeps working', async ( {
		page,
		requestUtils,
	} ) => {
		// Every release up to 2.58.3 shipped two printer GIFs and let a site
		// choose between them, so a custom template of that era points an <img> at
		// %PRINT_ICON_URL%. There is one inline SVG now and no URL to point at, so
		// the whole <img> is replaced rather than left with a src of raw markup.
		installLegacyRows( {
			post_text: 'Print This Post',
			page_text: 'Print This Page',
			print_style: 4,
			print_icon: 'printer.gif',
			print_html:
				'<a class="my-print" href="%PRINT_URL%" title="Print it"><img src="%PRINT_ICON_URL%" alt="Print" /></a>',
		} );

		await openSettings( page );

		const stored = getStoredOptions();

		expect( stored.print_html ).toContain( '%PRINT_ICON%' );
		expect( stored.print_html ).not.toContain( '%PRINT_ICON_URL%' );
		expect( stored.print_html ).not.toContain( '<img' );

		// And the site's own template is otherwise untouched, wording and all: a
		// row already on the custom style holds the only thing 3.0.0 stores, so
		// rewriting it would be the migration overwriting what it exists to keep.
		expect( stored.print_html ).toContain( 'title="Print it"' );
		expect( stored.print_html ).toContain( 'class="my-print"' );

		// And the settings the template replaced are retired with it: a choice
		// between two GIFs that no longer exist is not a choice, and neither is a
		// style when there is only the template.
		expect( stored.print_icon ).toBeUndefined();
		expect( stored.print_style ).toBeUndefined();
		expect( stored.post_text ).toBeUndefined();
		expect( stored.page_text ).toBeUndefined();

		const post = await createPrintablePost( requestUtils );

		await page.goto( post.link );

		// The template still renders, and the glyph in it is now the inline SVG
		// rather than a broken image.
		const link = page.locator( '.entry-content a.my-print' );

		await expect( link ).toHaveCount( 1 );
		await expect( link.locator( 'svg.WP-PrintIcon' ) ).toHaveCount( 1 );
		await expect( link.locator( 'img' ) ).toHaveCount( 0 );
	} );

	test( 'reactivating migrates the same rows, seeds the defaults and leaves a working endpoint', async ( {
		page,
		requestUtils,
	} ) => {
		installLegacyRows( {
			post_text: 'Print it',
			print_style: 3,
		} );

		// The other entry point, and the one that does more: activation migrates,
		// then seeds the defaults with add_option(), then flushes the rewrite
		// rules. The order matters and the comment in activate_site() says why --
		// seeding first would make every default look like a value already in the
		// new row, and a 2.58.3 site's settings would be thrown away by the very
		// routine that exists to rescue them.
		reactivatePlugin();

		const legacy = getLegacyRows();

		expect( legacy.options ).toBe( false );
		expect( legacy.version ).toBe( false );

		const stored = getStoredOptions();

		expect( stored.print_html ).toBe( '<a href="%PRINT_URL%" rel="nofollow" title="Print it">Print it</a>' );

		// And the row holds the migrated keys and only those. add_option() is
		// called after the fold-in and finds the row already there, so it does
		// nothing -- the keys a 2.58.3 row never had are supplied by the merge
		// every reader does rather than written in. That is the order the
		// comment in activate_site() argues for, and reversing it is exactly how
		// a legacy site's settings would be thrown away by the routine that
		// exists to rescue them.
		expect( stored.thumbnail ).toBeUndefined();
		expect( effectiveOptions().thumbnail ).toBeDefined();

		// And the endpoint the flush was for still answers. A print URL that
		// 404s after an activation is the classic symptom of rules rebuilt
		// before the endpoint was registered.
		const post = await createPrintablePost( requestUtils );
		const response = await page.goto( printUrl( post.link ) );

		expect( response.status() ).toBe( 200 );
		await expect( page.locator( 'body.wp-print' ) ).toBeVisible();
	} );

	test( 'a second activation, and the admin load after it, change nothing', async ( { page } ) => {
		installLegacyRows( { post_text: 'Print it once', print_style: 3 } );

		reactivatePlugin();

		const once = { options: getStoredOptions(), versions: getVersionRow() };

		// Owners deactivate and reactivate to fix things, sometimes twice. The
		// second pass has to be a bystander: the rows it finds are the rows it
		// leaves, and so is the admin_init pass that follows a real update.
		reactivatePlugin();

		expect( getStoredOptions() ).toEqual( once.options );
		expect( getVersionRow() ).toEqual( once.versions );

		await openSettings( page );

		expect( getStoredOptions() ).toEqual( once.options );
		expect( getVersionRow() ).toEqual( once.versions );
	} );

	test( 'an install already on this version is left alone', async ( { page } ) => {
		// A row carrying a key the current code has retired, and markers saying
		// the upgrade has already run. maybe_upgrade() returning early is what
		// keeps every admin request from being an option write, and the proof it
		// returned early is that this deliberately stale row survives.
		const stale = { print_html: '<a href="%PRINT_URL%">%PRINT_TEXT%</a>', print_icon: 'printer.gif' };

		setOptions( stale );

		wpEval(
			`update_option( 'wp_print_version', array(
				'plugin' => WP_PRINT_VERSION,
				'db'     => WP_PRINT_DB_VERSION,
			) );
			echo '<<<done>>>';`,
		);

		await openSettings( page );

		expect( getStoredOptions() ).toEqual( stale );
	} );
} );
