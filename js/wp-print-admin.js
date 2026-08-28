/**
 * WP-Print settings screen. One delegated listener covers both Restore Default
 * buttons, so neither the default strings nor the element ids are interpolated
 * into an attribute.
 */
( function() {
	'use strict';

	function ready( callback ) {
		if ( document.readyState === 'loading' ) {
			document.addEventListener( 'DOMContentLoaded', callback );
		} else {
			callback();
		}
	}

	ready( function() {
		// Restore Default Template, for both the link template and the disclaimer.
		document.addEventListener( 'click', function( event ) {
			const button = event.target.closest( '[data-print-restore]' );

			if ( ! button ) {
				return;
			}

			event.preventDefault();

			const defaults = ( window.wpPrintL10n && window.wpPrintL10n.defaults ) || {};
			const key = button.getAttribute( 'data-print-restore' );
			const target = document.getElementById( button.getAttribute( 'data-print-target' ) );

			if ( target && Object.prototype.hasOwnProperty.call( defaults, key ) ) {
				target.value = defaults[ key ];
			}
		} );
	} );
}() );
