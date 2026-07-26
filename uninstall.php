<?php
/**
 * Uninstaller: removes everything the plugin stored.
 *
 * @package WP-Print
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Delete the plugin's options for the current site.
 *
 * @return void
 */
function print_uninstall_site() {
	delete_option( 'print_options' );
	delete_option( 'print_db_version' );
}

if ( is_multisite() ) {
	// 'number' => 0 is required: WP_Site_Query defaults to 100, so without it the
	// options are left behind on every site past the hundredth and uninstall still
	// reports success. 'fields' => 'ids' avoids hydrating WP_Site objects the loop
	// does not use.
	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		print_uninstall_site();
		// Inside the loop: switch_to_blog() pushes onto a stack, so restoring once
		// after the loop would leave it unwound by all but one entry.
		restore_current_blog();
	}
} else {
	print_uninstall_site();
}
