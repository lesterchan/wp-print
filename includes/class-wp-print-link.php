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
	 * `svg`, so it would quietly strip the glyph out of styles 1 and 2. Used at
	 * the point the link is echoed.
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
	 * @param string $post_text Optional override for the post link text.
	 * @param string $page_text Optional override for the page link text.
	 * @return string
	 */
	public static function render( $post_text = '', $page_text = '' ) {
		$options = WP_Print_Options::get();

		$text = is_page()
			? ( '' !== $page_text ? $page_text : $options['page_text'] )
			: ( '' !== $post_text ? $post_text : $options['post_text'] );

		$url_esc  = esc_url( self::url() );
		$text_esc = esc_attr( $text );
		$icon     = self::icon();

		switch ( (int) $options['print_style'] ) {
			case self::STYLE_ICON_TEXT:
				return '<a href="' . $url_esc . '" title="' . $text_esc . '" rel="nofollow">' . $icon . '</a>&nbsp;<a href="' . $url_esc . '" title="' . $text_esc . '" rel="nofollow">' . $text . '</a>';

			case self::STYLE_ICON:
				// aria-label as well as title: with the glyph hidden from
				// assistive technology and no text beside it, the link would
				// otherwise have no accessible name at all.
				return '<a href="' . $url_esc . '" title="' . $text_esc . '" aria-label="' . $text_esc . '" rel="nofollow">' . $icon . '</a>';

			case self::STYLE_TEXT:
				return '<a href="' . $url_esc . '" title="' . $text_esc . '" rel="nofollow">' . $text . '</a>';

			case self::STYLE_CUSTOM:
				return str_replace(
					array( '%PRINT_URL%', '%PRINT_TEXT%', '%PRINT_ICON%' ),
					array( $url_esc, $text, $icon ),
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
