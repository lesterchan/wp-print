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
 * it; this class describes the keys it works on -- html_keys(), bool_keys() and
 * retired_keys().
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
	 * The pre-3.0.0 `print_style`: icon followed by a text link, and the default.
	 *
	 * The four are kept as constants rather than as bare numbers because the
	 * migration has to reproduce what each of them rendered. They are no longer a
	 * choice anybody can make: the settings screen offers one custom template and
	 * `print_style` is retired.
	 *
	 * @var int
	 */
	const LEGACY_STYLE_ICON_TEXT = 1;

	/**
	 * The pre-3.0.0 `print_style`: icon only.
	 *
	 * @var int
	 */
	const LEGACY_STYLE_ICON = 2;

	/**
	 * The pre-3.0.0 `print_style`: text link only.
	 *
	 * @var int
	 */
	const LEGACY_STYLE_TEXT = 3;

	/**
	 * The pre-3.0.0 `print_style`: the custom HTML template.
	 *
	 * A row carrying this already holds the only thing 3.0.0 stores, so the
	 * migration leaves its template exactly as the site wrote it.
	 *
	 * @var int
	 */
	const LEGACY_STYLE_CUSTOM = 4;

	/**
	 * Keys whose values are rendered as HTML.
	 *
	 * @return array
	 */
	public static function html_keys() {
		return array( 'print_html', 'disclaimer' );
	}

	/**
	 * Keys the pre-3.0.0 row held as slashed strings.
	 *
	 * Not the same list as html_keys(): `post_text` and `page_text` are retired,
	 * but the migration still reads them to synthesise a link template from, and a
	 * value read out of the unprefixed row has to be unslashed first or the
	 * template it lands in shows the backslashes to every visitor.
	 *
	 * @return array
	 */
	private static function legacy_string_keys() {
		return array( 'post_text', 'page_text', 'print_html', 'disclaimer' );
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
	 *
	 * `print_icon` chose between two bundled GIFs; there is one inline SVG now.
	 * `print_style` chose between three fixed markups and a custom template; there
	 * is only the template now, and `post_text` and `page_text` were the two link
	 * labels the three fixed markups interpolated. One template with a
	 * %POST_TYPE% placeholder says everything the four of them said between them.
	 *
	 * @return array
	 */
	public static function retired_keys() {
		return array( 'print_icon', 'print_style', 'post_text', 'page_text' );
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
			'print_html' => self::default_template(),
			'comments'   => 0,
			'links'      => 1,
			'images'     => 1,
			'thumbnail'  => 0,
			'videos'     => 0,
			'disclaimer' => self::default_disclaimer(),
		);
	}

	/**
	 * The shipped link template.
	 *
	 * Shared with the settings screen, whose Restore Default Template button has
	 * to offer exactly the same string, and with the migration, which synthesises
	 * this shape for a site that never left the shipped settings.
	 *
	 * One anchor carrying the glyph and the words, which is what the icon-and-text
	 * style rendered as two. %POST_TYPE% is what makes one template enough for
	 * every post type: the two labels it replaced could only ever say two things.
	 *
	 * @return string
	 */
	public static function default_template() {
		return self::link_template( self::LEGACY_STYLE_ICON_TEXT, self::default_link_text() );
	}

	/**
	 * The wording the shipped template uses.
	 *
	 * Kept out of the markup around it so that a translator is handed a sentence
	 * rather than an anchor, and so that the migration can recognise a site still
	 * on the stock wording and collapse it to this.
	 *
	 * @return string
	 */
	public static function default_link_text() {
		return __( 'Print This %POST_TYPE%', 'wp-print' );
	}

	/**
	 * One of the pre-3.0.0 markups, as a template.
	 *
	 * The three fixed styles are gone as a user choice, but their markup is what
	 * the migration has to reproduce for the sites that chose them -- an icon-only
	 * site stays icon-only -- so the shapes live on here as the templates they
	 * always were underneath.
	 *
	 * @param int    $style One of the LEGACY_STYLE_* constants.
	 * @param string $text  The link wording, which may itself hold %POST_TYPE%.
	 * @return string
	 */
	public static function link_template( $style, $text ) {
		// The wording is interpolated into an attribute as well as into the
		// anchor's contents, so the attribute copy is escaped here rather than
		// left for a reader of the row to remember.
		$title = esc_attr( $text );

		switch ( (int) $style ) {
			case self::LEGACY_STYLE_ICON:
				// aria-label as well as title: with the glyph hidden from assistive
				// technology and no text beside it, the link would otherwise have no
				// accessible name at all.
				return '<a href="%PRINT_URL%" rel="nofollow" title="' . $title . '" aria-label="' . $title . '">%PRINT_ICON%</a>';

			case self::LEGACY_STYLE_TEXT:
				return '<a href="%PRINT_URL%" rel="nofollow" title="' . $title . '">' . $text . '</a>';
		}

		return '<a href="%PRINT_URL%" rel="nofollow" title="' . $title . '">%PRINT_ICON% ' . $text . '</a>';
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

		/*
		 * Read the link settings before anything writes.
		 *
		 * `print_style`, `post_text` and `page_text` are retired, and every write
		 * below drops a retired key -- both the folds here and the sanitize
		 * callback, which register_setting() hangs on
		 * sanitize_option_wp_print_options and which is therefore attached to
		 * every update_option() this method makes on an admin request. Reading
		 * the three first is what lets the template be synthesised from them
		 * afterwards rather than from a row they have already been taken out of.
		 */
		$link = self::legacy_link_settings();

		self::migrate_legacy_rows();
		self::migrate_icon_placeholder();
		self::migrate_link_template( $link );

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

		$legacy = self::unslash_legacy( $legacy );
		$stored = get_option( self::OPTION, array() );
		$merged = array_merge( $legacy, is_array( $stored ) ? $stored : array() );

		// Explicitly rather than by leaving it to the sanitize callback, which is
		// only attached on an admin request: activation runs this method too, and
		// the hook has already fired by the time the newly activated plugin's
		// files are loaded. A retired key must go on both paths.
		$merged = array_diff_key( $merged, array_flip( self::retired_keys() ) );

		self::write( $merged );

		delete_option( self::LEGACY_OPTION );
	}

	/**
	 * Write the settings row from inside the migration.
	 *
	 * `update_option()` declines to write a value equal to the one `get_option()`
	 * would return, and `register_setting()` is passed a `default`, which installs
	 * a `default_option_wp_print_options` filter answering with the shipped
	 * defaults for a row that does not exist. So on an admin request -- the path
	 * every real update takes, because activation hooks do not fire on an update
	 * -- a migration whose result happens to equal the defaults writes nothing at
	 * all, and the row is never created.
	 *
	 * That is silent, and it does not stop there: migrate_legacy_rows() and
	 * migrate_link_template() are two writes that have to compose, the second
	 * re-reading what the first stored. When the first vanishes the second builds
	 * its template on an empty row, and the markers are stamped complete either
	 * way, so the upgrade can never run again.
	 *
	 * Passing an explicit default to `get_option()` defeats the registered one --
	 * `filter_default_option()` returns early when a default was passed -- which
	 * is what lets an absent row be told apart from a defaulted one and added
	 * outright. `add_option()` runs the sanitize callback exactly as
	 * `update_option()` does, so a retired key is dropped on both paths.
	 *
	 * @param array $options The settings to store.
	 * @return void
	 */
	private static function write( array $options ) {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, $options );

			return;
		}

		update_option( self::OPTION, $options );
	}

	/**
	 * Unslash the string values a pre-3.0.0 row holds.
	 *
	 * @param array $legacy The unprefixed settings row.
	 * @return array The same array, with its four strings unslashed.
	 */
	private static function unslash_legacy( array $legacy ) {
		foreach ( self::legacy_string_keys() as $key ) {
			if ( isset( $legacy[ $key ] ) && is_string( $legacy[ $key ] ) ) {
				$legacy[ $key ] = wp_unslash( $legacy[ $key ] );
			}
		}

		return $legacy;
	}

	/**
	 * The three retired link settings, wherever they are still to be found.
	 *
	 * Both rows are consulted and the prefixed one wins, key by key, which is the
	 * same precedence migrate_legacy_rows() applies to everything else. A value
	 * coming from the unprefixed row is unslashed on the way, because that row was
	 * written by a release that slashed on the way in and this one is going
	 * straight into a template.
	 *
	 * @return array Whichever of print_style, post_text and page_text exist.
	 */
	private static function legacy_link_settings() {
		$legacy = get_option( self::LEGACY_OPTION, false );
		$stored = get_option( self::OPTION, array() );

		$source = array_merge(
			self::unslash_legacy( is_array( $legacy ) ? $legacy : array() ),
			is_array( $stored ) ? $stored : array()
		);

		return array_intersect_key(
			$source,
			array_flip( array( 'print_style', 'post_text', 'page_text' ) )
		);
	}

	/**
	 * Turn the retired link settings into the one custom template that replaced
	 * them.
	 *
	 * Up to 3.0.0 the link was a style out of four plus two labels; it is now a
	 * single HTML template, so a site's four answers have to become one string
	 * that renders what it rendered before. The style decides the markup -- an
	 * icon-only site stays icon-only, a text-only site stays text-only -- and the
	 * labels decide the wording:
	 *
	 * - both labels still the shipped pair: the wording collapses to
	 *   "Print This %POST_TYPE%", which says on a post and on a page exactly what
	 *   the two of them said separately, and says something sensible on a custom
	 *   post type as well, which neither of them could.
	 * - a customised label: `post_text` is used verbatim. Where the two labels
	 *   differ this loses the page wording, and there is no way round it -- one
	 *   template cannot hold two arbitrary strings. The readme's upgrade notice
	 *   says so.
	 * - already on the custom style: nothing happens at all. That row already
	 *   holds the only thing 3.0.0 stores.
	 *
	 * Idempotent by construction: the settings it reads are retired, so a second
	 * run finds none of them and returns before writing anything.
	 *
	 * @param array $source The retired settings, from legacy_link_settings().
	 * @return void
	 */
	private static function migrate_link_template( array $source ) {
		if ( array() === $source ) {
			return;
		}

		$style = isset( $source['print_style'] ) ? (int) $source['print_style'] : self::LEGACY_STYLE_ICON_TEXT;

		if ( self::LEGACY_STYLE_CUSTOM === $style ) {
			return;
		}

		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$stored['print_html'] = self::link_template( $style, self::migrated_link_text( $source ) );

		self::write( $stored );
	}

	/**
	 * The wording a migrated template carries.
	 *
	 * The stock pair is recognised through __() rather than by its English
	 * spelling, so a site running in another language and never having touched
	 * either label is still recognised as being on the shipped wording.
	 *
	 * @param array $source The retired settings, from legacy_link_settings().
	 * @return string
	 */
	private static function migrated_link_text( array $source ) {
		$stock_post = __( 'Print This Post', 'wp-print' );
		$stock_page = __( 'Print This Page', 'wp-print' );

		$post_text = isset( $source['post_text'] ) && is_scalar( $source['post_text'] )
			? trim( (string) $source['post_text'] )
			: '';
		$page_text = isset( $source['page_text'] ) && is_scalar( $source['page_text'] )
			? trim( (string) $source['page_text'] )
			: '';

		// An absent label read as the shipped one, because that is what the site
		// was rendering: get() merged the defaults in on every read.
		$post_text = '' === $post_text ? $stock_post : $post_text;
		$page_text = '' === $page_text ? $stock_page : $page_text;

		if ( $stock_post === $post_text && $stock_page === $page_text ) {
			return self::default_link_text();
		}

		return $post_text;
	}

	/**
	 * Take every retired setting off the row, and rewrite the placeholder that
	 * went with one of them.
	 *
	 * There were two bundled GIFs to choose between; there is now one inline SVG
	 * that takes its colour from the theme, so the setting has nothing left to
	 * choose. %PRINT_ICON_URL% goes with it -- an inline glyph has no URL -- and a
	 * custom template carrying it is rewritten to %PRINT_ICON%, which substitutes
	 * the glyph itself.
	 *
	 * The link settings retired alongside it go here too, after
	 * migrate_link_template()'s source has already been read out of the row.
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

		$stored = array_diff_key( $stored, array_flip( self::retired_keys() ) );

		if ( isset( $stored['print_html'] ) && is_string( $stored['print_html'] ) ) {
			$html = preg_replace( '#<img[^>]*%PRINT_ICON_URL%[^>]*>#i', '%PRINT_ICON%', $stored['print_html'] );

			$stored['print_html'] = str_replace( '%PRINT_ICON_URL%', '%PRINT_ICON%', (string) $html );
		}

		if ( $stored !== $before ) {
			self::write( $stored );
		}
	}
}
