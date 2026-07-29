<?php
/**
 * Plugin options.
 *
 * @package WP-Print
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads, writes and upgrades the plugin's option rows.
 *
 * What the rows are called, what shape they hold and how one version's shape
 * becomes the next. The sanitize callback that guards writes from the settings
 * screen belongs to WP_Print_Settings, which is where register_setting() hangs
 * it; this class describes the keys it works on -- text_keys(), html_keys(),
 * bool_keys() and retired_keys().
 *
 * The shape of the stored strings changed in 3.0.0: before it the admin screen
 * slashed values on the way in and every reader called stripslashes() on the way
 * out. Values are now stored clean and read as-is, which is what
 * WP_Print_Options::maybe_upgrade() exists to fix up.
 */
class WP_Print_Options {

	/**
	 * Settings row, holding every user-editable setting and nothing else.
	 * Autoloaded.
	 *
	 * @var string
	 */
	const OPTION = 'wp_print_options';

	/**
	 * Upgrade markers row, holding 'plugin' and 'db'. Autoloaded.
	 *
	 * The markers live outside the settings array on purpose: a sanitize
	 * callback is a function from what the form posted to what gets stored, and
	 * the settings form never posts a version marker. Keeping them apart means a
	 * settings save and an upgrade cannot overwrite each other.
	 *
	 * @var string
	 */
	const VERSION = 'wp_print_version';

	/**
	 * The settings row as every release up to 2.58.3 named it.
	 *
	 * Read once by the migration and then deleted. Spelled as a constant rather
	 * than inline so that no live get_option() call in this plugin names an
	 * unprefixed row.
	 *
	 * @var string
	 */
	const LEGACY_OPTION = 'print_options';

	/**
	 * The schema counter as every release up to 2.58.3 named it.
	 *
	 * A bare string rather than the pair of markers, and unprefixed. Deleted by
	 * the migration.
	 *
	 * @var string
	 */
	const LEGACY_VERSION = 'print_db_version';

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
	 * Keys the plugin used to store and no longer does.
	 *
	 * Dropped by the sanitizer on every write, so a retired setting cannot come
	 * back through the "keep what this screen does not render" merge below.
	 * `print_icon` chose between two bundled GIFs; there is one inline SVG now.
	 *
	 * @return array
	 */
	public static function retired_keys() {
		return array( 'print_icon' );
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
		$stored  = get_option( self::OPTION, array() );
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
		return update_option( self::OPTION, $options );
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
	 * The stored upgrade markers, normalised to an array.
	 *
	 * @return array
	 */
	public static function markers() {
		$markers = get_option( self::VERSION, array() );

		return is_array( $markers ) ? $markers : array();
	}

	/**
	 * Run any pending migration.
	 *
	 * Gated on the stored markers, not on whether the old shape can still be
	 * detected: gating on detection means an install that has already been
	 * migrated gets migrated again on every request.
	 *
	 * Idempotent, and called from `admin_init` as well as from activation,
	 * because activation does not fire when a plugin is merely updated.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$markers = self::markers();

		$plugin = isset( $markers['plugin'] ) ? (string) $markers['plugin'] : '';
		$db     = isset( $markers['db'] ) ? (string) $markers['db'] : '';

		if ( WP_PRINT_VERSION === $plugin && WP_PRINT_DB_VERSION === $db ) {
			return;
		}

		self::migrate_legacy_rows();
		self::migrate_icon_placeholder();

		// Both markers in one write, so an upgrade that dies half way never
		// records itself as finished.
		update_option(
			self::VERSION,
			array(
				'plugin' => WP_PRINT_VERSION,
				'db'     => WP_PRINT_DB_VERSION,
			),
			true
		);
	}

	/**
	 * Fold the pre-3.0.0 rows into the prefixed ones and delete them.
	 *
	 * Two things happen here, both once. The settings move from `print_options`
	 * to `wp_print_options`, because an unprefixed row named after one of the
	 * commonest words in the language is a collision waiting to happen; and the
	 * four string values are unslashed, because up to 2.58.3 the admin screen
	 * slashed them on the way in and every reader called stripslashes() on the
	 * way out. `print_db_version` goes too, replaced by the pair of markers in
	 * `wp_print_version`.
	 *
	 * A value already in the prefixed row wins over the legacy one, so a partly
	 * finished migration cannot undo itself on a second run.
	 *
	 * @return void
	 */
	private static function migrate_legacy_rows() {
		$legacy = get_option( self::LEGACY_OPTION, false );

		delete_option( self::LEGACY_VERSION );

		if ( ! is_array( $legacy ) ) {
			delete_option( self::LEGACY_OPTION );

			return;
		}

		foreach ( array_merge( self::text_keys(), self::html_keys() ) as $key ) {
			if ( isset( $legacy[ $key ] ) && is_string( $legacy[ $key ] ) ) {
				$legacy[ $key ] = wp_unslash( $legacy[ $key ] );
			}
		}

		$stored = get_option( self::OPTION, array() );

		update_option( self::OPTION, array_merge( $legacy, is_array( $stored ) ? $stored : array() ) );

		delete_option( self::LEGACY_OPTION );
	}

	/**
	 * Retire the print_icon setting and the placeholder that went with it.
	 *
	 * There were two bundled GIFs to choose between; there is now one inline SVG
	 * that takes its colour from the theme, so the setting has nothing left to
	 * choose. %PRINT_ICON_URL% goes with it -- an inline glyph has no URL -- and a
	 * custom template carrying it is rewritten to %PRINT_ICON%, which substitutes
	 * the glyph itself.
	 *
	 * The <img> wrapper is replaced whole where there is one, because
	 * <img src="<svg ...>"> would be worse than either. A bare %PRINT_ICON_URL%
	 * outside an img is simply renamed.
	 *
	 * @return void
	 */
	private static function migrate_icon_placeholder() {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) ) {
			return;
		}

		$before = $stored;

		unset( $stored['print_icon'] );

		if ( isset( $stored['print_html'] ) && is_string( $stored['print_html'] ) ) {
			$html = preg_replace( '#<img[^>]*%PRINT_ICON_URL%[^>]*>#i', '%PRINT_ICON%', $stored['print_html'] );

			$stored['print_html'] = str_replace( '%PRINT_ICON_URL%', '%PRINT_ICON%', (string) $html );
		}

		if ( $stored !== $before ) {
			update_option( self::OPTION, $stored );
		}
	}
}
