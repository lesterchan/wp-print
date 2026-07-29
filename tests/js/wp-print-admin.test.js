/**
 * The settings screen's script.
 *
 * The two Restore Default buttons read their strings from wpPrintL10n, which is
 * why the defaults are nested one level deep: wp_localize_script() decodes HTML
 * entities in every scalar it is handed, and the shipped disclaimer contains
 * &copy;. A test that only ever used plain text would not notice.
 */
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import { loadScript, resetDocument } from './helper-load.js';

const SHIPPED_TEMPLATE =
	'<a href="%PRINT_URL%" rel="nofollow" title="%PRINT_TEXT%">%PRINT_TEXT%</a>';
const SHIPPED_DISCLAIMER = 'Copyright &copy; 2026 Example. All rights reserved.';

/**
 * The screen's markup, reduced to what the script touches.
 *
 * @param {string} style The selected print style.
 */
function renderSettingsScreen( style = '1' ) {
	document.body.innerHTML = `
		<select id="wp-print-style" data-print-toggle="wp-print-custom">
			<option value="1">Print Icon With Text Link</option>
			<option value="2">Print Icon Only</option>
			<option value="3">Print Text Link Only</option>
			<option value="4">Custom</option>
		</select>
		<div id="wp-print-custom" class="wp-print-custom hidden">
			<textarea id="wp-print-html"></textarea>
			<button type="button" data-print-restore="print_html" data-print-target="wp-print-html">Restore</button>
		</div>
		<textarea id="wp-print-disclaimer"></textarea>
		<button type="button" data-print-restore="disclaimer" data-print-target="wp-print-disclaimer">Restore</button>
	`;

	document.getElementById( 'wp-print-style' ).value = style;

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
	it( 'hides the custom template block for every style but Custom', () => {
		renderSettingsScreen( '1' );

		expect( document.getElementById( 'wp-print-custom' ).classList.contains( 'hidden' ) ).toBe(
			true,
		);
	} );

	it( 'reveals the custom template block as soon as Custom is selected', () => {
		renderSettingsScreen( '1' );

		const style = document.getElementById( 'wp-print-style' );
		style.value = '4';
		style.dispatchEvent( new window.Event( 'change' ) );

		expect( document.getElementById( 'wp-print-custom' ).classList.contains( 'hidden' ) ).toBe(
			false,
		);
	} );

	it( 'reveals it on load when Custom is already the stored style', () => {
		renderSettingsScreen( '4' );

		expect( document.getElementById( 'wp-print-custom' ).classList.contains( 'hidden' ) ).toBe(
			false,
		);
	} );

	it( 'hides it again when the style is changed away from Custom', () => {
		renderSettingsScreen( '4' );

		const style = document.getElementById( 'wp-print-style' );
		style.value = '2';
		style.dispatchEvent( new window.Event( 'change' ) );

		expect( document.getElementById( 'wp-print-custom' ).classList.contains( 'hidden' ) ).toBe(
			true,
		);
	} );

	it( 'restores the shipped link template byte for byte', () => {
		renderSettingsScreen( '4' );

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

		document.getElementById( 'wp-print-style' ).click();

		expect( field.value ).toBe( 'Mine' );
	} );

	it( 'does nothing on a screen that has no style dropdown', () => {
		document.body.innerHTML = '<p>Nothing to toggle.</p>';

		expect( () => loadScript( 'wp-print-admin.js' ) ).not.toThrow();
	} );
} );
