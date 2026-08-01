/**
 * The print link, on the page a reader is actually looking at.
 *
 * The link is the plugin's most visible output and the one themes style by
 * class, so its markup is byte-for-byte what earlier releases emitted. That
 * makes it exactly the sort of thing a unit test can pass while a browser gets
 * something else: the four styles differ only in which elements are present, the
 * glyph is an inline SVG that a stricter kses would silently eat, and the URL
 * takes two different shapes depending on a site-wide setting that has nothing
 * to do with this plugin.
 *
 * Every test here ends at the far end -- either the rendered element or the
 * document the href actually leads to -- rather than at the stored row.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	createPrintablePost,
	defaultOptions,
	deleteOptions,
	permalinkStructure,
	printUrl,
	setOptions,
	setPermalinkStructure,
	uniqueTitle,
} = require( './helpers.js' );

/** The permalink structure the environment started with, put back afterwards. */
let originalPermalinks;

/** The shipped defaults, which two tests compare rendered text against. */
let defaults;

test.describe( 'The print link', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();

		deleteOptions();

		originalPermalinks = permalinkStructure();
		defaults = defaultOptions();
	} );

	test.afterEach( async () => {
		// The row and the permalink structure are both site-wide, and the second
		// one decides what every URL on the site looks like, so neither is left
		// as a test found convenient.
		deleteOptions();

		if ( permalinkStructure() !== originalPermalinks ) {
			setPermalinkStructure( originalPermalinks );
		}
	} );

	test( 'the fixture really is a working link: the default style, pointing at a page that prints', async ( {
		page,
		requestUtils,
	} ) => {
		const title = uniqueTitle( 'Printable' );
		const post = await createPrintablePost( requestUtils, { title } );

		await page.goto( post.link );

		const content = page.locator( '.entry-content' );

		// The default is icon-and-text, which is two anchors to the same place.
		await expect( content.locator( 'a[rel="nofollow"]' ) ).toHaveCount( 2 );
		await expect( content.locator( 'svg.WP-PrintIcon' ) ).toHaveCount( 1 );

		// The second of the two, which is the one carrying the words. Both have
		// the same accessible name -- the glyph's link gets it from the title
		// attribute, because the SVG inside it is aria-hidden -- so a query by
		// name matches both and strict mode refuses to guess.
		const link = content.locator( 'a[rel="nofollow"]' ).last();

		await expect( link ).toHaveText( defaults.post_text );
		await expect( link ).toHaveAttribute( 'title', defaults.post_text );

		// And the href is not merely well-formed: following it renders the print
		// view. Without this half every other test in the file could be asserting
		// about a link that leads nowhere.
		await link.click();

		await expect( page.locator( 'body.wp-print' ) ).toBeVisible();
		await expect( page.locator( 'h1.entry-title' ) ).toHaveText( title );
	} );

	test( 'the icon-only style is one link with an accessible name and no visible text', async ( {
		page,
		requestUtils,
	} ) => {
		setOptions( { print_style: 2 } );

		const post = await createPrintablePost( requestUtils );

		await page.goto( post.link );

		const link = page.locator( '.entry-content a[rel="nofollow"]' );

		await expect( link ).toHaveCount( 1 );
		await expect( link.locator( 'svg.WP-PrintIcon' ) ).toHaveCount( 1 );

		// The glyph is aria-hidden, so without the aria-label this link would have
		// no accessible name at all -- which is the whole reason the style carries
		// one and the others do not.
		await expect( link ).toHaveAttribute( 'aria-label', defaults.post_text );
		await expect( link ).toHaveText( '' );
	} );

	test( 'the text-only style drops the glyph and keeps the words', async ( {
		page,
		requestUtils,
	} ) => {
		setOptions( { print_style: 3 } );

		const post = await createPrintablePost( requestUtils );

		await page.goto( post.link );

		const content = page.locator( '.entry-content' );

		await expect( content.locator( 'a[rel="nofollow"]' ) ).toHaveCount( 1 );
		await expect( content.locator( 'svg.WP-PrintIcon' ) ).toHaveCount( 0 );
		await expect( content.getByRole( 'link', { name: defaults.post_text } ) ).toBeVisible();
	} );

	test( 'the custom style substitutes all three placeholders', async ( { page, requestUtils } ) => {
		setOptions( {
			print_style: 4,
			print_html:
				'<a class="my-print" href="%PRINT_URL%" title="%PRINT_TEXT%">%PRINT_ICON% %PRINT_TEXT%</a>',
		} );

		const post = await createPrintablePost( requestUtils );

		await page.goto( post.link );

		const link = page.locator( '.entry-content a.my-print' );

		// %PRINT_URL%, %PRINT_TEXT% and %PRINT_ICON% each replaced, and the
		// template's own markup kept -- the class is the proof that the plugin
		// rendered the site's template rather than one of its own three styles.
		await expect( link ).toHaveAttribute( 'href', printUrl( post.link ) );
		await expect( link ).toHaveAttribute( 'title', defaults.post_text );
		await expect( link ).toContainText( defaults.post_text );
		await expect( link.locator( 'svg.WP-PrintIcon' ) ).toHaveCount( 1 );
	} );

	test( 'a style the plugin does not know renders nothing, and a known one renders again', async ( {
		page,
		requestUtils,
	} ) => {
		// Only reachable through a row the settings screen did not write: the
		// sanitizer rejects anything outside 1-4. Rendering nothing is the right
		// answer, but it must not be a state the plugin gets stuck in.
		setOptions( { print_style: 9 } );

		const post = await createPrintablePost( requestUtils );

		await page.goto( post.link );

		await expect( page.locator( '.entry-content a[rel="nofollow"]' ) ).toHaveCount( 0 );
		await expect( page.locator( '.entry-content svg.WP-PrintIcon' ) ).toHaveCount( 0 );

		setOptions( { print_style: 3 } );

		await page.goto( post.link );

		await expect( page.locator( '.entry-content a[rel="nofollow"]' ) ).toHaveCount( 1 );
	} );

	test( 'a post uses the post text and a page uses the page text', async ( {
		page,
		requestUtils,
	} ) => {
		setOptions( { print_style: 3, post_text: 'Print the post', page_text: 'Print the page' } );

		const post = await createPrintablePost( requestUtils );
		const pageFixture = await requestUtils.createPage( {
			title: uniqueTitle( 'Printable page' ),
			content: '[print_link]',
			status: 'publish',
		} );

		await page.goto( post.link );
		await expect( page.locator( '.entry-content' ) ).toContainText( 'Print the post' );
		await expect( page.locator( '.entry-content' ) ).not.toContainText( 'Print the page' );

		// The two texts are separate settings precisely so a site can say "Print
		// this page" on pages, and the branch that chooses between them is
		// is_page() -- which only means anything in a real request.
		await page.goto( pageFixture.link );
		await expect( page.locator( '.entry-content' ) ).toContainText( 'Print the page' );
		await expect( page.locator( '.entry-content' ) ).not.toContainText( 'Print the post' );
	} );

	test( 'the link works under plain permalinks too, as a query argument', async ( {
		page,
		requestUtils,
	} ) => {
		setPermalinkStructure( '' );

		const title = uniqueTitle( 'Printable' );
		const post = await createPrintablePost( requestUtils, { title } );

		await page.goto( post.link );

		const link = page.locator( '.entry-content a[rel="nofollow"]' ).first();
		const href = await link.getAttribute( 'href' );

		// Without a permalink structure there is no /print/ to append: the plugin
		// falls back to the public query var, which is the reason it registers one
		// at all. The endpoint and the query var are two different code paths and
		// a site on either setting has to reach the same document.
		expect( href ).toContain( 'print=1' );
		expect( href ).not.toContain( '/print/' );

		await link.click();

		await expect( page.locator( 'body.wp-print' ) ).toBeVisible();
		await expect( page.locator( 'h1.entry-title' ) ).toHaveText( title );
	} );

	test( 'the shortcode says so in a feed instead of printing a link', async ( {
		page,
		requestUtils,
	} ) => {
		const post = await createPrintablePost( requestUtils );

		// The site feed as a query argument, which is a URL WordPress answers
		// whatever the permalink structure is. Appending /feed/ to a permalink
		// only works when permalinks are pretty, and the tests environment ships
		// with them plain -- so that spelling quietly fetched the post's own HTML
		// page instead, where the shortcode does render a link and the assertion
		// below was measuring the wrong document.
		const feed = await page.request.get( '/?feed=rss2' );
		const body = await feed.text();

		expect( body ).toContain( post.title.rendered );

		// A feed reader cannot follow a print link usefully -- and the link would
		// be rendered relative to whatever site republished the item -- so the
		// shortcode returns a sentence there instead. Fetched rather than
		// navigated to, because what matters is the bytes, not what Chromium
		// decides to do with an XML document.
		expect( body ).toContain( 'There is a print link embedded within this post' );
		expect( body ).not.toContain( 'WP-PrintIcon' );
	} );

	test( 'the template tag prints the same link, glyph intact', async ( { page, requestUtils } ) => {
		const post = await createPrintablePost( requestUtils, {
			content: '[print_link_tag]',
		} );

		await page.goto( post.link );

		const tag = page.locator( '.wp-print-e2e-tag' );

		// A separate path from the shortcode: print_link() echoes through
		// wp_kses() with the plugin's own allow-list rather than returning markup
		// untouched. That list exists because wp_kses_post() has never allowed
		// <svg>, so the wrong list here would silently drop the glyph and nothing
		// else would notice.
		await expect( tag.locator( 'a[rel="nofollow"]' ) ).toHaveCount( 2 );
		await expect( tag.locator( 'svg.WP-PrintIcon' ) ).toHaveCount( 1 );
		await expect( tag.locator( 'svg.WP-PrintIcon path' ) ).toHaveCount( 3 );

		// The second anchor, the one carrying the words. Both have the same
		// accessible name -- the glyph's link takes its from the title
		// attribute, since the SVG inside is aria-hidden -- so a query by name
		// matches both and strict mode refuses to guess.
		await expect( tag.locator( 'a[rel="nofollow"]' ).last() ).toHaveAttribute(
			'href',
			printUrl( post.link ),
		);
	} );

	test( 'donotprint keeps its content on an ordinary page view', async ( { page, requestUtils } ) => {
		const post = await createPrintablePost( requestUtils, {
			content: 'Before [donotprint]only on screen[/donotprint] after. [print_link]',
		} );

		await page.goto( post.link );

		// The shortcode exists to hide text from the printed document, not from
		// the site, so on a normal view it is a no-op that passes its content
		// through -- nested shortcodes and all.
		await expect( page.locator( '.entry-content' ) ).toContainText( 'only on screen' );

		await page.goto( printUrl( post.link ) );

		await expect( page.locator( 'body.wp-print' ) ).toBeVisible();
		await expect( page.locator( '.entry-content' ) ).not.toContainText( 'only on screen' );
		await expect( page.locator( '.entry-content' ) ).toContainText( 'Before' );
	} );

	test( 'the print view never links to itself', async ( { page, requestUtils } ) => {
		const post = await createPrintablePost( requestUtils );

		await page.goto( post.link );
		await expect( page.locator( '.entry-content svg.WP-PrintIcon' ) ).toHaveCount( 1 );

		await page.goto( printUrl( post.link ) );

		// The same [print_link] that rendered a link a moment ago renders nothing
		// here: a printed page offering to print itself is noise on paper, which
		// is why the print view swaps the shortcode out rather than styling it
		// away.
		await expect( page.locator( '.entry-content svg.WP-PrintIcon' ) ).toHaveCount( 0 );
		await expect( page.locator( '.entry-content a[rel="nofollow"]' ) ).toHaveCount( 0 );
	} );
} );
