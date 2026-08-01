<?php
/**
 * Plugin Name: WP-Print E2E fixtures
 * Description: Exposes print_link() through a shortcode and attaches the plugin's two public filters, so the browser suite can reach the paths a theme or a site plugin owns. Loaded only in the wp-env tests environment.
 *
 * The print link has two ways out and they are not the same path. The
 * `[print_link]` shortcode returns WP_Print_Link::render()'s markup straight
 * into the post content, while the print_link() template tag echoes the same
 * markup through wp_kses() with the plugin's own allow-list. A theme calls the
 * tag; no bundled theme calls this one, so this file plays the part of a theme
 * that does.
 *
 * Without it the escaping half of the plugin's public API would be reachable
 * from PHPUnit only, and PHPUnit is the half that cannot tell you whether a
 * browser ran what came out.
 *
 * It is a fixture, not a shipped file: it lives under tests/ and is mapped into
 * wp-content/mu-plugins for the tests environment only, by .wp-env.json.
 *
 * @package WP-Print
 */

defined( 'ABSPATH' ) || exit;

/*
 * Web requests only.
 *
 * wp-env maps this directory into the tests environment, and PHPUnit runs in
 * that same environment -- so without this guard the unit suite would load a
 * fixture that has no business being visible to a test that is not driving a
 * browser.
 */
if ( 'cli' === PHP_SAPI ) {
	return;
}

/**
 * Call print_link() the way a theme would, and capture what it echoed.
 *
 * @return string The markup the template tag printed.
 */
function wp_print_e2e_template_tag() {
	ob_start();

	print_link();

	return '<div class="wp-print-e2e-tag">' . ob_get_clean() . '</div>';
}

add_shortcode( 'print_link_tag', 'wp_print_e2e_template_tag' );

/**
 * Widen the printed document's allow-list, through the plugin's own filter.
 *
 * The print view is the one place WP-Print echoes somebody else's HTML, and the
 * filter is documented as the escape hatch for a block or a shortcode whose
 * markup the default list does not cover. A site that needs it has a tag going
 * missing from its printouts and nothing on screen to explain why, so "the
 * filter is honoured" is worth more than one reading of the code.
 *
 * The tag to add comes from an option, and the context is passed through, so a
 * test can also see which of 'post' and 'comment' it was called for.
 *
 * @param array  $allowed Allowed tags, in wp_kses() form.
 * @param string $context Where the content came from: 'post' or 'comment'.
 * @return array
 */
function wp_print_e2e_allowed_html( $allowed, $context ) {
	$tag = (string) get_option( 'wp_print_e2e_allow_tag', '' );

	if ( '' === $tag ) {
		return $allowed;
	}

	$allowed[ $tag ] = array( 'class' => true );

	// A word rather than a 1: an option's value comes back out of the database
	// as a string, so a test comparing against the integer it wrote would fail
	// on the type and say nothing about the hook.
	update_option( 'wp_print_e2e_allow_context_' . $context, 'yes', false );

	return $allowed;
}

add_filter( 'wp_print_allowed_html', 'wp_print_e2e_allowed_html', 10, 2 );

/**
 * Hand the settings screen to a lesser capability when the test asks.
 *
 * `read` is the capability every logged-in user has, so a subscriber reaching
 * the screen is unambiguous evidence the filter was honoured rather than that
 * some other gate happened to let them by.
 *
 * @param string $capability The capability the plugin requires.
 * @return string
 */
function wp_print_e2e_capability( $capability ) {
	$answer = (string) get_option( 'wp_print_e2e_capability', '' );

	return '' === $answer ? $capability : $answer;
}

add_filter( 'wp_print_capability', 'wp_print_e2e_capability' );
