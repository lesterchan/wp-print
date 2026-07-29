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
 * The two legacy rows go too. Deleting a plugin that was never opened in
 * wp-admin after the upgrade would otherwise leave them behind, because the
 * migration that folds them in runs on admin_init and would never have fired.
 *
 * The row names are spelled out rather than read from WP_Print_Options: this
 * file runs with the plugin inactive, so none of its classes are loaded.
 *
 * @return void
 */
function wp_print_uninstall_site() {
	delete_option( 'wp_print_options' );
	delete_option( 'wp_print_version' );
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
		wp_print_uninstall_site();
		// Inside the loop: switch_to_blog() pushes onto a stack, so restoring once
		// after the loop would leave it unwound by all but one entry.
		restore_current_blog();
	}
} else {
	wp_print_uninstall_site();
}
