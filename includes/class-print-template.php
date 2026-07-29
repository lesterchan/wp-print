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
class Print_Template {

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

		return plugin_dir_path( WP_PRINT_MAIN_FILE ) . self::bundled( $file );
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

		return plugins_url( self::bundled( $file ), WP_PRINT_MAIN_FILE );
	}

	/**
	 * The URL of a file that only ever ships with the plugin.
	 *
	 * @param string $file Path relative to the plugin root.
	 * @return string
	 */
	public static function plugin_url( $file ) {
		return plugins_url( $file, WP_PRINT_MAIN_FILE );
	}

	/**
	 * Render the print view and stop.
	 *
	 * @return void
	 */
	public static function render() {
		Print_Content::reset();

		add_filter( 'wp_title', array( __CLASS__, 'page_title' ) );
		add_filter( 'comments_template', array( __CLASS__, 'comments_template' ) );

		// The bundled print-posts.php reads $print_options directly for the
		// disclaimer, and a theme's copy of it may do the same, so the variable has
		// to be in scope for the include.
		$print_options = Print_Options::get();

		require self::locate( 'print-posts.php' );

		exit;
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
	 * Mark the document title as a print view.
	 *
	 * @param string $title Title so far.
	 * @return string
	 */
	public static function page_title( $title ) {
		return $title . ' &raquo; ' . __( 'Print', 'wp-print' );
	}
}
