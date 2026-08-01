/**
 * Settings -> WP-Print.
 *
 * This screen replaced a hand-rolled form that posted to itself and did its own
 * nonce and capability handling, so every field here is new plumbing over old
 * storage. That is exactly the change a unit test on the sanitiser cannot vouch
 * for, because the sanitiser is only reached if the form posts what it expects.
 *
 * It is now two tabs -- Settings and Templates -- over one option row, and the
 * tabs are what make the browser half indispensable. The Settings API hands the
 * sanitize callback only the fields the submitting form posted, so each tab's
 * save is a partial write, and a partial write that goes wrong takes the other
 * tab's values with it. Nothing but a real save through a real form proves it
 * does not.
 *
 * So each test saves through the form, reads the row back, and where the setting
 * is visible to a reader, goes and looks at the page. And the two Restore Default
 * buttons are pure JavaScript, which exists nowhere else at all.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	PLUGINS_URL,
	SETTINGS_URL,
	TAB,
	clearFixtureOption,
	createPrintablePost,
	defaultOptions,
	deleteOptions,
	effectiveOptions,
	field,
	getStoredOptions,
	openSettings,
	printUrl,
	saveSettings,
	setFixtureOption,
	setOptions,
	tabUrl,
} = require( './helpers.js' );

/** The shipped defaults, which several tests compare the screen against. */
let defaults;

test.describe( 'The settings screen', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();

		defaults = defaultOptions();
	} );

	test.beforeEach( async () => {
		deleteOptions();
	} );

	test.afterEach( async () => {
		// The row decides what every page on the site renders, so no test leaves
		// its own settings behind.
		deleteOptions();
	} );

	test( 'the fixture really is a fresh install, and both tabs show the shipped defaults', async ( {
		page,
	} ) => {
		// The precondition the rest of the file leans on. With a row already in
		// place, "the field saved" could be true before the test did anything.
		expect( getStoredOptions() ).toBe( false );

		await openSettings( page );

		// Links and images on, comments, thumbnail and videos off: the shipped
		// answer to "what belongs on paper".
		await expect( page.locator( field( 'links' ) ) ).toHaveValue( '1' );
		await expect( page.locator( field( 'images' ) ) ).toHaveValue( '1' );
		await expect( page.locator( field( 'comments' ) ) ).toHaveValue( '0' );
		await expect( page.locator( field( 'thumbnail' ) ) ).toHaveValue( '0' );
		await expect( page.locator( field( 'videos' ) ) ).toHaveValue( '0' );
		await expect( page.locator( field( 'disclaimer' ) ) ).toHaveValue( defaults.disclaimer );

		// And the template is not on this tab at all, which is the point of there
		// being two of them.
		await expect( page.locator( field( 'html' ) ) ).toHaveCount( 0 );

		await openSettings( page, TAB.templates );

		await expect( page.locator( field( 'html' ) ) ).toHaveValue( defaults.print_html );
		await expect( page.locator( field( 'disclaimer' ) ) ).toHaveCount( 0 );
		await expect( page.locator( field( 'comments' ) ) ).toHaveCount( 0 );
	} );

	test( 'the tabs are Settings and Templates, and each one leads to the other', async ( {
		page,
	} ) => {
		await page.goto( SETTINGS_URL );

		const tabs = page.locator( '.nav-tab-wrapper .nav-tab' );

		// Exactly these two, spelled exactly this way. The h1 above them already
		// says which plugin this is, so a tab called "Print Templates" would be
		// saying it twice and would not match the eighteen other plugins.
		await expect( tabs ).toHaveText( [ 'Settings', 'Templates' ] );

		await tabs.filter( { hasText: 'Templates' } ).click();

		await expect( page.locator( field( 'html' ) ) ).toBeVisible();
		await expect( page.locator( '.nav-tab-active' ) ).toHaveText( 'Templates' );
	} );

	test( 'the link template saves, and the link on the page changes with it', async ( {
		page,
		requestUtils,
	} ) => {
		const post = await createPrintablePost( requestUtils );

		await openSettings( page, TAB.templates );

		await page
			.locator( field( 'html' ) )
			.fill( '<a class="mine" href="%PRINT_URL%">Take this %POST_TYPE% away with you</a>' );

		await saveSettings( page );

		expect( getStoredOptions().print_html ).toBe(
			'<a class="mine" href="%PRINT_URL%">Take this %POST_TYPE% away with you</a>',
		);

		// The far end. A stored value that never reaches the page is the failure a
		// screenshot of this screen cannot distinguish from success -- and
		// %POST_TYPE% only resolves where there is a post to resolve it against.
		await page.goto( post.link );

		await expect(
			page.locator( '.entry-content' ).getByRole( 'link', { name: 'Take this Post away with you' } ),
		).toBeVisible();
		await expect( page.locator( '.entry-content svg.WP-PrintIcon' ) ).toHaveCount( 0 );
	} );

	test( 'saving one tab leaves the other tab alone', async ( { page } ) => {
		// The regression the tabs introduce, and the one that costs a site
		// something it cannot get back. Each tab posts only its own fields, so a
		// sanitizer returning just what it was handed would blank a customised
		// link template the first time anybody flipped a toggle -- silently, with
		// no notice and nothing in the row to say what used to be there.
		setOptions( {
			print_html: '<a class="mine" href="%PRINT_URL%">My own %POST_TYPE% link</a>',
			disclaimer: 'My own notice',
			comments: 0,
		} );

		await openSettings( page );
		await page.locator( field( 'comments' ) ).selectOption( '1' );
		await saveSettings( page );

		expect( getStoredOptions().print_html ).toBe(
			'<a class="mine" href="%PRINT_URL%">My own %POST_TYPE% link</a>',
		);
		expect( getStoredOptions().comments ).toBe( 1 );

		// And the other direction: the Templates tab posts print_html and nothing
		// else, so everything the Settings tab owns has to survive its save.
		await openSettings( page, TAB.templates );
		await page.locator( field( 'html' ) ).fill( '<a href="%PRINT_URL%">Print</a>' );
		await saveSettings( page );

		const stored = getStoredOptions();

		expect( stored.print_html ).toBe( '<a href="%PRINT_URL%">Print</a>' );
		expect( stored.disclaimer ).toBe( 'My own notice' );
		expect( stored.comments ).toBe( 1 );
	} );

	test( 'a save comes back to the tab it was made from', async ( { page } ) => {
		await openSettings( page, TAB.templates );
		await saveSettings( page );

		// options.php sends the browser to wp_get_referer(), so a screen that does
		// not carry its tab through the save drops the owner back on the first tab
		// and looks like it lost the change.
		expect( page.url() ).toContain( 'tab=templates' );
		await expect( page.locator( '.nav-tab-active' ) ).toHaveText( 'Templates' );
		await expect( page.locator( field( 'html' ) ) ).toBeVisible();
	} );

	test( 'all five content toggles save, and one of them is checked on paper', async ( {
		page,
		requestUtils,
	} ) => {
		const post = await createPrintablePost( requestUtils, {
			content: 'A body. [print_link]',
		} );

		// A comment, because the printable comments template renders nothing at
		// all when there are none -- so "comments are on" and "there is nothing
		// to say" would look identical at the far end.
		await requestUtils.createComment( { content: 'A comment to print.', post: post.id } );

		await openSettings( page );

		// Every one of them flipped from its shipped value, so a screen that wrote
		// the defaults back would fail rather than coincide.
		await page.locator( field( 'comments' ) ).selectOption( '1' );
		await page.locator( field( 'links' ) ).selectOption( '0' );
		await page.locator( field( 'images' ) ).selectOption( '0' );
		await page.locator( field( 'thumbnail' ) ).selectOption( '1' );
		await page.locator( field( 'videos' ) ).selectOption( '1' );

		await saveSettings( page );

		expect( getStoredOptions() ).toMatchObject( {
			comments: 1,
			links: 0,
			images: 0,
			thumbnail: 1,
			videos: 1,
		} );

		// The screen re-reads what it wrote, which is where a select that stores
		// the right value and renders the wrong one shows up.
		await openSettings( page );

		await expect( page.locator( field( 'comments' ) ) ).toHaveValue( '1' );
		await expect( page.locator( field( 'links' ) ) ).toHaveValue( '0' );

		// And one effect end to end, so the whole set is not merely stored: with
		// comments on, the printed document gains the section it did not have.
		await page.goto( printUrl( post.link ) );

		await expect( page.locator( '#comments_controls' ) ).toHaveCount( 1 );
		await expect( page.locator( '#comments_box' ) ).toContainText( 'A comment to print' );

		await requestUtils.deleteAllComments();
	} );

	test( 'an emptied template is stored empty rather than refilled', async ( { page } ) => {
		await openSettings( page, TAB.templates );

		await page.locator( field( 'html' ) ).fill( '' );
		await saveSettings( page );

		// A site may genuinely not want a link -- it has [print_link] and the
		// template tag either way -- so an empty box means empty, and Restore
		// Default Template is how the shipped one comes back.
		expect( effectiveOptions().print_html ).toBe( '' );
	} );

	test( 'Restore Default Template puts back exactly the shipped strings, on both tabs', async ( {
		page,
	} ) => {
		setOptions( {
			print_html: '<a href="%PRINT_URL%">something else entirely</a>',
			disclaimer: 'Some other notice',
		} );

		await openSettings( page, TAB.templates );

		await expect( page.locator( field( 'html' ) ) ).toHaveValue(
			'<a href="%PRINT_URL%">something else entirely</a>',
		);

		await page.getByRole( 'button', { name: 'Restore Default Template' } ).click();

		// Byte for byte, not merely similar. The defaults reach the page through
		// wp_localize_script(), which runs html_entity_decode() over every scalar
		// it is handed -- so the disclaimer's &copy; would arrive as a literal ©
		// and Restore Default would insert something the plugin never shipped. The
		// nesting that avoids that is invisible from PHP and this is what checks
		// it survived.
		await expect( page.locator( field( 'html' ) ) ).toHaveValue( defaults.print_html );

		// Restoring fills the box; saving is what stores it.
		await saveSettings( page );

		await openSettings( page );

		await page.getByRole( 'button', { name: 'Restore Default Template' } ).click();
		await expect( page.locator( field( 'disclaimer' ) ) ).toHaveValue( defaults.disclaimer );

		await saveSettings( page );

		expect( effectiveOptions().disclaimer ).toBe( defaults.disclaimer );
		expect( effectiveOptions().print_html ).toBe( defaults.print_html );
	} );

	test( 'a save keeps a key this screen does not render and drops every retired one', async ( {
		page,
	} ) => {
		setOptions( {
			print_icon: 'printer.gif',
			print_style: 1,
			post_text: 'Print This Post',
			page_text: 'Print This Page',
			some_other_plugins_key: 'kept',
		} );

		await openSettings( page );
		await saveSettings( page );

		const stored = getStoredOptions();

		// A screen that returns only what it renders drops everything a filter or
		// an older version put in the row, on the first save, silently.
		expect( stored.some_other_plugins_key ).toBe( 'kept' );

		// Except the keys the plugin has deliberately retired. print_icon chose
		// between two bundled GIFs and there is one inline glyph now; print_style,
		// post_text and page_text were the link's four settings and there is one
		// template. Letting any of them survive the merge would resurrect a setting
		// with nothing to set.
		expect( stored.print_icon ).toBeUndefined();
		expect( stored.print_style ).toBeUndefined();
		expect( stored.post_text ).toBeUndefined();
		expect( stored.page_text ).toBeUndefined();
	} );

	test( 'the success notice is printed once on either tab, not twice', async ( { page } ) => {
		await openSettings( page );
		await saveSettings( page );

		// A page registered with add_options_page() has options-head.php run ahead
		// of it, and that file already calls settings_errors(). A screen that calls
		// it again renders every queued notice a second time, one under the other,
		// in what looks like the plugin's own markup. This screen deliberately does
		// not call it -- this is the guard on that.
		await expect( page.locator( '#setting-error-settings_updated' ) ).toHaveCount( 1 );

		// And the notice is not a casualty of the tab that was saved: the second
		// tab is the same screen with a query argument, so options-head.php runs
		// for it too.
		await openSettings( page, TAB.templates );
		await saveSettings( page );

		await expect( page.locator( '#setting-error-settings_updated' ) ).toHaveCount( 1 );
	} );

	test( 'the Plugins screen carries a Settings link that goes to the screen', async ( { page } ) => {
		await page.goto( PLUGINS_URL );

		const row = page.locator( 'tr[data-slug="wp-print"]' );

		await expect( row ).toHaveCount( 1 );
		await row.getByRole( 'link', { name: 'Settings' } ).click();

		await expect( page.getByRole( 'heading', { level: 1, name: 'Print Settings' } ) ).toBeVisible();
	} );

	test( 'a subscriber gets neither the menu item nor the screen, and an administrator gets both', async ( {
		page,
		requestUtils,
	} ) => {
		// Both directions on purpose. "The subscriber sees nothing" passes with
		// the plugin deactivated, because there is nothing to see either way; the
		// administrator half is what proves the gate is the capability.
		await page.goto( '/wp-admin/options-general.php' );
		await expect( page.locator( '#adminmenu' ) ).toContainText( 'WP-Print' );

		await openSettings( page );

		await requestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/users',
			data: {
				username: 'print_subscriber',
				email: 'print_subscriber@example.com',
				password: 'correct-horse-battery-staple',
				roles: [ 'subscriber' ],
			},
		} ).catch( () => {} ); // Already there from an earlier run.

		const context = await page.context().browser().newContext( { storageState: undefined } );
		const other = await context.newPage();

		await other.goto( '/wp-login.php' );

		// wp-login.php focuses and selects #user_login on a 200ms timer, so that a
		// visitor can start typing. Filling across that moment puts the password
		// into the username box: Playwright focuses #user_pass, the timer takes
		// focus back and selects what is there, and the typed text replaces the
		// selection. Waiting for the timer's own effect is the signal that it has
		// already fired.
		await expect( other.locator( '#user_login' ) ).toBeFocused();

		await other.locator( '#user_login' ).fill( 'print_subscriber' );
		await other.locator( '#user_pass' ).fill( 'correct-horse-battery-staple' );
		await other.locator( '#wp-submit' ).click();
		await expect( other.locator( '#wpadminbar' ) ).toBeVisible();

		await other.goto( '/wp-admin/index.php' );
		await expect( other.locator( '#adminmenu' ).getByText( 'WP-Print' ) ).toHaveCount( 0 );

		// Both tabs, because a capability check that only guards the default one
		// would leave the template editable by anybody who guessed the query
		// argument.
		await other.goto( SETTINGS_URL );
		await expect( other.locator( 'body' ) ).toContainText( /not allowed to access this page/ );

		await other.goto( tabUrl( TAB.templates ) );
		await expect( other.locator( 'body' ) ).toContainText( /not allowed to access this page/ );

		await context.close();
	} );

	test( 'the capability filter is what decides, and it decides both the menu and the screen', async ( {
		page,
		requestUtils,
	} ) => {
		// The filter is asked twice with two different contexts -- 'menu' for
		// add_options_page() and 'settings' for the render -- so it has to move
		// both gates. A screen that let the menu through and then wp_die()'d on
		// the render would look exactly like a broken plugin.
		//
		// The fixture mu-plugin answers from an option, so it stays inert for the
		// test above, which is the one that has to keep proving manage_options is
		// the default.
		setFixtureOption( 'wp_print_e2e_capability', 'read' );

		await requestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/users',
			data: {
				username: 'print_subscriber',
				email: 'print_subscriber@example.com',
				password: 'correct-horse-battery-staple',
				roles: [ 'subscriber' ],
			},
		} ).catch( () => {} ); // Already there from the test above.

		const context = await page.context().browser().newContext( { storageState: undefined } );
		const other = await context.newPage();

		try {
			await other.goto( '/wp-login.php' );

			// The 200ms focus timer again -- see the test above for what filling
			// across it does to the password.
			await expect( other.locator( '#user_login' ) ).toBeFocused();

			await other.locator( '#user_login' ).fill( 'print_subscriber' );
			await other.locator( '#user_pass' ).fill( 'correct-horse-battery-staple' );
			await other.locator( '#wp-submit' ).click();
			await expect( other.locator( '#wpadminbar' ) ).toBeVisible();

			await other.goto( '/wp-admin/index.php' );
			await expect( other.locator( '#adminmenu' ) ).toContainText( 'WP-Print' );

			await other.goto( SETTINGS_URL );
			await expect( other.getByRole( 'heading', { level: 1, name: 'Print Settings' } ) ).toBeVisible();

			// The form itself, not just the wrapper: render_page() wp_die()s on a
			// failed capability check, so the fields are what says it did not.
			await expect( other.locator( field( 'disclaimer' ) ) ).toBeAttached();
		} finally {
			// Inside a finally, because a filter left answering 'read' would hand
			// this screen to a subscriber for the rest of the run and quietly
			// invalidate the test above it.
			clearFixtureOption( 'wp_print_e2e_capability' );
			await context.close();
		}
	} );
} );
