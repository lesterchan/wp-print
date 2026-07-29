<?php
/**
 * The admin menu and the screen it renders.
 *
 * @package WP-Print
 */

defined( 'ABSPATH' ) || exit;

/**
 * Puts WP-Print under Settings and renders its one screen.
 *
 * Admin owns the menu and the screens; WP_Print_Settings owns
 * register_setting(), the sections, the fields and the sanitiser. The two meet
 * at two constants: Settings registers its sections against this class's PAGE,
 * and render_page() below calls settings_fields( WP_Print_Settings::GROUP ).
 *
 * A plugin whose only admin surface is settings gets add_options_page() and no
 * top-level menu.
 */
class WP_Print_Admin {

	/**
	 * The page slug.
	 *
	 * @var string
	 */
	const PAGE = 'wp-print';

	/**
	 * The capability the screen requires.
	 *
	 * A settings screen, so manage_options and nothing more specific: WP-Print
	 * has no data of its own to manage and so has never shipped a custom
	 * capability.
	 *
	 * @var string
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * The capability required for a given part of the screen.
	 *
	 * Every capability check in the plugin goes through here, so a site that
	 * wants its editors to reach the print settings has one filter to hook
	 * rather than a list of checks to find.
	 *
	 * @param string $context What the capability is being checked for.
	 * @return string
	 */
	public static function capability( $context = 'settings' ) {
		/**
		 * Filters the capability required to reach the WP-Print settings screen.
		 *
		 * @since 3.0.0
		 *
		 * @param string $capability The required capability.
		 * @param string $context    What the capability is being checked for.
		 */
		return (string) apply_filters( 'wp_print_capability', self::CAPABILITY, $context );
	}

	/**
	 * Hook the admin screen into WordPress.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WP_PRINT_MAIN_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Add the settings page under the Settings menu.
	 *
	 * @return void
	 */
	public static function add_page() {
		add_options_page(
			__( 'Print Options', 'wp-print' ),
			__( 'Print', 'wp-print' ),
			self::capability( 'menu' ),
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Add a Settings link to the plugin's row on the Plugins screen.
	 *
	 * @param array $links Existing action links.
	 * @return array
	 */
	public static function action_links( $links ) {
		if ( ! is_array( $links ) ) {
			$links = array();
		}

		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ) . '">' . esc_html__( 'Settings', 'wp-print' ) . '</a>'
		);

		return $links;
	}

	/**
	 * Load the screen's script.
	 *
	 * Vanilla, and with an empty dependency array: the two behaviours here are a
	 * class toggle and setting a textarea's value. The loading strategy goes in
	 * the $args array, which needs WordPress 6.3 and so is available on the 6.8
	 * floor.
	 *
	 * @param string $hook_suffix Current admin page.
	 * @return void
	 */
	public static function enqueue( $hook_suffix ) {
		if ( 'settings_page_' . self::PAGE !== $hook_suffix ) {
			return;
		}

		wp_enqueue_script(
			'wp-print-admin',
			WP_PRINT_URL . 'js/wp-print-admin.js',
			array(),
			WP_PRINT_VERSION,
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);

		/*
		 * Nested under a key rather than passed as two top-level strings.
		 * wp_localize_script() runs html_entity_decode() over every *scalar* it
		 * is given, which turned the disclaimer's &copy; into a literal © -- the
		 * two render identically, but Restore Default would then have inserted
		 * something other than the shipped default byte for byte. A value that
		 * is an array is passed through untouched, so one level of nesting is
		 * all it takes to keep the defaults exact.
		 */
		wp_localize_script(
			'wp-print-admin',
			'wpPrintL10n',
			array(
				'defaults' => array(
					'print_html' => WP_Print_Options::get_defaults()['print_html'],
					'disclaimer' => WP_Print_Options::default_disclaimer(),
				),
			)
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( self::capability( 'settings' ) ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-print' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Print Options', 'wp-print' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( WP_Print_Settings::GROUP );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}

WP_Print_Admin::init();
