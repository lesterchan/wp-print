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
 * The third parameter is $display, matching the other tags here and core's own
 * the_title(). It was $echo up to 2.58.3, which only matters to a caller passing
 * it by name -- print_link( echo: false ) -- and PHP had no named arguments for
 * most of the years that spelling was in use.
 *
 * The first two arguments no longer do anything. They overrode the two link
 * labels, and there are no link labels: the wording lives in the one HTML
 * template on the settings screen, where %POST_TYPE% says on each post type what
 * the two of them said between them on two. They stay in the signature because
 * every theme that has ever called this tag passes them, and dropping them would
 * turn a settings change into a fatal error on somebody else's site.
 *
 * @param string $print_post_text Unused. Formerly the link text on a post.
 * @param string $print_page_text Unused. Formerly the link text on a page.
 * @param bool   $display         Optional. Whether to print. Default true.
 * @return string|void The markup when $display is false.
 */
function print_link( $print_post_text = '', $print_page_text = '', $display = true ) {
	unset( $print_post_text, $print_page_text );

	$output = WP_Print_Link::render();

	if ( ! $display ) {
		return $output;
	}

	echo wp_kses( $output, WP_Print_Link::allowed_html() ) . "\n";
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

	echo wp_kses( $content, WP_Print_Content::allowed_html( 'post' ) );
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

	echo wp_kses( $content, WP_Print_Content::allowed_html( 'comment' ) );
}

/**
 * Display the categories the post is filed under.
 *
 * @param string $before Optional. Markup before each category.
 * @param string $after  Optional. Markup after each category.
 * @return void
 */
function print_categories( $before = '', $after = '' ) {
	// The names have already had their tags stripped; $before and $after are the
	// caller's own markup, which is why this is filtered rather than escaped.
	echo wp_kses_post( WP_Print_Content::categories( $before, $after ) );
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

	// The URLs were escaped as they were collected; the heading is the caller's
	// markup. wp_kses_post() keeps every tag either can legitimately carry.
	echo wp_kses_post( $text_links . $links_text );
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
