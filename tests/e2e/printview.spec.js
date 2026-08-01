/**
 * The printable document itself.
 *
 * This is the half of WP-Print that PHPUnit cannot look at. The print view is
 * not a template fragment: it is a whole HTML document that takes the request
 * over on template_redirect and exits, deliberately without calling wp_head(),
 * so that printing a page does not drag in the theme's stylesheets and every
 * other plugin's assets. Whether that actually happened is a fact about the
 * <head> a browser received.
 *
 * The five content toggles are each tested in both directions in one test,
 * because "images are missing" is true of a broken print view as well as of one
 * with images switched off, and only the pair tells them apart.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	attachThumbnail,
	clearFixtureOption,
	createPrintablePost,
	defaultOptions,
	deleteAttachment,
	deleteOptions,
	printUrl,
	removeThemeOverride,
	setFixtureOption,
	setOptions,
	takeFixtureOption,
	uniqueTitle,
	writeThemeOverride,
} = require( './helpers.js' );

/** A YouTube-shaped embed, so the videos toggle has an iframe to remove. */
const IFRAME = '<iframe src="https://www.example.org/embed/x" width="560" height="315"></iframe>';

/** An image on this site, so the browser really loads it. */
const IMAGE = '<img src="/wp-includes/images/w-logo-blue.png" alt="Logo" />';

/** The shipped defaults, for the disclaimer's exact text. */
let defaults;

/** The disclaimer as a reader sees it, rather than as the row stores it. */
let disclaimerText;

test.describe( 'The printable document', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
		await requestUtils.deleteAllComments();

		deleteOptions();

		defaults = defaultOptions();

		// The shipped default carries &copy;, deliberately: it is stored as an
		// entity so that wp_localize_script() cannot decode it and make Restore
		// Default insert something other than the shipped bytes. What a reader
		// sees is the character, so that is what a text assertion has to compare.
		disclaimerText = defaults.disclaimer.replace( '&copy;', '\u00a9' );
	} );

	test.afterEach( async () => {
		deleteOptions();
	} );

	test( 'the fixture really is a standalone document with only the plugin stylesheet', async ( {
		page,
		requestUtils,
	} ) => {
		const title = uniqueTitle( 'Standalone' );
		const post = await createPrintablePost( requestUtils, { title } );

		await page.goto( printUrl( post.link ) );

		await expect( page.locator( 'body.wp-print' ) ).toBeVisible();

		// One stylesheet, and it is the plugin's. This is the assertion the whole
		// no-wp_head() design exists for: a print view that quietly started
		// pulling the theme in would still render, still look plausible in a
		// screenshot, and print several pages of navigation.
		const sheets = page.locator( 'head link[rel="stylesheet"]' );

		await expect( sheets ).toHaveCount( 1 );
		await expect( sheets ).toHaveAttribute( 'href', /wp-print\/css\/wp-print\.css/ );
		await expect( sheets ).toHaveAttribute( 'media', 'screen, print' );

		// The plugin's own script is there, and it is the only one: the print view
		// registers both handles and prints them by name rather than enqueuing
		// them for a wp_head() it never calls.
		await expect( page.locator( 'head script[src]' ) ).toHaveCount( 1 );
		await expect( page.locator( 'head script[src]' ) ).toHaveAttribute(
			'src',
			/wp-print\/js\/wp-print\.js/,
		);

		// And it announces itself as a print view rather than a second copy of the
		// post for a search engine to index.
		await expect( page ).toHaveTitle( new RegExp( `${ title } . Print$` ) );
		await expect( page.locator( 'head meta[name="robots"]' ) ).toHaveAttribute(
			'content',
			'noindex, nofollow',
		);
		await expect( page.locator( 'head link[rel="canonical"]' ) ).toHaveAttribute(
			'href',
			post.link,
		);
	} );

	test( 'the document carries the post, its byline and where it came from', async ( {
		page,
		requestUtils,
	} ) => {
		const title = uniqueTitle( 'Whole document' );
		const post = await createPrintablePost( requestUtils, {
			title,
			content: 'The body a reader wants on paper. [print_link]',
		} );

		await page.goto( printUrl( post.link ) );

		await expect( page.locator( 'h1.entry-title' ) ).toHaveText( title );
		await expect( page.locator( '.entry-content' ) ).toContainText(
			'The body a reader wants on paper',
		);
		await expect( page.locator( '.entry-date' ) ).toContainText( 'Uncategorized' );

		// A printed page has no back button, so the URL it came from is part of
		// the document rather than a nicety.
		await expect( page.locator( 'footer.footer' ) ).toContainText( 'Article printed from' );
		await expect( page.locator( 'footer.footer' ) ).toContainText( post.link );

		await expect( page.locator( '.wp-print-disclaimer' ) ).toHaveText( disclaimerText );
	} );

	test( 'the links toggle decides whether URLs are footnoted and listed', async ( {
		page,
		requestUtils,
	} ) => {
		const post = await createPrintablePost( requestUtils, {
			content:
				'One <a href="https://example.org/a">first</a>, again <a href="https://example.org/a">second</a>, and <a href="https://example.org/b">other</a>. [print_link]',
		} );

		setOptions( { links: 1 } );

		await page.goto( printUrl( post.link ) );

		const content = page.locator( '.entry-content' );

		// Paper cannot be clicked, which is the entire reason this exists: each
		// link gets a number and the numbers are resolved at the foot.
		await expect( content ).toContainText( '[1]' );
		await expect( content ).toContainText( '[2]' );

		// The same URL twice keeps its first number rather than taking a second
		// one, so the list at the foot has one entry per destination.
		await expect( page.locator( 'p.wp-print-url' ) ).toHaveCount( 2 );
		await expect( page.locator( 'p.wp-print-url' ).first() ).toContainText(
			'https://example.org/a',
		);
		await expect( page.locator( 'footer.footer' ) ).toContainText( 'URLs in this post:' );

		setOptions( { links: 0 } );

		await page.goto( printUrl( post.link ) );

		await expect( page.locator( '.entry-content' ) ).not.toContainText( '[1]' );
		await expect( page.locator( 'p.wp-print-url' ) ).toHaveCount( 0 );
		await expect( page.locator( 'footer.footer' ) ).not.toContainText( 'URLs in this post:' );
	} );

	test( 'a site-relative link is printed as an address somebody could type', async ( {
		page,
		requestUtils,
	} ) => {
		const post = await createPrintablePost( requestUtils, {
			content: 'See <a href="/about-us/">about us</a>. [print_link]',
		} );

		setOptions( { links: 1 } );

		await page.goto( printUrl( post.link ) );

		// "/about-us/" on paper is not an address. The URL list resolves a
		// site-relative href against the home URL, because the printed list is the
		// only way a reader can reach it.
		await expect( page.locator( 'p.wp-print-url' ) ).toContainText( /https?:\/\/[^/]+\/about-us\// );
	} );

	test( 'the images toggle decides whether pictures reach the paper', async ( {
		page,
		requestUtils,
	} ) => {
		const post = await createPrintablePost( requestUtils, {
			content: `Text and a picture. ${ IMAGE } [print_link]`,
		} );

		setOptions( { images: 1 } );

		await page.goto( printUrl( post.link ) );

		await expect( page.locator( '.entry-content img' ) ).toHaveCount( 1 );

		setOptions( { images: 0 } );

		await page.goto( printUrl( post.link ) );

		// Off means gone, not hidden: the point of the setting is saving ink, and
		// display:none would have made no difference to that.
		await expect( page.locator( '.entry-content img' ) ).toHaveCount( 0 );
		await expect( page.locator( '.entry-content' ) ).toContainText( 'Text and a picture' );
	} );

	test( 'the videos toggle decides whether embeds reach the paper', async ( {
		page,
		requestUtils,
	} ) => {
		const post = await createPrintablePost( requestUtils, {
			content: `Watch this. ${ IFRAME } [print_link]`,
		} );

		setOptions( { videos: 1 } );

		await page.goto( printUrl( post.link ) );

		// An embed on paper is a grey rectangle, so the default is off -- but a
		// site that wants it has to be able to have it, and the allow-list the
		// print view escapes with is what decides whether the iframe survives at
		// all.
		await expect( page.locator( '.entry-content iframe' ) ).toHaveCount( 1 );

		setOptions( { videos: 0 } );

		await page.goto( printUrl( post.link ) );

		await expect( page.locator( '.entry-content iframe' ) ).toHaveCount( 0 );
		await expect( page.locator( '.entry-content' ) ).toContainText( 'Watch this' );
	} );

	test( 'the comments toggle decides whether the discussion is printed', async ( {
		page,
		requestUtils,
	} ) => {
		const post = await createPrintablePost( requestUtils );

		await requestUtils.createComment( {
			content: 'A comment worth printing.',
			post: post.id,
		} );

		setOptions( { comments: 1 } );

		await page.goto( printUrl( post.link ) );

		await expect( page.locator( '#comments_box' ) ).toContainText( 'A comment worth printing' );
		await expect( page.locator( '#comments_controls' ) ).toContainText( '1 Comment' );

		setOptions( { comments: 0 } );

		await page.goto( printUrl( post.link ) );

		await expect( page.locator( '#comments_box' ) ).toHaveCount( 0 );

		// The count in the header is not part of the toggle: it is the link a
		// reader follows to the discussion, and it still says how many there are.
		await expect( page.locator( '.entry-date' ) ).toContainText( '1 Comment' );

		await requestUtils.deleteAllComments();
	} );

	test( 'the comment box opens and closes', async ( { page, requestUtils } ) => {
		const post = await createPrintablePost( requestUtils );

		await requestUtils.createComment( { content: 'Toggle me.', post: post.id } );

		setOptions( { comments: 1 } );

		await page.goto( printUrl( post.link ) );

		const box = page.locator( '#comments_box' );

		await expect( box ).toBeVisible();

		// The two controls were inline onclick attributes before 3.0.0, one of
		// them with a javascript: label. They are delegated listeners now, which
		// is a rewrite that a unit test cannot tell succeeded.
		await page.getByRole( 'link', { name: 'Close' } ).click();
		await expect( box ).toBeHidden();

		await page.getByRole( 'link', { name: 'Open' } ).click();
		await expect( box ).toBeVisible();

		await requestUtils.deleteAllComments();
	} );

	test( 'the thumbnail toggle decides whether the featured image is printed', async ( {
		page,
		requestUtils,
	} ) => {
		const post = await createPrintablePost( requestUtils );
		const attachment = attachThumbnail( post.id );

		setOptions( { thumbnail: 1 } );

		await page.goto( printUrl( post.link ) );

		await expect( page.locator( '.thumbnail img' ) ).toHaveCount( 1 );

		setOptions( { thumbnail: 0 } );

		await page.goto( printUrl( post.link ) );

		// Off by default, and off means the wrapper is not printed either -- an
		// empty div is a gap on the page.
		await expect( page.locator( '.thumbnail' ) ).toHaveCount( 0 );

		deleteAttachment( attachment );
	} );

	test( 'the print control asks the browser to print', async ( { page, requestUtils } ) => {
		const post = await createPrintablePost( requestUtils );

		await page.goto( printUrl( post.link ) );

		// window.print() opens a native dialog that would block everything after
		// it, so it is replaced before the click. What is being tested is that the
		// delegated listener runs at all: the control was an inline onclick with a
		// javascript: href until 3.0.0, and a link that quietly navigates to
		// "#print" instead looks identical.
		await page.evaluate( () => {
			window.__printed = 0;
			window.print = () => {
				window.__printed += 1;
			};
		} );

		await page.getByRole( 'link', { name: 'Click here to print.' } ).click();

		expect( await page.evaluate( () => window.__printed ) ).toBe( 1 );
	} );

	test( 'a password-protected post prints neither its body nor its comments', async ( {
		page,
		requestUtils,
	} ) => {
		const post = await createPrintablePost( requestUtils, {
			content: 'The secret body. [print_link]',
			password: 'hunter2',
		} );

		await requestUtils.createComment( { content: 'A secret comment.', post: post.id } );

		setOptions( { comments: 1 } );

		await page.goto( printUrl( post.link ) );

		// The print view is a second route to the same content, so it has to
		// honour the same lock -- otherwise the password is a suggestion. The
		// comment count says so in words rather than printing a number that would
		// itself leak how much discussion is behind the lock.
		await expect( page.locator( '.entry-content' ) ).not.toContainText( 'The secret body' );

		// The comment assertion currently fails, and it is right to: the
		// discussion behind the lock is printed in full.
		//
		// post_content() checks post_password_required() and the comment count
		// says "Comments Hidden", so the body and the count are both guarded --
		// but print-posts.php calls comments_template() with only the Print
		// Comments toggle in front of it, and core's comments_template() has no
		// password check of its own. That check lives in the theme's comments
		// template by convention, and this plugin's copy does not make it.
		await expect( page.locator( 'body' ) ).not.toContainText( 'A secret comment' );
		await expect( page.locator( '.entry-content' ) ).toContainText( 'password protected' );
		await expect( page.locator( '.entry-date' ) ).toContainText( 'Comments Hidden' );

		await requestUtils.deleteAllComments();
	} );

	test( 'a password-protected print view offers somewhere to type the password', async ( {
		page,
		requestUtils,
	} ) => {
		const post = await createPrintablePost( requestUtils, {
			content: 'The secret body. [print_link]',
			password: 'hunter2',
		} );

		await page.goto( printUrl( post.link ) );

		// The plugin returns get_the_password_form() from post_content() on
		// purpose -- there is no other reason to build a form there -- so the
		// document has to end up with a usable one. A prompt with no field is
		// worse than no prompt: it tells the reader to enter a password and then
		// gives them nowhere to do it, and the label left behind points at an
		// input that is not in the document.
		//
		// This currently fails, and it is right to: the prompt arrives with no
		// form and no field. post_content() returns get_the_password_form(), and
		// print_content() then echoes it through wp_kses() with
		// WP_Print_Content::allowed_html( 'post' ) -- which is built on
		// wp_kses_allowed_html( 'post' ), and that list has never contained
		// <form> or <input>. The label survives, the input it points at does
		// not, and the reader is told to type a password into nothing.
		await expect( page.locator( '.entry-content form' ) ).toHaveCount( 1 );
		await expect( page.locator( '.entry-content input[type="password"]' ) ).toHaveCount( 1 );
	} );

	test( 'the comment count says what it means when there are none and when they are closed', async ( {
		page,
		requestUtils,
	} ) => {
		const open = await createPrintablePost( requestUtils );

		await page.goto( printUrl( open.link ) );
		await expect( page.locator( '.entry-date' ) ).toContainText( 'No Comments' );

		const closed = await createPrintablePost( requestUtils, { comment_status: 'closed' } );

		await page.goto( printUrl( closed.link ) );

		// Three different sentences for three different states, because "0
		// comments" on a post that never accepted any is a different fact from
		// "nobody has commented yet".
		await expect( page.locator( '.entry-date' ) ).toContainText( 'Comments Disabled' );
	} );

	test( 'the print view still renders when the option row has never been written', async ( {
		page,
		requestUtils,
	} ) => {
		deleteOptions();

		const post = await createPrintablePost( requestUtils, {
			content: `A fresh install. ${ IMAGE } [print_link]`,
		} );

		await page.goto( printUrl( post.link ) );

		// A fresh install has no row at all, and every reader merges the defaults
		// in rather than assuming one. The shipped defaults are links and images
		// on, comments and videos off, which is what this asserts -- if the merge
		// stopped happening, the toggles would all read as off and the document
		// would quietly lose half its content.
		await expect( page.locator( '.entry-content img' ) ).toHaveCount( 1 );
		await expect( page.locator( '#comments_box' ) ).toHaveCount( 0 );
		await expect( page.locator( '.wp-print-disclaimer' ) ).toHaveText( disclaimerText );
	} );

	test( 'a URL that appears twice keeps one number, and an image link is labelled', async ( {
		page,
		requestUtils,
	} ) => {
		const post = await createPrintablePost( requestUtils, {
			content:
				'<a href="https://example.org/one">First</a> and ' +
				'<a href="https://example.org/one">the same place again</a> and ' +
				'<a href="https://example.org/two">somewhere else</a> and ' +
				`<a href="https://example.org/three">${ IMAGE }</a> [print_link]`,
		} );

		setOptions( { links: 1, images: 1 } );

		await page.goto( printUrl( post.link ) );

		const content = page.locator( '.entry-content' );

		// Numbering is per URL, not per link: a document that renumbered every
		// occurrence would print [1] [2] [3] [4] for three destinations and send
		// the reader looking for a fourth address that is not in the list.
		await expect( content.locator( 'sup' ) ).toHaveCount( 4 );
		await expect( content.locator( 'sup' ).nth( 0 ) ).toHaveText( '[1]' );
		await expect( content.locator( 'sup' ).nth( 1 ) ).toHaveText( '[1]' );
		await expect( content.locator( 'sup' ).nth( 2 ) ).toHaveText( '[2]' );
		await expect( content.locator( 'sup' ).nth( 3 ) ).toHaveText( '[3]' );

		const list = page.locator( '.wp-print-url' );

		await expect( list ).toHaveCount( 3 );
		await expect( list.nth( 0 ) ).toContainText( 'First: https://example.org/one' );

		// A link whose only content is a picture has no words to label it with,
		// and printing the <img> markup as the label would be worse than
		// useless, so the list says Image instead.
		await expect( list.nth( 2 ) ).toContainText( 'Image: https://example.org/three' );
	} );

	test( 'a theme copy of the print template wins over the plugin one', async ( {
		page,
		requestUtils,
	} ) => {
		const marker = uniqueTitle( 'From the theme' );
		const post = await createPrintablePost( requestUtils );

		// The documented promise: drop print-posts.php into your theme and your
		// changes survive an upgrade. The plugin's own copy moved into includes/
		// in 3.0.0 while the theme-side name deliberately did not, so the lookup
		// is doing real translation now rather than just finding the same name
		// twice -- and only a real file on disk can show that it still works.
		// The loop is the theme's job here, exactly as it is in the plugin's own
		// copy: print_content() reads the $pages global, and only the_post()
		// populates it. A template that forgot would render an empty sheet --
		// which is worth saying in the fixture, because it is the first thing a
		// theme author copying this file gets wrong.
		writeThemeOverride(
			'print-posts.php',
			`<?php defined( 'ABSPATH' ) || exit; ?><!DOCTYPE html><html><body class="wp-print">
			<p id="theme-override">${ marker }</p>
			<?php while ( have_posts() ) : the_post(); ?>
				<div class="entry-content"><?php print_content(); ?></div>
			<?php endwhile; ?>
			<p class="wp-print-disclaimer"><?php print_disclaimer(); ?></p>
			</body></html>`,
		);

		try {
			await page.goto( printUrl( post.link ) );

			await expect( page.locator( '#theme-override' ) ).toHaveText( marker );
			await expect( page.locator( '.entry-content' ) ).toContainText( 'The body of the post' );

			// Still the plugin's template tags doing the work inside the theme's
			// file: an override that got the markup but not the content would be
			// a blank sheet of paper.
			await expect( page.locator( '.wp-print-disclaimer' ) ).toHaveText( disclaimerText );
		} finally {
			// A template left in the theme replaces the print view for every
			// test after this one.
			removeThemeOverride( 'print-posts.php' );
		}

		// And the plugin's own copy is back the moment the override is gone,
		// which is the half that proves the override was doing the work.
		await page.goto( printUrl( post.link ) );

		await expect( page.locator( '#theme-override' ) ).toHaveCount( 0 );
		await expect( page.locator( 'h1.entry-title' ) ).toBeVisible();
	} );

	test( 'the allowed-HTML filter can put a tag back into the printed document', async ( {
		page,
		requestUtils,
	} ) => {
		const post = await createPrintablePost( requestUtils, {
			content: '<p>Before</p><marquee class="wp-print-e2e">Scrolling</marquee> [print_link]',
		} );

		await page.goto( printUrl( post.link ) );

		// The default list is wp_kses_post() plus embeds and the glyph, and
		// <marquee> is in none of them -- so the tag goes and its text stays.
		// That is the shape of the complaint the filter exists to answer: a
		// block whose wrapper vanishes on paper with nothing to explain it.
		await expect( page.locator( '.entry-content marquee' ) ).toHaveCount( 0 );
		await expect( page.locator( '.entry-content' ) ).toContainText( 'Scrolling' );

		setFixtureOption( 'wp_print_e2e_allow_tag', 'marquee' );

		try {
			await page.goto( printUrl( post.link ) );

			await expect( page.locator( '.entry-content marquee.wp-print-e2e' ) ).toHaveCount( 1 );

			// The context argument is passed, and it says which side of the
			// document asked. A filter handed the same context for both would be
			// unable to widen the post body without also widening comments.
			expect( takeFixtureOption( 'wp_print_e2e_allow_context_post' ) ).toBe( 'yes' );
		} finally {
			clearFixtureOption( 'wp_print_e2e_allow_tag' );
			clearFixtureOption( 'wp_print_e2e_allow_context_post' );
			clearFixtureOption( 'wp_print_e2e_allow_context_comment' );
		}
	} );
} );
