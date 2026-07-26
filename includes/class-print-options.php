<?php
/**
 * Plugin options.
 *
 * @package WP-Print
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads, writes and sanitizes the plugin's single option row.
 *
 * The option key is deliberately unchanged from every previous release, so an
 * existing install carries its settings over. What did change is the shape of the
 * stored strings: before 3.0.0 the admin screen slashed values on the way in and
 * every reader called stripslashes() on the way out. Values are now stored clean
 * and read as-is, which is why Print_Options::maybe_upgrade() exists.
 *
 * The class name is Print_Options rather than the collection's usual bare-slug
 * main class name, because `print` is a reserved word in PHP and `class Print`
 * will not parse. Print_Core carries the main class for the same reason.
 */
class Print_Options {

	/**
	 * The option key. Unchanged since the plugin's first release.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'print_options';

	/**
	 * Schema version, in its own row.
	 *
	 * It is read to decide whether the main row needs migrating, so it cannot
	 * live inside the thing being migrated.
	 *
	 * @var string
	 */
	const DB_VERSION_NAME = 'print_db_version';

	/**
	 * Keys whose values are rendered as HTML.
	 *
	 * @return array
	 */
	public static function html_keys() {
		return array( 'print_html', 'disclaimer' );
	}

	/**
	 * Keys whose values are rendered as plain text.
	 *
	 * @return array
	 */
	public static function text_keys() {
		return array( 'post_text', 'page_text' );
	}

	/**
	 * Keys holding a yes/no toggle, stored as 0 or 1.
	 *
	 * @return array
	 */
	public static function bool_keys() {
		return array( 'comments', 'links', 'images', 'thumbnail', 'videos' );
	}

	/**
	 * The default option values.
	 *
	 * Translated on demand rather than at load time, so this must not be called
	 * before `init` fires.
	 *
	 * @return array
	 */
	public static function get_defaults() {
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
			'disclaimer'  => self::default_disclaimer(),
		);
	}

	/**
	 * The shipped disclaimer text.
	 *
	 * Shared with the settings screen, whose Restore Default button has to offer
	 * exactly the same string.
	 *
	 * @return string
	 */
	public static function default_disclaimer() {
		return sprintf(
			/* translators: 1: current year, 2: site name */
			__( 'Copyright &copy; %1$s %2$s. All rights reserved.', 'wp-print' ),
			current_time( 'Y' ),
			get_option( 'blogname' )
		);
	}

	/**
	 * Get the stored options, merged over the defaults.
	 *
	 * Merging on read is what lets an install upgraded from an older version pick
	 * up keys that did not exist when its row was written - `thumbnail` arrived in
	 * 2.58, and reading it unguarded raised "Undefined array key".
	 *
	 * @param string|null $key Optional single key to return.
	 * @return mixed The full option array, or one value, or null for an unknown key.
	 */
	public static function get( $key = null ) {
		$stored  = get_option( self::OPTION_NAME, array() );
		$options = array_merge( self::get_defaults(), is_array( $stored ) ? $stored : array() );

		if ( null === $key ) {
			return $options;
		}

		return isset( $options[ $key ] ) ? $options[ $key ] : null;
	}

	/**
	 * Replace the stored options.
	 *
	 * @param array $options Option values.
	 * @return bool Whether the option row changed.
	 */
	public static function update( array $options ) {
		return update_option( self::OPTION_NAME, $options );
	}

	/**
	 * Whether a toggle is on.
	 *
	 * Cast to int because templates use the result in a boolean test, and the
	 * string '0' that an older row may hold is truthy.
	 *
	 * @param string $type Toggle key.
	 * @return int 1 or 0.
	 */
	public static function can( $type ) {
		$value = self::get( $type );

		return null === $value ? 0 : (int) $value;
	}

	/**
	 * Sanitize a submitted option array.
	 *
	 * Receives the whole nested array from register_setting(). options.php has
	 * already run wp_unslash() over it, so nothing here re-slashes: values are
	 * stored exactly as they will be rendered.
	 *
	 * A key missing from the input keeps whatever is already stored, rather than
	 * reverting to a default or blanking. Every control on the settings screen is
	 * a text field, a select or a textarea, so the form always posts all of them
	 * and the screen behaves identically either way - but register_setting() hangs
	 * this on sanitize_option_print_options, which means it also runs for
	 * update_option() calls made by WP-CLI, a migration or another plugin. Those
	 * are usually partial, and blanking the disclaimer and the custom template
	 * because a caller only wanted to flip one toggle is not a defensible reading
	 * of "sanitize".
	 *
	 * @param mixed $input Raw submitted value.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$defaults = self::get_defaults();
		$current  = self::get();
		$input    = is_array( $input ) ? $input : array();
		$clean    = array();

		foreach ( self::text_keys() as $key ) {
			if ( ! isset( $input[ $key ] ) || ! is_scalar( $input[ $key ] ) ) {
				continue;
			}

			// wp_kses_data(), not wp_filter_kses(). The two allow the same tags -
			// these are link labels, so only the small inline set is worth keeping -
			// but wp_filter_kses() also runs addslashes() on the way out, because it
			// was written for the days when superglobals stayed slashed. options.php
			// has already unslashed, so using it here stored "Tom & Jerry\'s Post".
			$value = trim( wp_kses_data( (string) $input[ $key ] ) );

			// An empty link label renders a link with nothing to click, so an empty
			// submission means "give me the shipped text back".
			$clean[ $key ] = '' === $value ? $defaults[ $key ] : $value;
		}

		foreach ( self::html_keys() as $key ) {
			if ( ! isset( $input[ $key ] ) || ! is_scalar( $input[ $key ] ) ) {
				continue;
			}

			$value = trim( (string) $input[ $key ] );

			// HTML is allowed, but a user without unfiltered_html - a site admin on
			// multisite, for instance - must not be able to store script. Emptying
			// these is legitimate: a site may not want a disclaimer at all.
			if ( ! current_user_can( 'unfiltered_html' ) ) {
				$value = wp_kses_post( $value );
			}

			$clean[ $key ] = $value;
		}

		foreach ( self::bool_keys() as $key ) {
			if ( ! isset( $input[ $key ] ) ) {
				continue;
			}

			$clean[ $key ] = $input[ $key ] ? 1 : 0;
		}

		if ( isset( $input['print_style'] ) ) {
			$style = (int) $input['print_style'];

			// An unknown style renders nothing at all, so keep the last valid one.
			if ( in_array( $style, array( 1, 2, 3, 4 ), true ) ) {
				$clean['print_style'] = $style;
			}
		}

		// Only accept an icon that really exists in the plugin's images directory,
		// so the stored value can never be turned into a path traversal. Anything
		// else - absent, empty, a traversal, a file that is not there - leaves the
		// stored icon alone.
		if ( isset( $input['print_icon'] ) && is_scalar( $input['print_icon'] ) ) {
			$icon = basename( trim( (string) $input['print_icon'] ) );

			if ( '' !== $icon && is_file( plugin_dir_path( WP_PRINT_MAIN_FILE ) . 'images/' . $icon ) ) {
				$clean['print_icon'] = $icon;
			}
		}

		// Preserve anything a filter or an older version stored that this screen
		// does not render, rather than dropping it on the first save.
		return array_merge( $current, $clean );
	}

	/**
	 * Run any pending migration.
	 *
	 * Gated on the stored schema version, not on whether the old shape can still
	 * be detected: gating on detection means an install that has already been
	 * migrated gets migrated again.
	 *
	 * Idempotent, and called from `admin_init` as well as activation, because
	 * activation does not fire when a plugin is updated.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$stored = get_option( self::DB_VERSION_NAME, '0' );

		if ( version_compare( (string) $stored, WP_PRINT_DB_VERSION, '>=' ) ) {
			return;
		}

		$options = get_option( self::OPTION_NAME );

		if ( is_array( $options ) ) {
			// Before 3.0.0 these four were stored slashed and unslashed again by
			// every reader. They are now stored clean, so undo the slashing once.
			foreach ( array_merge( self::text_keys(), self::html_keys() ) as $key ) {
				if ( isset( $options[ $key ] ) && is_string( $options[ $key ] ) ) {
					$options[ $key ] = wp_unslash( $options[ $key ] );
				}
			}

			update_option( self::OPTION_NAME, $options );
		}

		update_option( self::DB_VERSION_NAME, WP_PRINT_DB_VERSION );
	}
}
