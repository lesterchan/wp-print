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
 * The three overridable files - print-posts.php, print-comments.php and
 * print-css.css - keep their names and their lookup order: child theme, then
 * parent theme, then the copies bundled with the plugin. That is the documented
 * way to restyle the print view without losing the changes on upgrade.
 */
class Print_Template {

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

		return plugin_dir_path( WP_PRINT_MAIN_FILE ) . $file;
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

		return plugins_url( $file, WP_PRINT_MAIN_FILE );
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
