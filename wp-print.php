<?php
/*
Plugin Name: WP-Print
Plugin URI: https://lesterchan.net/portfolio/programming/php/
Description: Displays a printable version of your WordPress blog's post/page.
Version: 2.58.3
Author: Lester 'GaMerZ' Chan
Author URI: https://lesterchan.net
Text Domain: wp-print
*/

/*
	Copyright 2026  Lester Chan  (email : lesterchan@gmail.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


// Create Text Domain For Translations
add_action( 'plugins_loaded', 'print_textdomain' );
function print_textdomain() {
	load_plugin_textdomain( 'wp-print' );
}


// Function: Default Option Values
function print_default_options() {
	return array(
		'post_text'   => __( 'Print This Post', 'wp-print' ),
		'page_text'   => __( 'Print This Page', 'wp-print' ),
		'print_icon'  => 'print.gif',
		'print_style' => 1,
		'print_html'  => '<a href="%PRINT_URL%" rel="nofollow" title="%PRINT_TEXT%">%PRINT_TEXT%</a>',
		'comments'    => 0,
		'links'       => 1,
		'images'      => 1,
		'thumbnail'   => 0,
		'videos'      => 0,
		'disclaimer'  => sprintf( __( 'Copyright &copy; %1$s %2$s. All rights reserved.', 'wp-print' ), current_time( 'Y' ), get_option( 'blogname' ) ),
	);
}


// Function: Read The Options, Merged Over The Defaults
// Merging on read is what lets an install upgraded from a version that predates a
// key pick that key up. Without it, reading one raises "Undefined array key" and
// renders an empty print link.
function print_get_options() {
	$print_options = get_option( 'print_options' );

	if ( ! is_array( $print_options ) ) {
		$print_options = array();
	}

	return array_merge( print_default_options(), $print_options );
}


// Function: Print Option Menu
add_action( 'admin_menu', 'print_menu' );
function print_menu() {
	add_options_page( __( 'Print', 'wp-print' ), __( 'Print', 'wp-print' ), 'manage_options', 'wp-print/print-options.php' );
}


// Function: Add htaccess Rewrite Endpoint - this handles all the rules
add_action( 'init', 'wp_print_endpoint' );
function wp_print_endpoint() {
	add_rewrite_endpoint( 'print', EP_PERMALINK | EP_PAGES );
}


// Function: Print Public Variables
add_filter( 'query_vars', 'print_variables' );
function print_variables( $public_query_vars ) {
	$public_query_vars[] = 'print';
	return $public_query_vars;
}


// Function: Display Print Link
function print_link( $print_post_text = '', $print_page_text = '', $echo = true ) {
	$polyglot_append = '';
	if ( function_exists( 'polyglot_get_lang' ) ) {
		global $polyglot_settings;
		$polyglot_append = $polyglot_settings['uri_helpers']['lang_view'] . '/' . polyglot_get_lang() . '/';
	}
	$output          = '';
	$using_permalink = get_option( 'permalink_structure' );
	$print_options   = print_get_options();
	$print_style     = intval( $print_options['print_style'] );
	if ( empty( $print_post_text ) ) {
		$print_text = stripslashes( $print_options['post_text'] );
	} else {
		$print_text = $print_post_text;
	}
	$print_icon = plugins_url( 'wp-print/images/' . $print_options['print_icon'] );
	$print_link = get_permalink();
	$print_html = stripslashes( $print_options['print_html'] );
	// Fix For Static Page
	if ( get_option( 'show_on_front' ) === 'page' && is_page() ) {
		if ( (int) get_option( 'page_on_front' ) > 0 ) {
			$print_link = _get_page_link();
		}
	}
	if ( ! empty( $using_permalink ) ) {
		if ( substr( $print_link, -1, 1 ) != '/' ) {
			$print_link = $print_link . '/';
		}
		if ( is_page() ) {
			if ( empty( $print_page_text ) ) {
				$print_text = stripslashes( $print_options['page_text'] );
			} else {
				$print_text = $print_page_text;
			}
		}
		$print_link = $print_link . 'print/' . $polyglot_append;
	} else {
		if ( is_page() ) {
			if ( empty( $print_page_text ) ) {
				$print_text = stripslashes( $print_options['page_text'] );
			} else {
				$print_text = $print_page_text;
			}
		}
		$print_link = $print_link . '&amp;print=1';
	}
	unset( $print_options );
	$print_link_esc  = esc_url( $print_link );
	$print_icon_esc  = esc_url( $print_icon );
	$print_text_attr = esc_attr( $print_text );
	switch ( $print_style ) {
		// Icon + Text Link
		case 1:
			$output = '<a href="' . $print_link_esc . '" title="' . $print_text_attr . '" rel="nofollow"><img class="WP-PrintIcon" src="' . $print_icon_esc . '" alt="' . $print_text_attr . '" title="' . $print_text_attr . '" style="border: 0px;" /></a>&nbsp;<a href="' . $print_link_esc . '" title="' . $print_text_attr . '" rel="nofollow">' . $print_text . '</a>';
			break;
		// Icon Only
		case 2:
			$output = '<a href="' . $print_link_esc . '" title="' . $print_text_attr . '" rel="nofollow"><img class="WP-PrintIcon" src="' . $print_icon_esc . '" alt="' . $print_text_attr . '" title="' . $print_text_attr . '" style="border: 0px;" /></a>';
			break;
		// Text Link Only
		case 3:
			$output = '<a href="' . $print_link_esc . '" title="' . $print_text_attr . '" rel="nofollow">' . $print_text . '</a>';
			break;
		case 4:
			$print_html = str_replace( '%PRINT_URL%', $print_link_esc, $print_html );
			$print_html = str_replace( '%PRINT_TEXT%', $print_text, $print_html );
			$print_html = str_replace( '%PRINT_ICON_URL%', $print_icon_esc, $print_html );
			$output     = $print_html;
			break;
	}
	if ( $echo ) {
		echo $output . "\n";
	} else {
		return $output;
	}
}


// Function: Short Code For Inserting Prink Links Into Posts/Pages
add_shortcode( 'print_link', 'print_link_shortcode' );
function print_link_shortcode( $atts ) {
	if ( ! is_feed() ) {
		return print_link( '', '', false );
	} else {
		return __( 'Note: There is a print link embedded within this post, please visit this post to print it.', 'wp-print' );
	}
}
function print_link_shortcode2( $atts ) {
	return;
}


// Function: Short Code For DO NOT PRINT Content
add_shortcode( 'donotprint', 'print_donotprint_shortcode' );
function print_donotprint_shortcode( $atts, $content = null ) {
	return do_shortcode( $content );
}
function print_donotprint_shortcode2( $atts, $content = null ) {
	return;
}


// Function: Print Content
function print_content( $display = true ) {
	global $links_text, $link_number, $max_link_number, $matched_links, $pages, $multipage, $numpages, $post;
	if ( ! isset( $matched_links ) ) {
		$matched_links = array();
	}
	$content = '';
	if ( post_password_required() ) {
		$content = get_the_password_form();
	} else {
		// $pages is only populated by setup_postdata(). Guard it so a print view
		// reached outside the loop degrades to empty content instead of a warning.
		$pages = is_array( $pages ) ? $pages : array();
		if ( $multipage ) {
			for ( $page = 0; $page < $numpages; $page++ ) {
				if ( isset( $pages[ $page ] ) ) {
					$content .= $pages[ $page ];
				}
			}
		} else {
			$content = isset( $pages[0] ) ? $pages[0] : '';
		}
		if ( function_exists( 'email_rewrite' ) ) {
			remove_shortcode( 'donotemail' );
			add_shortcode( 'donotemail', 'email_donotemail_shortcode2' );
		}
		remove_shortcode( 'donotprint' );
		add_shortcode( 'donotprint', 'print_donotprint_shortcode2' );
		remove_shortcode( 'print_link' );
		add_shortcode( 'print_link', 'print_link_shortcode2' );
		$content = apply_filters( 'the_content', $content );
		$content = str_replace( ']]>', ']]&gt;', $content );
		if ( ! print_can( 'images' ) ) {
			$content = remove_image( $content );
		}
		if ( ! print_can( 'videos' ) ) {
			$content = remove_video( $content );
		}
		if ( print_can( 'links' ) ) {
			preg_match_all( '/<a(.+?)href=[\"\'](.+?)[\"\'](.*?)>(.+?)<\/a>/', $content, $matches );
			for ( $i = 0; $i < count( $matches[0] ); $i++ ) {
				$link_match = $matches[0][ $i ];
				$link_url   = $matches[2][ $i ];
				if ( substr( $link_url, 0, 2 ) == '//' ) {
					$link_url = ( is_ssl() ? 'https:' : 'http:' ) . $link_url;
				} elseif ( stristr( $link_url, 'https://' ) ) {
					$link_url = ( strtolower( substr( $link_url, 0, 8 ) ) != 'https://' ) ? get_option( 'home' ) . $link_url : $link_url;
				} elseif ( stristr( $link_url, 'mailto:' ) ) {
					$link_url = ( strtolower( substr( $link_url, 0, 7 ) ) != 'mailto:' ) ? get_option( 'home' ) . $link_url : $link_url;
				} elseif ( $link_url[0] == '#' ) {
					$link_url = $link_url;
				} else {
					$link_url = ( strtolower( substr( $link_url, 0, 7 ) ) != 'http://' ) ? get_option( 'home' ) . $link_url : $link_url;
				}
				$link_text     = $matches[4][ $i ];
				$new_link      = true;
				$link_url_hash = md5( $link_url );
				if ( ! isset( $matched_links[ $link_url_hash ] ) ) {
					$link_number                     = ++$max_link_number;
					$matched_links[ $link_url_hash ] = $link_number;
				} else {
					$new_link    = false;
					$link_number = $matched_links[ $link_url_hash ];
				}
				// The URL is rebuilt from the post's own markup above, so escape it at
				// both sinks: esc_url() for the attribute, esc_html() for the printed
				// list. Without them a quote in an href closes the attribute early.
				$content = str_replace_one( $link_match, '<a href="' . esc_url( $link_url ) . '" rel="external">' . $link_text . '</a> <sup>[' . number_format_i18n( $link_number ) . ']</sup>', $content );
				if ( $new_link ) {
					if ( preg_match( '/<img(.+?)src=[\"\'](.+?)[\"\'](.*?)>/', $link_text ) ) {
						$links_text .= '<p style="margin: 2px 0;">[' . number_format_i18n( $link_number ) . '] ' . __( 'Image', 'wp-print' ) . ': <strong><span dir="ltr">' . esc_html( $link_url ) . '</span></strong></p>';
					} else {
						$links_text .= '<p style="margin: 2px 0;">[' . number_format_i18n( $link_number ) . '] ' . $link_text . ': <strong><span dir="ltr">' . esc_html( $link_url ) . '</span></strong></p>';
					}
				}
			}
		}
	}
	if ( $display ) {
		echo $content;
	} else {
		return $content;
	}
}


// Function: Print Categories
// get_the_category_list() was being joined with ',' and then split on ', ', so the
// split never matched and the whole list came back as one element: $before/$after
// wrapped the entire run of categories instead of each one, and the separator lost
// its space. Splitting on the same string that was used to join fixes both. The
// separator is also no longer run through __(), because a bare ',' gives
// translators no context and reads as an untranslatable string.
function print_categories( $before = '', $after = '' ) {
	$categories = strip_tags( get_the_category_list( ',' ) );
	$categories = explode( ',', $categories );
	$categories = array_filter( array_map( 'trim', $categories ), 'strlen' );

	echo $before . implode( $after . ', ' . $before, $categories ) . $after;
}


// Function: Print Comments Content
function print_comments_content( $display = true ) {
	global $links_text, $link_number, $max_link_number, $matched_links;
	if ( ! isset( $matched_links ) ) {
		$matched_links = array();
	}
	$content = get_comment_text();
	$content = apply_filters( 'comment_text', $content, null, array() );
	if ( ! print_can( 'images' ) ) {
		$content = remove_image( $content );
	}
	if ( ! print_can( 'videos' ) ) {
		$content = remove_video( $content );
	}
	if ( print_can( 'links' ) ) {
		preg_match_all( '/<a(.+?)href=[\"\'](.+?)[\"\'](.*?)>(.+?)<\/a>/', $content, $matches );
		for ( $i = 0; $i < count( $matches[0] ); $i++ ) {
			$link_match = $matches[0][ $i ];
			$link_url   = $matches[2][ $i ];
			$link_text  = $matches[4][ $i ];
			if ( stristr( $link_url, 'https://' ) ) {
				$link_url = ( strtolower( substr( $link_url, 0, 8 ) ) != 'https://' ) ? get_option( 'home' ) . $link_url : $link_url;
			} elseif ( stristr( $link_url, 'mailto:' ) ) {
				$link_url = ( strtolower( substr( $link_url, 0, 7 ) ) != 'mailto:' ) ? get_option( 'home' ) . $link_url : $link_url;
			} elseif ( $link_url[0] == '#' ) {
				$link_url = $link_url;
			} else {
				$link_url = ( strtolower( substr( $link_url, 0, 7 ) ) != 'http://' ) ? get_option( 'home' ) . $link_url : $link_url;
			}
			$new_link      = true;
			$link_url_hash = md5( $link_url );
			if ( ! isset( $matched_links[ $link_url_hash ] ) ) {
				$link_number                     = ++$max_link_number;
				$matched_links[ $link_url_hash ] = $link_number;
			} else {
				$new_link    = false;
				$link_number = $matched_links[ $link_url_hash ];
			}
			// Escaped at both sinks, as in print_content() above.
			$content = str_replace_one( $link_match, '<a href="' . esc_url( $link_url ) . '" rel="external">' . $link_text . '</a> <sup>[' . number_format_i18n( $link_number ) . ']</sup>', $content );
			if ( $new_link ) {
				if ( preg_match( '/<img(.+?)src=[\"\'](.+?)[\"\'](.*?)>/', $link_text ) ) {
					$links_text .= '<p style="margin: 2px 0;">[' . number_format_i18n( $link_number ) . '] ' . __( 'Image', 'wp-print' ) . ': <strong><span dir="ltr">' . esc_html( $link_url ) . '</span></strong></p>';
				} else {
					$links_text .= '<p style="margin: 2px 0;">[' . number_format_i18n( $link_number ) . '] ' . $link_text . ': <strong><span dir="ltr">' . esc_html( $link_url ) . '</span></strong></p>';
				}
			}
		}
	}
	if ( $display ) {
		echo $content;
	} else {
		return $content;
	}
}


// Function: Print Comments
function print_comments_number() {
	global $post;
	$comment_status = $post->comment_status;
	if ( $comment_status == 'open' ) {
		$num_comments = get_comments_number();
		if ( $num_comments == 0 ) {
			$comment_text = __( 'No Comments', 'wp-print' );
		} else {
			$comment_text = sprintf( _n( '%s Comment', '%s Comments', $num_comments, 'wp-print' ), number_format_i18n( $num_comments ) );
		}
	} else {
		$comment_text = __( 'Comments Disabled', 'wp-print' );
	}
	if ( post_password_required() ) {
		_e( 'Comments Hidden', 'wp-print' );
	} else {
		echo $comment_text;
	}
}


// Function: Print Links
function print_links( $text_links = '' ) {
	global $links_text;
	if ( empty( $text_links ) ) {
		$text_links = __( 'URLs in this post:', 'wp-print' );
	}
	if ( ! empty( $links_text ) ) {
		echo $text_links . $links_text;
	}
}


// Function: Load WP-Print
add_action( 'template_redirect', 'wp_print', 5 );
function wp_print() {
	global $wp_query;
	if ( array_key_exists( 'print', $wp_query->query_vars ) ) {
		include WP_PLUGIN_DIR . '/wp-print/print.php';
		exit();
	}
}


// Function: Add Print Comments Template
function print_template_comments() {
	if ( file_exists( get_stylesheet_directory() . '/print-comments.php' ) ) {
		$file = get_stylesheet_directory() . '/print-comments.php';
	} else {
		$file = WP_PLUGIN_DIR . '/wp-print/print-comments.php';
	}
	return $file;
}


// Function: Print Page Title
function print_pagetitle( $page_title ) {
	$page_title .= ' &raquo; ' . __( 'Print', 'wp-print' );
	return $page_title;
}


// Function: Can Print?
function print_can( $type ) {
	$print_options = print_get_options();
	if ( isset( $print_options[ $type ] ) ) {
		return (int) $print_options[ $type ];
	}

	return 0;
}


// Function: Remove Image From Text
function remove_image( $content ) {
	$content = preg_replace( '/<img(.+?)src=[\"\'](.+?)[\"\'](.*?)>/', '', $content );
	return $content;
}


// Function: Remove Video From Text
function remove_video( $content ) {
	$content = preg_replace( '/<object[^>]*?>.*?<\/object>/', '', $content );
	$content = preg_replace( '/<embed[^>]*?>.*?<\/embed>/', '', $content );
	$content = preg_replace( '/<iframe[^>]*?>.*?<\/iframe>/', '', $content );
	return $content;
}


// Function: Replace One Time Only
function str_replace_one( $search, $replace, $content ) {
	$pos = strpos( $content, $search );

	// Compare against false explicitly. A match at offset 0 is a valid match, but
	// 0 is falsy, so the old truthiness test silently skipped the replacement for
	// any content that began with the string being replaced - which is what
	// happens to the first link in a post whose theme has turned wpautop off.
	if ( false !== $pos ) {
		return substr( $content, 0, $pos ) . $replace . substr( $content, $pos + strlen( $search ) );
	} else {
		return $content;
	}
}


// Function: Activate Plugin
register_activation_hook( __FILE__, 'print_activation' );
function print_activation( $network_wide ) {
	$option_name = 'print_options';
	$option      = print_default_options();

	if ( is_multisite() && $network_wide ) {
		// 'number' => 0 is required: WP_Site_Query defaults to 100, so without it a
		// network larger than that silently leaves every site past the hundredth
		// unconfigured. 'fields' => 'ids' avoids hydrating WP_Site objects the loop
		// does not use. wp_get_sites() is gone - it was removed in WordPress 5.1 -
		// so the old fallback would fatal rather than merely skip sites.
		$site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );
			add_option( $option_name, $option );
			print_activate();
			// Inside the loop: switch_to_blog() pushes onto a stack, so restoring
			// once after the loop would leave it unwound by all but one entry.
			restore_current_blog();
		}
	} else {
		add_option( $option_name, $option );
		print_activate();
	}
}

function print_activate() {
	flush_rewrite_rules();
}
