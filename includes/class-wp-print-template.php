<?php
/**
 * The print view template.
 *
 * @package WP-Print
 */

defined( 'ABSPATH' ) || exit;

/**
 * Locates and renders the printable document.
 *
 * The three overridable files keep the names a theme has always looked for -
 * print-posts.php, print-comments.php and print-css.css - and the lookup order
 * is unchanged: child theme, then parent theme, then the copy bundled with the
 * plugin. That is the documented way to restyle the print view without losing
 * the changes on upgrade.
 *
 * The bundled copies moved in 3.0.0: the two templates now live in includes/
 * and the stylesheet in css/, because the plugin root holds only the main file,
 * index.php and uninstall.php. The theme-side names deliberately did not move
 * with them, so an existing override still wins.
 */
class WP_Print_Template {

	/**
	 * Where the plugin keeps each overridable file, keyed by the name a theme
	 * overrides it with.
	 *
	 * @var array
	 */
	const OVERRIDABLE = array(
		'print-posts.php'    => 'includes/print-posts.php',
		'print-comments.php' => 'includes/print-comments.php',
		'print-css.css'      => 'css/wp-print.css',
	);

	/**
	 * The plugin's own copy of an overridable file.
	 *
	 * @param string $file Theme-side file name.
	 * @return string Path relative to the plugin root.
	 */
	private static function bundled( $file ) {
		return isset( self::OVERRIDABLE[ $file ] ) ? self::OVERRIDABLE[ $file ] : $file;
	}

	/**
	 * Find an overridable template file.
	 *
	 * @param string $file File name, for instance 'print-posts.php'.
	 * @return string Absolute path.
	 */
	public static function locate( $file ) {
		$stylesheet = get_stylesheet_directory() . '/' . $file;

		if ( file_exists( $stylesheet ) ) {
			return $stylesheet;
		}

		$template = get_template_directory() . '/' . $file;

		if ( file_exists( $template ) ) {
			return $template;
		}

		return WP_PRINT_DIR . self::bundled( $file );
	}

	/**
	 * The URL of an overridable asset, honouring a theme's copy.
	 *
	 * @param string $file File name, for instance 'print-css.css'.
	 * @return string
	 */
	public static function asset_url( $file ) {
		if ( file_exists( get_stylesheet_directory() . '/' . $file ) ) {
			return get_stylesheet_directory_uri() . '/' . $file;
		}

		if ( file_exists( get_template_directory() . '/' . $file ) ) {
			return get_template_directory_uri() . '/' . $file;
		}

		return WP_PRINT_URL . self::bundled( $file );
	}

	/**
	 * The URL of a file that only ever ships with the plugin.
	 *
	 * @param string $file Path relative to the plugin root.
	 * @return string
	 */
	public static function plugin_url( $file ) {
		return WP_PRINT_URL . $file;
	}

	/**
	 * Render the print view and stop.
	 *
	 * @return void
	 */
	public static function render() {
		WP_Print_Content::reset();

		self::register_assets();

		add_filter( 'wp_title', array( __CLASS__, 'page_title' ) );
		add_filter( 'comments_template', array( __CLASS__, 'comments_template' ) );
		add_filter( 'comments_array', array( __CLASS__, 'hide_protected_comments' ), 10, 2 );

		// The bundled print-posts.php reads $print_options directly for the
		// disclaimer, and a theme's copy of it may do the same, so the variable has
		// to be in scope for the include.
		$print_options = WP_Print_Options::get();

		require self::locate( 'print-posts.php' );

		exit;
	}

	/**
	 * Register the print view's own stylesheet and script.
	 *
	 * The print view is a standalone document that deliberately never calls
	 * wp_head(), so nothing here is hooked to an enqueue action: the handles are
	 * registered, then printed by name from the template with
	 * wp_print_styles( 'wp-print' ) / wp_print_scripts( 'wp-print' ). Printing by
	 * name is what keeps the theme's and every other plugin's assets out of a
	 * page whose whole purpose is to print cleanly, while still going through
	 * WP_Dependencies rather than emitting hand-written tags.
	 *
	 * Registered here rather than in the template so that a theme's own copy of
	 * print-posts.php only has to print the handles.
	 *
	 * @return void
	 */
	public static function register_assets() {
		wp_register_style(
			'wp-print',
			self::asset_url( 'print-css.css' ),
			array(),
			WP_PRINT_VERSION,
			'screen, print'
		);

		wp_register_script(
			'wp-print',
			self::plugin_url( 'js/wp-print.js' ),
			array(),
			WP_PRINT_VERSION,
			array(
				'strategy'  => 'defer',
				'in_footer' => false,
			)
		);
	}

	/**
	 * Point comments_template() at the printable comments file.
	 *
	 * @return string
	 */
	public static function comments_template() {
		return self::locate( 'print-comments.php' );
	}

	/**
	 * Withhold the thread on a post whose password has not been entered.
	 *
	 * The bundled print-comments.php guards itself, and this is the same guard in
	 * the one place a theme cannot take a copy of. Both are wanted: the templates
	 * exist to be copied into a theme, and a copy taken before the guard existed
	 * would go on printing a locked post's discussion for as long as the theme
	 * lives. Emptying the array core is about to hand the template settles it for
	 * every copy at once -- have_comments() is false, whatever file runs.
	 *
	 * comments_array rather than the query arguments, because the count core
	 * derives is not what the document prints: WP_Print_Content::comments_number()
	 * answers "Comments Hidden" from the same check, so a number is never shown
	 * that would itself say how much discussion is behind the lock.
	 *
	 * @param array $comments Comments core is about to hand the template.
	 * @param int   $post_id  The post they belong to.
	 * @return array
	 */
	public static function hide_protected_comments( $comments, $post_id ) {
		return post_password_required( $post_id ) ? array() : (array) $comments;
	}

	/**
	 * Mark the document title as a print view.
	 *
	 * @param string $title Title so far.
	 * @return string
	 */
	public static function page_title( $title ) {
		return $title . ' &raquo; ' . __( 'Print', 'wp-print' );
	}
}
