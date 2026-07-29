/**
 * Load one of the plugin's scripts into the jsdom document.
 *
 * The two scripts ship as plain IIFEs, not modules -- there is no build step and
 * no bundler -- so importing them would not be testing what users run. They are
 * compiled and called instead, which leaves `window` and `document` resolving to
 * the same objects the test sees. Appending a <script> element is closer to what
 * a browser does, but jsdom evaluates that in its own realm, and a value the
 * test puts on `window` -- wpPrintL10n, a stubbed window.print -- never reaches
 * the script.
 *
 * Both scripts attach a delegated listener to `document`, which outlives a body
 * reset, so every listener added while a script loads is recorded and taken off
 * again between tests. Without that, the second test in a file would see the
 * first test's copy of the handler as well as its own.
 */
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join( dirname( fileURLToPath( import.meta.url ) ), '..', '..' );

let attached = [];

/**
 * Evaluate a plugin script in the current document.
 *
 * @param {string} file File name inside js/, for instance 'wp-print.js'.
 */
export function loadScript( file ) {
	const original = document.addEventListener.bind( document );

	document.addEventListener = ( type, listener, options ) => {
		attached.push( [ type, listener, options ] );
		original( type, listener, options );
	};

	// Not a module, so it is compiled and called rather than imported.
	const run = new Function( readFileSync( join( root, 'js', file ), 'utf8' ) );
	run();

	document.addEventListener = original;
}

/**
 * Put the document back the way an empty page would be.
 */
export function resetDocument() {
	attached.forEach( ( [ type, listener, options ] ) =>
		document.removeEventListener( type, listener, options ),
	);
	attached = [];

	document.head.innerHTML = '';
	document.body.innerHTML = '';
}
