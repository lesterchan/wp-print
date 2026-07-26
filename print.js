/**
 * WP-Print print view.
 *
 * Replaces the inline onclick attributes the print template carried before
 * 3.0.0, including two that used a javascript: label. One delegated listener
 * covers the print prompt and the comment box's Open and Close controls.
 *
 * Loaded with a plain script tag rather than wp_enqueue_script(): the print view
 * is a standalone document that deliberately does not call wp_head(), so that
 * printing a page does not pull in the theme's and every other plugin's assets.
 */
( function() {
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
