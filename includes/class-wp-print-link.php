<?php
/**
 * The print link.
 *
 * @package WP-Print
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds the link that takes a reader to the print view.
 *
 * One HTML template, held in `print_html`, with three placeholders substituted
 * into it. Up to 3.0.0 there were three fixed markups and two link labels as
 * well; the template could express all of them and the four settings could not
 * express anything else, so the template is all that is left. What each of the
 * three used to render is now what the migration writes into a template.
 *
 * A placeholder the plugin does not know is left in the markup exactly as the
 * site wrote it. That is deliberate: a template still carrying the retired
 * %PRINT_TEXT% is visibly wrong on the page rather than silently missing its
 * words, and the difference is whether anybody notices.
 */
class WP_Print_Link {

	/**
	 * The printer glyph.
	 *
	 * An inline SVG rather than the two GIFs the plugin used to ship and let a
	 * site choose between. It inherits the surrounding text colour through
	 * `fill="currentColor"`, so it matches whatever the theme paints its links,
	 * and it scales with the text instead of blurring on a high-density screen.
	 * There is nothing left to choose between, which is why the Print Icon
	 * setting is gone.
	 *
	 * The WP-PrintIcon class comes along unchanged: themes have styled it by that
	 * name for twenty years.
	 *
	 * @return string
	 */
	public static function icon() {
		return '<svg class="WP-PrintIcon wp-print-icon" width="16" height="16" viewBox="0 0 20 20"'
			. ' fill="currentColor" aria-hidden="true" focusable="false">'
			. '<path d="M6 2h8v4H6z" />'
			. '<path d="M3 7h14v7h-3v-3H6v3H3z" />'
			. '<path d="M6 12h8v6H6z" />'
			. '</svg>';
	}

	/**
	 * The tags and attributes the link may contain.
	 *
	 * A closed list rather than wp_kses_post(): that one has never allowed
	 * `svg`, so it would quietly strip the glyph the template substitutes in.
	 * Used at the point the link is echoed.
	 *
	 * @return array
	 */
	public static function allowed_html() {
		return array(
			'a'      => array(
				'href'       => true,
				'title'      => true,
				'rel'        => true,
				'class'      => true,
				'id'         => true,
				'target'     => true,
				'aria-label' => true,
			),
			'img'    => array(
				'src'    => true,
				'alt'    => true,
				'title'  => true,
				'class'  => true,
				'width'  => true,
				'height' => true,
				'id'     => true,
			),
			'span'   => array(
				'class' => true,
				'id'    => true,
				'dir'   => true,
			),
			'strong' => array( 'class' => true ),
			'em'     => array( 'class' => true ),
			'br'     => array(),
			'svg'    => array(
				'class'       => true,
				'width'       => true,
				'height'      => true,
				'viewbox'     => true,
				'fill'        => true,
				'aria-hidden' => true,
				'focusable'   => true,
				'role'        => true,
			),
			'path'   => array(
				'd'    => true,
				'fill' => true,
			),
		);
	}

	/**
	 * Build the print link for the current post or page.
	 *
	 * Returns the markup rather than printing it, and does not filter it: the
	 * caller escapes at the point of output, exactly as get_the_title() leaves
	 * that to the_title(). print_link() does it for you.
	 *
	 * @return string
	 */
	public static function render() {
		return str_replace(
			array( '%PRINT_URL%', '%PRINT_ICON%', '%POST_TYPE%' ),
			array( esc_url( self::url() ), self::icon(), self::post_type_label() ),
			(string) WP_Print_Options::get( 'print_html' )
		);
	}

	/**
	 * What %POST_TYPE% stands for on the post being rendered.
	 *
	 * The post type's own singular label, so one template says "Print This Post"
	 * on a post, "Print This Page" on a page and "Print This Recipe" on a site
	 * whose recipes are a post type of their own -- which is a sentence the two
	 * labels this replaced could not produce between them.
	 *
	 * Outside the loop there is no post to ask, and the shipped wording has said
	 * "Post" there since the plugin's first release.
	 *
	 * @return string
	 */
	public static function post_type_label() {
		$post_type = get_post_type_object( get_post_type() );

		if ( $post_type instanceof WP_Post_Type
			&& isset( $post_type->labels->singular_name )
			&& '' !== (string) $post_type->labels->singular_name ) {
			return (string) $post_type->labels->singular_name;
		}

		return __( 'Post', 'wp-print' );
	}

	/**
	 * The print URL for the current post or page.
	 *
	 * @return string
	 */
	public static function url() {
		$url = get_permalink();

		// A static front page's permalink is the site root, which the print
		// endpoint cannot be appended to usefully; _get_page_link() gives the
		// page's own URL instead.
		if ( 'page' === get_option( 'show_on_front' ) && is_page() && (int) get_option( 'page_on_front' ) > 0 ) {
			$url = _get_page_link();
		}

		if ( get_option( 'permalink_structure' ) ) {
			return trailingslashit( $url ) . 'print/';
		}

		return $url . '&amp;print=1';
	}

	/**
	 * The [print_link] shortcode.
	 *
	 * Declares no parameters: the link is configured on the settings screen
	 * rather than per shortcode, and PHP is happy to call a callback with more
	 * arguments than it takes.
	 *
	 * @return string
	 */
	public static function shortcode() {
		if ( is_feed() ) {
			return __( 'Note: There is a print link embedded within this post, please visit this post to print it.', 'wp-print' );
		}

		return self::render();
	}

	/**
	 * The [donotprint] shortcode, outside a print view.
	 *
	 * The content is kept: it is only dropped when the print view replaces this
	 * callback. See WP_Print_Content::suppress_shortcodes().
	 *
	 * @param array       $atts    Shortcode attributes. Unused.
	 * @param string|null $content Enclosed content.
	 * @return string
	 */
	public static function donotprint_shortcode( $atts = array(), $content = null ) {
		return do_shortcode( (string) $content );
	}
}
