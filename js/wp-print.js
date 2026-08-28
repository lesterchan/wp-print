/**
 * WP-Print print view. One delegated listener covers the print prompt and the
 * comment box's controls.
 *
 * Loaded with a plain script tag: the print view does not call wp_head(), so
 * printing pulls in no theme or plugin assets.
 */
( function() {
	'use strict';

	document.addEventListener( 'click', function( event ) {
		const trigger = event.target.closest( '[data-print-action]' );

		if ( ! trigger ) {
			return;
		}

		const action = trigger.getAttribute( 'data-print-action' );

		if ( action === 'print' ) {
			event.preventDefault();
			window.print();

			return;
		}

		if ( action !== 'open' && action !== 'close' ) {
			return;
		}

		const box = document.getElementById( trigger.getAttribute( 'data-print-target' ) || 'comments_box' );

		if ( box ) {
			event.preventDefault();
			box.style.display = action === 'open' ? 'block' : 'none';
		}
	} );
}() );
