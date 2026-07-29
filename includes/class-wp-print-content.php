<?php
/**
 * Content rendering for the print view.
 *
 * @package WP-Print
 */

defined( 'ABSPATH' ) || exit;

/**
 * Prepares post and comment content for printing.
 *
 * Printed output cannot rely on a reader following a link, so every link is
 * footnoted with a number and the URLs are listed at the end of the document. The
 * numbering is shared between the post body and its comments, and a URL that
 * appears more than once reuses its first number.
 */
class WP_Print_Content {

	/**
	 * Highest footnote number handed out so far.
	 *
	 * @var int
	 */
	private static $max_number = 0;

	/**
	 * Footnote number for each URL seen so far, keyed by URL hash.
	 *
	 * @var array
	 */
	private static $seen = array();

	/**
	 * Start a fresh document.
	 *
	 * @return void
	 */
	public static function reset() {
		self::$max_number = 0;
		self::$seen       = array();

		// $links_text has been the accumulator a print template reads since the
		// plugin's first release, so it stays a global.
		$GLOBALS['links_text'] = '';
	}

	/**
	 * The collected list of URLs, ready to print.
	 *
	 * @return string
	 */
	public static function links_text() {
		return isset( $GLOBALS['links_text'] ) ? (string) $GLOBALS['links_text'] : '';
	}

	/**
	 * Append an entry to the printed URL list.
	 *
	 * @param int    $number Footnote number.
	 * @param string $label  Link text, or the Image placeholder.
	 * @param string $url    Absolute URL.
	 * @return void
	 */
	private static function add_to_list( $number, $label, $url ) {
		if ( ! isset( $GLOBALS['links_text'] ) ) {
			$GLOBALS['links_text'] = '';
		}

		$GLOBALS['links_text'] .= '<p style="margin: 2px 0;">[' . number_format_i18n( $number ) . '] '
			. $label . ': <strong><span dir="ltr">' . esc_html( $url ) . '</span></strong></p>';
	}

	/**
	 * The post content, prepared for printing.
	 *
	 * @return string
	 */
	public static function post_content() {
		global $pages, $multipage, $numpages;

		if ( post_password_required() ) {
			return get_the_password_form();
		}

		// Copied to a local rather than reassigned: $pages is a WordPress global,
		// and it is only populated by setup_postdata(), so a print view reached
		// outside the loop has to degrade to empty content instead of warning.
		$post_pages = is_array( $pages ) ? $pages : array();
		$content    = '';

		if ( $multipage ) {
			for ( $page = 0; $page < $numpages; $page++ ) {
				if ( isset( $post_pages[ $page ] ) ) {
					$content .= $post_pages[ $page ];
				}
			}
		} else {
			$content = isset( $post_pages[0] ) ? $post_pages[0] : '';
		}

		self::suppress_shortcodes();

		$content = apply_filters( 'the_content', $content );
		$content = str_replace( ']]>', ']]&gt;', $content );

		return self::prepare( $content );
	}

	/**
	 * The current comment's content, prepared for printing.
	 *
	 * @return string
	 */
	public static function comment_content() {
		$content = get_comment_text();
		$content = apply_filters( 'comment_text', $content, null, array() );

		return self::prepare( $content );
	}

	/**
	 * Apply the media toggles and footnote the links.
	 *
	 * @param string $content Filtered content.
	 * @return string
	 */
	private static function prepare( $content ) {
		if ( ! WP_Print_Options::can( 'images' ) ) {
			$content = self::remove_images( $content );
		}

		if ( ! WP_Print_Options::can( 'videos' ) ) {
			$content = self::remove_videos( $content );
		}

		if ( WP_Print_Options::can( 'links' ) ) {
			$content = self::footnote_links( $content );
		}

		return $content;
	}

	/**
	 * Swap the print-suppressing shortcodes for ones that render nothing.
	 *
	 * [donotprint] passes its content through on a normal page view and drops it
	 * here; [print_link] must not print a link to the page being printed. WP-Email
	 * has the same arrangement for [donotemail], honoured here for the same reason:
	 * text a reader excluded from print is usually excluded from email too.
	 *
	 * @return void
	 */
	private static function suppress_shortcodes() {
		$nothing = static function () {
			return '';
		};

		if ( function_exists( 'email_rewrite' ) ) {
			remove_shortcode( 'donotemail' );
			add_shortcode( 'donotemail', $nothing );
		}

		remove_shortcode( 'donotprint' );
		add_shortcode( 'donotprint', $nothing );

		remove_shortcode( 'print_link' );
		add_shortcode( 'print_link', $nothing );
	}

	/**
	 * Number every link and collect its URL.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	private static function footnote_links( $content ) {
		preg_match_all( '/<a(.+?)href=["\'](.+?)["\'](.*?)>(.+?)<\/a>/', $content, $matches );

		$total = count( $matches[0] );

		for ( $i = 0; $i < $total; $i++ ) {
			$link_match = $matches[0][ $i ];
			$link_url   = self::absolute_url( $matches[2][ $i ] );
			$link_text  = $matches[4][ $i ];

			$hash     = md5( $link_url );
			$is_first = ! isset( self::$seen[ $hash ] );

			if ( $is_first ) {
				++self::$max_number;
				self::$seen[ $hash ] = self::$max_number;
			}

			$number = self::$seen[ $hash ];

			// Escaped at both sinks: esc_url() for the attribute here, esc_html()
			// for the printed list in add_to_list().
			$content = self::replace_first(
				$link_match,
				'<a href="' . esc_url( $link_url ) . '" rel="external">' . $link_text . '</a> <sup>[' . number_format_i18n( $number ) . ']</sup>',
				$content
			);

			if ( $is_first ) {
				$label = preg_match( '/<img(.+?)src=["\'](.+?)["\'](.*?)>/', $link_text )
					? __( 'Image', 'wp-print' )
					: $link_text;

				self::add_to_list( $number, $label, $link_url );
			}
		}

		return $content;
	}

	/**
	 * Resolve a URL taken from post markup to an absolute one.
	 *
	 * A printed URL has to stand on its own, so a site-relative href is expanded
	 * against the home URL and a protocol-relative one is given a scheme.
	 *
	 * @param string $url URL as it appeared in the markup.
	 * @return string
	 */
	private static function absolute_url( $url ) {
		if ( '' === $url ) {
			return $url;
		}

		// Protocol-relative, as in //example.com/x.
		if ( 0 === strpos( $url, '//' ) ) {
			return ( is_ssl() ? 'https:' : 'http:' ) . $url;
		}

		// In-page anchor: nothing to resolve.
		if ( '#' === $url[0] ) {
			return $url;
		}

		// Already carries a scheme - http:, https:, mailto:, tel: and so on. The
		// character class stops at the first character a scheme cannot contain, so
		// a relative URL with a scheme inside its query string is not mistaken for
		// an absolute one.
		if ( preg_match( '#^[a-z][a-z0-9+.\-]*:#i', $url ) ) {
			return $url;
		}

		return get_option( 'home' ) . $url;
	}

	/**
	 * Replace the first occurrence of a string, and only the first.
	 *
	 * Identical links must each get their own footnote marker, so the replacement
	 * cannot be global.
	 *
	 * @param string $search  Needle.
	 * @param string $replace Replacement.
	 * @param string $content Haystack.
	 * @return string
	 */
	private static function replace_first( $search, $replace, $content ) {
		$pos = strpos( $content, $search );

		// Compared against false explicitly: a match at offset 0 is a valid match,
		// but 0 is falsy, and testing truthiness skipped the replacement for any
		// content beginning with the needle - which is what happens to the first
		// link of a post whose theme has turned wpautop off.
		if ( false === $pos ) {
			return $content;
		}

		return substr( $content, 0, $pos ) . $replace . substr( $content, $pos + strlen( $search ) );
	}

	/**
	 * Strip images.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	private static function remove_images( $content ) {
		return preg_replace( '/<img(.+?)src=["\'](.+?)["\'](.*?)>/', '', $content );
	}

	/**
	 * Strip embedded video.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	private static function remove_videos( $content ) {
		$content = preg_replace( '/<object[^>]*?>.*?<\/object>/', '', $content );
		$content = preg_replace( '/<embed[^>]*?>.*?<\/embed>/', '', $content );
		$content = preg_replace( '/<iframe[^>]*?>.*?<\/iframe>/', '', $content );

		return $content;
	}

	/**
	 * The list of categories the post is filed under.
	 *
	 * @param string $before Markup before each category.
	 * @param string $after  Markup after each category.
	 * @return string
	 */
	public static function categories( $before = '', $after = '' ) {
		$categories = wp_strip_all_tags( get_the_category_list( ',' ) );
		$categories = array_filter( array_map( 'trim', explode( ',', $categories ) ), 'strlen' );

		return $before . implode( $after . ', ' . $before, $categories ) . $after;
	}

	/**
	 * The comment count, as a sentence.
	 *
	 * @return string
	 */
	public static function comments_number() {
		if ( post_password_required() ) {
			return __( 'Comments Hidden', 'wp-print' );
		}

		$post = get_post();

		if ( ! $post || 'open' !== $post->comment_status ) {
			return __( 'Comments Disabled', 'wp-print' );
		}

		$count = (int) get_comments_number();

		if ( 0 === $count ) {
			return __( 'No Comments', 'wp-print' );
		}

		return sprintf(
			/* translators: %s: number of comments */
			_n( '%s Comment', '%s Comments', $count, 'wp-print' ),
			number_format_i18n( $count )
		);
	}
}
