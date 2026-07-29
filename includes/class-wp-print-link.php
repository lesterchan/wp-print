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
 * The markup of all four styles is byte-for-byte what previous releases emitted,
 * because it is the plugin's most visible output and themes style it by class.
 */
class WP_Print_Link {

	/**
	 * Style: icon followed by a text link.
	 */
	const STYLE_ICON_TEXT = 1;

	/**
	 * Style: icon only.
	 */
	const STYLE_ICON = 2;

	/**
	 * Style: text link only.
	 */
	const STYLE_TEXT = 3;

	/**
	 * Style: the custom HTML template.
	 */
	const STYLE_CUSTOM = 4;

	/**
	 * Build the print link for the current post or page.
	 *
	 * @param string $post_text Optional override for the post link text.
	 * @param string $page_text Optional override for the page link text.
	 * @return string
	 */
	public static function render( $post_text = '', $page_text = '' ) {
		$options = WP_Print_Options::get();

		$text = is_page()
			? ( '' !== $page_text ? $page_text : $options['page_text'] )
			: ( '' !== $post_text ? $post_text : $options['post_text'] );

		$url  = self::url();
		$icon = WP_Print_Template::plugin_url( 'images/' . $options['print_icon'] );

		$url_esc  = esc_url( $url );
		$icon_esc = esc_url( $icon );
		$text_esc = esc_attr( $text );

		switch ( (int) $options['print_style'] ) {
			case self::STYLE_ICON_TEXT:
				return '<a href="' . $url_esc . '" title="' . $text_esc . '" rel="nofollow"><img class="WP-PrintIcon" src="' . $icon_esc . '" alt="' . $text_esc . '" title="' . $text_esc . '" style="border: 0px;" /></a>&nbsp;<a href="' . $url_esc . '" title="' . $text_esc . '" rel="nofollow">' . $text . '</a>';

			case self::STYLE_ICON:
				return '<a href="' . $url_esc . '" title="' . $text_esc . '" rel="nofollow"><img class="WP-PrintIcon" src="' . $icon_esc . '" alt="' . $text_esc . '" title="' . $text_esc . '" style="border: 0px;" /></a>';

			case self::STYLE_TEXT:
				return '<a href="' . $url_esc . '" title="' . $text_esc . '" rel="nofollow">' . $text . '</a>';

			case self::STYLE_CUSTOM:
				return str_replace(
					array( '%PRINT_URL%', '%PRINT_TEXT%', '%PRINT_ICON_URL%' ),
					array( $url_esc, $text, $icon_esc ),
					$options['print_html']
				);
		}

		return '';
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
	 * @param array $atts Shortcode attributes. Unused; the link is configured in
	 *                     the settings screen rather than per shortcode.
	 * @return string
	 */
	public static function shortcode( $atts = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Signature is fixed by add_shortcode().
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
	public static function donotprint_shortcode( $atts = array(), $content = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Signature is fixed by add_shortcode().
		return do_shortcode( (string) $content );
	}
}
