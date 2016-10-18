<?php
/*
 * Uninstall plugin
 */
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) )
	exit ();

$option_name = 'print_options';

if ( is_multisite() ) {
	$ms_sites = function_exists( 'get_sites' ) ? get_sites() : wp_get_sites();

	if( 0 < sizeof( $ms_sites ) ) {
		foreach ( $ms_sites as $ms_site ) {
			$blog_id = isset( $ms_site['blog_id'] ) ? $ms_site['blog_id'] : $ms_site->blog_id;
			switch_to_blog( $blog_id );
			delete_option( $option_name );
		}
	}

	restore_current_blog();
} else {
	delete_option( $option_name );
}