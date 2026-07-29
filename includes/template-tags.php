<?php
/**
 * Template tags.
 *
 * The plugin's public API. Themes call these from their own templates and from
 * their copies of print-posts.php and print-comments.php, so the names, the
 * argument order and the return values are all fixed - they are what the readme
 * has documented for the plugin's whole life.
 *
 * @package WP-Print
 */

defined( 'ABSPATH' ) || exit;

/**
 * Display or return the print link for the current post or page.
 *
 * @param string $print_post_text Optional. Link text on a post. Default the stored option.
 * @param string $print_page_text Optional. Link text on a page. Default the stored option.
 * @param bool   $echo            Optional. Whether to print. Default true.
 * @return string|void The markup when $echo is false.
 */
function print_link( $print_post_text = '', $print_page_text = '', $echo = true ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase, Universal.NamingConventions.NoReservedKeywordParameterNames.echoFound -- Named $echo since the plugin's first release; renaming it would break named arguments.
	$output = WP_Print_Link::render( $print_post_text, $print_page_text );

	if ( ! $echo ) {
		return $output;
	}

	echo $output . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped at each sink in WP_Print_Link::render().
}

/**
 * Display or return the post content, prepared for printing.
 *
 * @param bool $display Optional. Whether to print. Default true.
 * @return string|void The content when $display is false.
 */
function print_content( $display = true ) {
	$content = WP_Print_Content::post_content();

	if ( ! $display ) {
		return $content;
	}

	echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Post content, already through the_content and escaped at each sink.
}

/**
 * Display the current comment's content, prepared for printing.
 *
 * @param bool $display Optional. Whether to print. Default true.
 * @return string|void The content when $display is false.
 */
function print_comments_content( $display = true ) {
	$content = WP_Print_Content::comment_content();

	if ( ! $display ) {
		return $content;
	}

	echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Comment content, already through comment_text and escaped at each sink.
}

/**
 * Display the categories the post is filed under.
 *
 * @param string $before Optional. Markup before each category.
 * @param string $after  Optional. Markup after each category.
 * @return void
 */
function print_categories( $before = '', $after = '' ) {
	echo WP_Print_Content::categories( $before, $after ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Category names are stripped of tags; $before and $after are the caller's markup.
}

/**
 * Display the comment count as a sentence.
 *
 * @return void
 */
function print_comments_number() {
	echo esc_html( WP_Print_Content::comments_number() );
}

/**
 * Display the collected list of URLs found in the content.
 *
 * @param string $text_links Optional. Heading for the list.
 * @return void
 */
function print_links( $text_links = '' ) {
	$links_text = WP_Print_Content::links_text();

	if ( '' === $links_text ) {
		return;
	}

	if ( '' === $text_links ) {
		$text_links = __( 'URLs in this post:', 'wp-print' );
	}

	echo $text_links . $links_text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Heading is the caller's markup; URLs are escaped as they are collected.
}

/**
 * Display the disclaimer or copyright notice.
 *
 * @return void
 */
function print_disclaimer() {
	echo wp_kses_post( WP_Print_Options::get( 'disclaimer' ) );
}

/**
 * Whether a print option is switched on.
 *
 * @param string $type One of comments, links, images, thumbnail, videos.
 * @return int 1 or 0.
 */
function print_can( $type ) {
	return WP_Print_Options::can( $type );
}

/**
 * All print options, merged over the defaults.
 *
 * @return array
 */
function print_get_options() {
	return WP_Print_Options::get();
}

/**
 * The default print options.
 *
 * @return array
 */
function print_default_options() {
	return WP_Print_Options::get_defaults();
}

/**
 * The path to the printable comments template.
 *
 * @return string
 */
function print_template_comments() {
	return WP_Print_Template::comments_template();
}

/**
 * Append the print suffix to a document title.
 *
 * @param string $page_title Title so far.
 * @return string
 */
function print_pagetitle( $page_title ) {
	return WP_Print_Template::page_title( $page_title );
}

/**
 * Render the print view when the print endpoint was requested.
 *
 * Themes guard their call to print_link() with function_exists( 'wp_print' ), so
 * this function has to keep existing under this name even though the plugin no
 * longer calls it directly.
 *
 * @return void
 */
function wp_print() {
	WP_Print::maybe_render();
}
