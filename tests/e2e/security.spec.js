/**
 * The stored XSS regressions, in a real browser.
 *
 * WP-Print echoes three things it read out of its own option row -- the two link
 * texts, the custom link template and the disclaimer -- and one thing it read
 * out of somebody else's: the post body, which it re-escapes on the way into the
 * printed document. All four are covered here.
 *
 * The fixtures go straight into the option row rather than through the settings
 * screen. Sanitising on the way in is the assumption under test, not a step to
 * reproduce: this is the row a compromised install, a WP-CLI one-liner or a
 * pre-3.0.0 release already left behind.
 *
 * The assertion has two halves everywhere. The sentinel the payload would set
 * must never become defined, and the payload's text must still be on the page --
 * escaping that ate the value entirely passes the first half and is its own bug,
 * because a print link whose wording silently vanished is one.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	createPrintablePost,
	deleteOptions,
	field,
	openSettings,
	printUrl,
	setOptions,
} = require( './helpers.js' );

const SCRIPT_PAYLOAD = '<script>window.__pwned = 1;</script>';
const IMG_PAYLOAD = '<img src=x onerror="window.__pwned = 1">';
const ATTR_PAYLOAD = '" onmouseover="window.__pwned = 1';

/**
 * Whether any payload managed to run.
 *
 * @param {import('@playwright/test').Page} page Page to ask.
 * @return {Promise<boolean>} True if the sentinel was set.
 */
function pwned( page ) {
	return page.evaluate( () => window.__pwned === 1 );
}

test.describe( 'A hostile option row stays inert', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
	} );

	test.afterEach( async () => {
		deleteOptions();
	} );

	test( 'the shortcode renders a hostile link text as text', async ( { page, requestUtils } ) => {
		setOptions( { print_style: 3, post_text: `Print ${ SCRIPT_PAYLOAD }` } );

		const post = await createPrintablePost( requestUtils );

		await page.goto( post.link );

		const content = page.locator( '.entry-content' );

		// The link is there at all -- otherwise every assertion below is about an
		// empty div.
		await expect( content.locator( 'a[rel="nofollow"]' ) ).toHaveCount( 1 );

		// This currently fails, and it is right to: the script runs.
		//
		// WP_Print_Link::render() interpolates the link text into its markup
		// twice -- escaped into the title attribute, and raw as the anchor's
		// contents. Its docblock says the caller escapes at the point of output,
		// and print_link() does exactly that with wp_kses(). The shortcode does
		// not: WP_Print_Link::shortcode() returns render()'s markup straight to
		// the shortcode engine, which inserts it into the_content untouched.
		//
		// So the same stored value is inert through the template tag and live
		// through the shortcode, which is the one every documented install
		// actually uses.
		expect( await pwned( page ) ).toBe( false );

		await expect( content.locator( 'script' ) ).toHaveCount( 0 );
		await expect( content ).toContainText( 'window.__pwned' );
	} );

	test( 'the template tag renders the same hostile link text as text', async ( {
		page,
		requestUtils,
	} ) => {
		setOptions( { print_style: 3, post_text: `Print ${ SCRIPT_PAYLOAD }` } );

		const post = await createPrintablePost( requestUtils, { content: '[print_link_tag]' } );

		await page.goto( post.link );

		const tag = page.locator( '.wp-print-e2e-tag' );

		// Deliberately the same fixture as the test above, through the other
		// route out of WP_Print_Link::render(). print_link() escapes at its sink
		// with wp_kses(); the shortcode returns the same markup untouched. Two
		// tests rather than one, because a payload can be inert in one and live in
		// the other, and knowing which is the difference between a diagnosis and a
		// shrug.
		await expect( tag.locator( 'a[rel="nofollow"]' ) ).toHaveCount( 1 );

		expect( await pwned( page ) ).toBe( false );

		await expect( tag.locator( 'script' ) ).toHaveCount( 0 );
		await expect( tag ).toContainText( 'window.__pwned' );
	} );

	test( 'a hostile link text cannot break out of the title and aria-label', async ( {
		page,
		requestUtils,
	} ) => {
		const text = `Print ${ ATTR_PAYLOAD }`;

		setOptions( { print_style: 2, post_text: text } );

		const post = await createPrintablePost( requestUtils );

		await page.goto( post.link );

		const link = page.locator( '.entry-content a[rel="nofollow"]' );

		// The icon-only style is the one that prints the text into two attributes
		// and nowhere else, so a bare quote here would end the attribute and turn
		// everything after it into markup on the anchor itself.
		await expect( link ).toHaveAttribute( 'title', text );
		await expect( link ).toHaveAttribute( 'aria-label', text );

		expect( await pwned( page ) ).toBe( false );
		await expect( page.locator( '.entry-content [onmouseover]' ) ).toHaveCount( 0 );
	} );

	test( 'a hostile custom template renders as text', async ( { page, requestUtils } ) => {
		setOptions( {
			print_style: 4,
			print_html: `<a class="my-print" href="%PRINT_URL%">Print ${ IMG_PAYLOAD }</a>`,
		} );

		const post = await createPrintablePost( requestUtils );

		await page.goto( post.link );

		const content = page.locator( '.entry-content' );

		await expect( content.locator( 'a.my-print' ) ).toHaveCount( 1 );

		// The same defect as the first test in this file, reached through the
		// other stored string: the custom template is substituted and returned
		// by the shortcode without going through any allow-list, so the handler
		// survives and fires. Through print_link() it does not.
		expect( await pwned( page ) ).toBe( false );

		// The allow-list the link is escaped with keeps <img> -- an icon in a
		// custom template is a legitimate thing to want -- and strips the handler,
		// so the check is not "no image" but "nothing that runs".
		await expect( content.locator( 'img[onerror]' ) ).toHaveCount( 0 );
	} );

	test( 'the printed document renders a hostile disclaimer as text', async ( {
		page,
		requestUtils,
	} ) => {
		setOptions( { disclaimer: `Copyright ${ SCRIPT_PAYLOAD } ${ IMG_PAYLOAD }` } );

		const post = await createPrintablePost( requestUtils );

		await page.goto( printUrl( post.link ) );

		const disclaimer = page.locator( '.wp-print-disclaimer' );

		await expect( disclaimer ).toContainText( 'Copyright' );

		expect( await pwned( page ) ).toBe( false );

		// wp_kses_post() at the sink: it keeps a bare <img>, because a site
		// putting a logo in its copyright line is reasonable, and strips only the
		// handler. The script goes entirely, and its text survives -- which is
		// what the second assertion is for.
		await expect( disclaimer.locator( 'script' ) ).toHaveCount( 0 );
		await expect( disclaimer.locator( 'img[onerror]' ) ).toHaveCount( 0 );
		await expect( disclaimer ).toContainText( 'window.__pwned' );
	} );

	test( 'a post body holding a script does not run in the printed document', async ( {
		page,
		requestUtils,
	} ) => {
		const post = await createPrintablePost( requestUtils, {
			content: `A body, and then ${ SCRIPT_PAYLOAD } ${ IMG_PAYLOAD } [print_link]`,
		} );

		// The normal view is the theme's problem and a post body is
		// admin-authored, so this is not a claim about the site. It is a claim
		// about the print view, which re-renders somebody else's HTML through its
		// own allow-list, and is the one place in this plugin that echoes content
		// it did not write.
		await page.goto( printUrl( post.link ) );

		const content = page.locator( '.entry-content' );

		await expect( content ).toContainText( 'A body, and then' );

		expect( await pwned( page ) ).toBe( false );

		await expect( content.locator( 'script' ) ).toHaveCount( 0 );
		await expect( content.locator( 'img[onerror]' ) ).toHaveCount( 0 );
	} );

	test( 'the settings screen round-trips a hostile row without running or losing it', async ( {
		page,
	} ) => {
		const options = {
			print_style: 4,
			post_text: `Print ${ ATTR_PAYLOAD }`,
			page_text: `Page ${ SCRIPT_PAYLOAD }`,
			print_html: `<a href="%PRINT_URL%">${ IMG_PAYLOAD }</a>`,
			disclaimer: `Copyright ${ SCRIPT_PAYLOAD }`,
		};

		setOptions( options );

		await openSettings( page );

		expect( await pwned( page ) ).toBe( false );
		await expect( page.locator( '#wpbody [onerror]' ) ).toHaveCount( 0 );
		await expect( page.locator( '#wpbody [onmouseover]' ) ).toHaveCount( 0 );

		// The fields must hold the stored values themselves. A screen that escaped
		// a value into the attribute and decoded it on save is fine; one that
		// dropped or double-escaped it corrupts the row the moment the owner
		// presses Save Changes -- which is the first thing an owner does after
		// finding a payload in their print link.
		await expect( page.locator( field( 'post_text' ) ) ).toHaveValue( options.post_text );
		await expect( page.locator( field( 'page_text' ) ) ).toHaveValue( options.page_text );
		await expect( page.locator( field( 'html' ) ) ).toHaveValue( options.print_html );
		await expect( page.locator( field( 'disclaimer' ) ) ).toHaveValue( options.disclaimer );
	} );
} );
