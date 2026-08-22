/**
 * The settings screen's script.
 *
 * The two Restore Default buttons read their strings from wpPrintL10n, which is
 * why the defaults are nested one level deep: wp_localize_script() decodes HTML
 * entities in every scalar it is handed, and the shipped disclaimer contains
 * &copy;. A test that only ever used plain text would not notice.
 *
 * There is nothing else left for this script to do. The link template used to be
 * revealed by a four-way style dropdown, and the dropdown went when the three
 * styles that were not the template did.
 */
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import { loadScript, resetDocument } from './helpers.js';

const SHIPPED_TEMPLATE =
	'<a href="%PRINT_URL%" rel="nofollow" title="Print This %POST_TYPE%">%PRINT_ICON% Print This %POST_TYPE%</a>';
const SHIPPED_DISCLAIMER = 'Copyright &copy; 2026 Example. All rights reserved.';

/**
 * The screen's markup, reduced to what the script touches.
 *
 * Both tabs' controls at once: the script is one delegated listener on the
 * document, so which tab a button is drawn on makes no difference to it, and a
 * fixture split in two would only prove that twice.
 */
function renderSettingsScreen() {
	document.body.innerHTML = `
		<textarea id="wp-print-html"></textarea>
		<button type="button" data-print-restore="print_html" data-print-target="wp-print-html">Restore</button>
		<textarea id="wp-print-disclaimer"></textarea>
		<button type="button" data-print-restore="disclaimer" data-print-target="wp-print-disclaimer">Restore</button>
	`;

	loadScript( 'wp-print-admin.js' );
}

beforeEach( () => {
	window.wpPrintL10n = {
		defaults: {
			print_html: SHIPPED_TEMPLATE,
			disclaimer: SHIPPED_DISCLAIMER,
		},
	};
} );

afterEach( () => {
	delete window.wpPrintL10n;
	resetDocument();
} );

describe( 'the settings screen script', () => {
	it( 'restores the shipped link template byte for byte', () => {
		renderSettingsScreen();

		document.querySelector( '[data-print-restore="print_html"]' ).click();

		expect( document.getElementById( 'wp-print-html' ).value ).toBe( SHIPPED_TEMPLATE );
	} );

	it( 'restores the shipped disclaimer with its entity intact', () => {
		renderSettingsScreen();

		document.querySelector( '[data-print-restore="disclaimer"]' ).click();

		expect( document.getElementById( 'wp-print-disclaimer' ).value ).toBe( SHIPPED_DISCLAIMER );
		expect( document.getElementById( 'wp-print-disclaimer' ).value ).toContain( '&copy;' );
	} );

	it( 'prevents the button from submitting the form', () => {
		renderSettingsScreen();

		const event = new window.MouseEvent( 'click', { bubbles: true, cancelable: true } );
		document.querySelector( '[data-print-restore="disclaimer"]' ).dispatchEvent( event );

		expect( event.defaultPrevented ).toBe( true );
	} );

	it( 'leaves the field alone when the localised object is missing', () => {
		delete window.wpPrintL10n;
		renderSettingsScreen();

		const field = document.getElementById( 'wp-print-disclaimer' );
		field.value = 'Mine';

		document.querySelector( '[data-print-restore="disclaimer"]' ).click();

		expect( field.value ).toBe( 'Mine' );
	} );

	it( 'ignores a click that is not on a Restore button', () => {
		renderSettingsScreen();

		const field = document.getElementById( 'wp-print-disclaimer' );
		field.value = 'Mine';

		document.getElementById( 'wp-print-html' ).click();

		expect( field.value ).toBe( 'Mine' );
	} );

	it( 'does nothing on a screen with no Restore buttons at all', () => {
		document.body.innerHTML = '<p>Nothing to restore.</p>';

		expect( () => loadScript( 'wp-print-admin.js' ) ).not.toThrow();
	} );
} );
