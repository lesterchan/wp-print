/**
 * WP-Print settings screen.
 *
 * Replaces the inline onchange/onclick attributes and the jQuery dependency the
 * screen carried before 3.0.0. One delegated listener handles both Restore
 * Default buttons, so neither the default strings nor the element ids have to be
 * interpolated into an attribute.
 */
( function() {
	function ready( callback ) {
		if ( document.readyState === 'loading' ) {
			document.addEventListener( 'DOMContentLoaded', callback );
		} else {
			callback();
		}
	}

	ready( function() {
		const style = document.getElementById( 'wp-print-style' );

		// Reveal the custom HTML template only for the Custom style.
		if ( style ) {
			const toggle = function() {
				const target = document.getElementById( style.getAttribute( 'data-print-toggle' ) );

				if ( target ) {
					target.classList.toggle( 'hidden', style.value !== '4' );
				}
			};

			style.addEventListener( 'change', toggle );
			toggle();
		}

		// Restore Default Template, for both the custom HTML and the disclaimer.
		document.addEventListener( 'click', function( event ) {
			const button = event.target.closest( '[data-print-restore]' );

			if ( ! button ) {
				return;
			}

			event.preventDefault();

			const defaults = window.wpPrintDefaults || {};
			const key = button.getAttribute( 'data-print-restore' );
			const target = document.getElementById( button.getAttribute( 'data-print-target' ) );

			if ( target && Object.prototype.hasOwnProperty.call( defaults, key ) ) {
				target.value = defaults[ key ];
			}
		} );
	} );
}() );
