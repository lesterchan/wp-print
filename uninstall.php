<?php
/*
 * Uninstall plugin
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

function print_uninstall_site() {
	delete_option( 'print_options' );
}

if ( is_multisite() ) {
	// 'number' => 0 is required: WP_Site_Query defaults to 100, so without it the
	// options are left behind on every site past the hundredth and uninstall still
	// reports success. 'fields' => 'ids' avoids hydrating WP_Site objects the loop
	// does not use. wp_get_sites() is gone - removed in WordPress 5.1 - so the old
	// fallback would fatal on a multisite uninstall rather than merely skip sites.
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
