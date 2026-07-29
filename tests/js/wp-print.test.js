/**
 * The print view's script.
 *
 * Everything here used to be an inline onclick attribute, two of them carrying a
 * javascript: label, so the assertions are as much about the data-* contract the
 * template now relies on as about the behaviour.
 */
import { afterEach, describe, expect, it, vi } from 'vitest';

import { loadScript, resetDocument } from './helper-load.js';

/**
 * The markup the print template emits, reduced to what the script touches.
 */
function renderPrintView() {
	document.body.innerHTML = `
		<span id="comments_controls">
			<a href="#comments_box" data-print-action="open" data-print-target="comments_box">Open</a>
			<a href="#comments_box" data-print-action="close" data-print-target="comments_box">Close</a>
		</span>
		<div id="comments_box"></div>
		<p id="print-link">
			<a href="#print" data-print-action="print">Click here to print.</a>
		</p>
		<p><a href="#somewhere-else" id="ordinary">An ordinary link</a></p>
	`;

	loadScript( 'wp-print.js' );
}

afterEach( () => {
	resetDocument();
} );

describe( 'the print view script', () => {
	it( 'opens the print dialogue when the print link is clicked', () => {
		renderPrintView();
		window.print = vi.fn();

		const event = new window.MouseEvent( 'click', { bubbles: true, cancelable: true } );
		document.querySelector( '[data-print-action="print"]' ).dispatchEvent( event );

		expect( window.print ).toHaveBeenCalledTimes( 1 );
		expect( event.defaultPrevented ).toBe( true );
	} );

	it( 'hides and shows the comment box from its Open and Close controls', () => {
		renderPrintView();
		const box = document.getElementById( 'comments_box' );

		document.querySelector( '[data-print-action="close"]' ).click();
		expect( box.style.display ).toBe( 'none' );

		document.querySelector( '[data-print-action="open"]' ).click();
		expect( box.style.display ).toBe( 'block' );
	} );

	it( 'reads the target from the element rather than assuming an id', () => {
		document.body.innerHTML = `
			<a href="#other" data-print-action="open" data-print-target="other_box">Open</a>
			<div id="other_box" style="display: none;"></div>
		`;
		loadScript( 'wp-print.js' );

		document.querySelector( '[data-print-action="open"]' ).click();

		expect( document.getElementById( 'other_box' ).style.display ).toBe( 'block' );
	} );

	it( 'leaves an ordinary link alone', () => {
		renderPrintView();
		window.print = vi.fn();

		const event = new window.MouseEvent( 'click', { bubbles: true, cancelable: true } );
		document.getElementById( 'ordinary' ).dispatchEvent( event );

		expect( window.print ).not.toHaveBeenCalled();
		expect( event.defaultPrevented ).toBe( false );
	} );

	it( 'does nothing when the named target is not on the page', () => {
		document.body.innerHTML =
			'<a href="#gone" data-print-action="open" data-print-target="gone">Open</a>';
		loadScript( 'wp-print.js' );

		const event = new window.MouseEvent( 'click', { bubbles: true, cancelable: true } );
		document.querySelector( '[data-print-action="open"]' ).dispatchEvent( event );

		// Nothing to toggle, so the anchor is left to behave as an anchor.
		expect( event.defaultPrevented ).toBe( false );
	} );
} );
