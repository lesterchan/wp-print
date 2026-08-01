/**
 * Settings -> WP-Print.
 *
 * This screen replaced a hand-rolled form that posted to itself and did its own
 * nonce and capability handling, so every field here is new plumbing over old
 * storage: the keys did not change, the way they are written did. That is
 * exactly the change a unit test on the sanitiser cannot vouch for, because the
 * sanitiser is only reached if the form posts what it expects.
 *
 * So each test saves through the form, reads the row back, and where the setting
 * is visible to a reader, goes and looks at the page. And two of the controls are
 * pure JavaScript -- the custom template reveal and the two Restore Default
 * buttons -- which exist nowhere else at all.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	PLUGINS_URL,
	SETTINGS_URL,
	STYLE,
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

	test( 'the fixture really is a fresh install, and the screen shows the shipped defaults', async ( {
		page,
	} ) => {
		// The precondition the rest of the file leans on. With a row already in
		// place, "the field saved" could be true before the test did anything.
		expect( getStoredOptions() ).toBe( false );

		await openSettings( page );

		await expect( page.locator( field( 'post_text' ) ) ).toHaveValue( defaults.post_text );
		await expect( page.locator( field( 'page_text' ) ) ).toHaveValue( defaults.page_text );
		await expect( page.locator( field( 'style' ) ) ).toHaveValue( STYLE.iconAndText );
		await expect( page.locator( field( 'html' ) ) ).toHaveValue( defaults.print_html );
		await expect( page.locator( field( 'disclaimer' ) ) ).toHaveValue( defaults.disclaimer );

		// Links and images on, comments, thumbnail and videos off: the shipped
		// answer to "what belongs on paper".
		await expect( page.locator( field( 'links' ) ) ).toHaveValue( '1' );
		await expect( page.locator( field( 'images' ) ) ).toHaveValue( '1' );
		await expect( page.locator( field( 'comments' ) ) ).toHaveValue( '0' );
		await expect( page.locator( field( 'thumbnail' ) ) ).toHaveValue( '0' );
		await expect( page.locator( field( 'videos' ) ) ).toHaveValue( '0' );
	} );

	test( 'the link texts and the style save, and the link changes', async ( {
		page,
		requestUtils,
	} ) => {
		const post = await createPrintablePost( requestUtils );

		await openSettings( page );

		await page.locator( field( 'post_text' ) ).fill( 'Take this away with you' );
		await page.locator( field( 'page_text' ) ).fill( 'Take this page away' );
		await page.locator( field( 'style' ) ).selectOption( STYLE.textOnly );

		await saveSettings( page );

		const stored = getStoredOptions();

		expect( stored.post_text ).toBe( 'Take this away with you' );
		expect( stored.page_text ).toBe( 'Take this page away' );
		expect( stored.print_style ).toBe( 3 );

		// The far end. A stored value that never reaches the page is the failure a
		// screenshot of this screen cannot distinguish from success.
		await page.goto( post.link );

		await expect(
			page.locator( '.entry-content' ).getByRole( 'link', { name: 'Take this away with you' } ),
		).toBeVisible();
		await expect( page.locator( '.entry-content svg.WP-PrintIcon' ) ).toHaveCount( 0 );
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

	test( 'an emptied link text falls back to the shipped default', async ( { page } ) => {
		await openSettings( page );

		await page.locator( field( 'post_text' ) ).fill( '' );
		await page.locator( field( 'page_text' ) ).fill( '   ' );

		await saveSettings( page );

		// A link with no text is a link with nothing to click, so an empty
		// submission means "give me the shipped wording back" rather than "store
		// an empty string". Whitespace counts as empty.
		//
		// Read as the plugin reads it rather than off the raw row. The result of
		// this particular save is the defaults exactly, and update_option()
		// declines to write a value it already answers with -- so there is no
		// row afterwards, and that is the correct outcome rather than a missing
		// setting. What matters is what the link will say.
		const options = effectiveOptions();

		expect( options.post_text ).toBe( defaults.post_text );
		expect( options.page_text ).toBe( defaults.page_text );
	} );

	test( 'the custom template is revealed only for the custom style', async ( { page } ) => {
		await openSettings( page );

		const custom = page.locator( '#wp-print-custom' );

		// Hidden to begin with, because the shipped style is not Custom. The
		// server prints the hidden class and the script keeps it in step, so this
		// is the pair: neither half alone would show the box at the right moment.
		await expect( custom ).toBeHidden();

		await page.locator( field( 'style' ) ).selectOption( STYLE.custom );
		await expect( custom ).toBeVisible();

		await page.locator( field( 'style' ) ).selectOption( STYLE.textOnly );
		await expect( custom ).toBeHidden();

		// And on the way back in: saving as Custom has to leave the box open when
		// the screen is next opened, or the setting looks like it did not save.
		await page.locator( field( 'style' ) ).selectOption( STYLE.custom );
		await saveSettings( page );

		await openSettings( page );
		await expect( page.locator( '#wp-print-custom' ) ).toBeVisible();
	} );

	test( 'Restore Default Template puts back exactly the shipped strings', async ( { page } ) => {
		setOptions( {
			print_style: 4,
			print_html: '<a href="%PRINT_URL%">something else entirely</a>',
			disclaimer: 'Some other notice',
		} );

		await openSettings( page );

		await expect( page.locator( field( 'html' ) ) ).toHaveValue(
			'<a href="%PRINT_URL%">something else entirely</a>',
		);

		await page.getByRole( 'button', { name: 'Restore Default Template' } ).first().click();
		await page.getByRole( 'button', { name: 'Restore Default Template' } ).last().click();

		// Byte for byte, not merely similar. The defaults reach the page through
		// wp_localize_script(), which runs html_entity_decode() over every scalar
		// it is handed -- so the disclaimer's &copy; would arrive as a literal ©
		// and Restore Default would insert something the plugin never shipped. The
		// nesting that avoids that is invisible from PHP and this is what checks
		// it survived.
		await expect( page.locator( field( 'html' ) ) ).toHaveValue( defaults.print_html );
		await expect( page.locator( field( 'disclaimer' ) ) ).toHaveValue( defaults.disclaimer );

		// Restoring fills the box; saving is what stores it.
		await saveSettings( page );

		expect( getStoredOptions().disclaimer ).toBe( defaults.disclaimer );
	} );

	test( 'a style outside the four keeps the last valid one', async ( { page } ) => {
		setOptions( { print_style: 3 } );

		await openSettings( page );

		// The select cannot offer this, which is the point: the sanitiser is what
		// a hand-made POST, a WP-CLI call or another plugin meets, and an unknown
		// style renders no link at all. Keeping the last valid one is the
		// difference between a rejected write and a site with no print link.
		await page.locator( field( 'style' ) ).evaluate( ( select ) => {
			const option = document.createElement( 'option' );

			option.value = '99';
			option.selected = true;
			select.appendChild( option );
		} );

		await saveSettings( page );

		expect( getStoredOptions().print_style ).toBe( 3 );
	} );

	test( 'a save keeps a key this screen does not render and drops a retired one', async ( {
		page,
	} ) => {
		setOptions( {
			print_style: 1,
			print_icon: 'printer.gif',
			some_other_plugins_key: 'kept',
		} );

		await openSettings( page );
		await saveSettings( page );

		const stored = getStoredOptions();

		// A screen that returns only what it renders drops everything a filter or
		// an older version put in the row, on the first save, silently.
		expect( stored.some_other_plugins_key ).toBe( 'kept' );

		// Except a key the plugin has deliberately retired. print_icon chose
		// between two bundled GIFs and there is one inline glyph now, so letting
		// it survive the merge would resurrect a setting with nothing to choose.
		expect( stored.print_icon ).toBeUndefined();
	} );

	test( 'the success notice is printed once, not twice', async ( { page } ) => {
		await openSettings( page );
		await saveSettings( page );

		// A page registered with add_options_page() has options-head.php run ahead
		// of it, and that file already calls settings_errors(). A screen that calls
		// it again renders every queued notice a second time, one under the other,
		// in what looks like the plugin's own markup. This screen deliberately does
		// not call it -- this is the guard on that.
		await expect( page.locator( '#setting-error-settings_updated' ) ).toHaveCount( 1 );
	} );

	test( 'the Plugins screen carries a Settings link that goes to the screen', async ( { page } ) => {
		await page.goto( PLUGINS_URL );

		const row = page.locator( 'tr[data-slug="wp-print"]' );

		await expect( row ).toHaveCount( 1 );
		await row.getByRole( 'link', { name: 'Settings' } ).click();

		await expect( page.getByRole( 'heading', { level: 1, name: 'Print Options' } ) ).toBeVisible();
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

		await other.goto( SETTINGS_URL );
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
			await expect( other.getByRole( 'heading', { level: 1, name: 'Print Options' } ) ).toBeVisible();

			// The form itself, not just the wrapper: render_page() wp_die()s on a
			// failed capability check, so the fields are what says it did not.
			await expect( other.locator( field( 'post_text' ) ) ).toBeAttached();
		} finally {
			// Inside a finally, because a filter left answering 'read' would hand
			// this screen to a subscriber for the rest of the run and quietly
			// invalidate the test above it.
			clearFixtureOption( 'wp_print_e2e_capability' );
			await context.close();
		}
	} );
} );
