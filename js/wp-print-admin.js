/**
 * WP-Print settings screen.
 *
 * Vanilla, with no dependencies at all. Replaces the inline onchange/onclick
 * attributes and the framework the screen leaned on before 3.0.0. One delegated
 * listener handles both Restore Default buttons, so neither the default strings
 * nor the element ids have to be interpolated into an attribute.
 *
 * There is nothing to show or hide any more: the link template used to be
 * revealed by a four-way style dropdown, and the dropdown is gone along with the
 * three styles that were not the template.
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
