<?php
/**
 * The settings screen.
 *
 * @package WP-Print
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds Settings -> Print with the WordPress Settings API.
 *
 * Replaces the hand-rolled form that posted to itself and did its own nonce and
 * capability handling. The option key and every stored key are unchanged; the
 * submitted field names now nest under print_options[...] because
 * register_setting() hands the whole array to one sanitize callback.
 */
class WP_Print_Admin {

	/**
	 * Settings group passed to register_setting() and settings_fields().
	 *
	 * @var string
	 */
	const GROUP = 'print_options_group';

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
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
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

		$defaults = array(
			'print_html' => WP_Print_Options::get_defaults()['print_html'],
			'disclaimer' => WP_Print_Options::default_disclaimer(),
		);

		// wp_add_inline_script(), not wp_localize_script(): the latter runs
		// html_entity_decode() over every scalar, so the disclaimer's &copy; reached
		// the page as a literal ©. Both render identically, but Restore Default would
		// then insert something other than the shipped default byte for byte.
		wp_add_inline_script(
			'wp-print-admin',
			'var wpPrintDefaults = ' . wp_json_encode( $defaults ) . ';',
			'before'
		);
	}

	/**
	 * Register the setting, its sections and its fields.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			self::GROUP,
			WP_Print_Options::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'WP_Print_Options', 'sanitize' ),
				'default'           => WP_Print_Options::get_defaults(),
			)
		);

		add_settings_section(
			'wp_print_styles',
			__( 'Print Styles', 'wp-print' ),
			'__return_empty_string',
			self::PAGE
		);

		add_settings_field(
			'post_text',
			__( 'Print Text Link For Post', 'wp-print' ),
			array( __CLASS__, 'field_text' ),
			self::PAGE,
			'wp_print_styles',
			array( 'key' => 'post_text' )
		);

		add_settings_field(
			'page_text',
			__( 'Print Text Link For Page', 'wp-print' ),
			array( __CLASS__, 'field_text' ),
			self::PAGE,
			'wp_print_styles',
			array( 'key' => 'page_text' )
		);

		add_settings_field(
			'print_icon',
			__( 'Print Icon', 'wp-print' ),
			array( __CLASS__, 'field_icon' ),
			self::PAGE,
			'wp_print_styles'
		);

		add_settings_field(
			'print_style',
			__( 'Print Text Link Style', 'wp-print' ),
			array( __CLASS__, 'field_style' ),
			self::PAGE,
			'wp_print_styles'
		);

		add_settings_section(
			'wp_print_options',
			__( 'Print Options', 'wp-print' ),
			'__return_empty_string',
			self::PAGE
		);

		$toggles = array(
			'comments'  => __( 'Print Comments?', 'wp-print' ),
			'links'     => __( 'Print Links?', 'wp-print' ),
			'images'    => __( 'Print Images?', 'wp-print' ),
			'thumbnail' => __( 'Print Thumbnail?', 'wp-print' ),
			'videos'    => __( 'Print Videos?', 'wp-print' ),
		);

		foreach ( $toggles as $key => $label ) {
			add_settings_field(
				$key,
				$label,
				array( __CLASS__, 'field_toggle' ),
				self::PAGE,
				'wp_print_options',
				array( 'key' => $key )
			);
		}

		add_settings_field(
			'disclaimer',
			__( 'Disclaimer/Copyright Text?', 'wp-print' ),
			array( __CLASS__, 'field_disclaimer' ),
			self::PAGE,
			'wp_print_options'
		);
	}

	/**
	 * The name attribute for a key inside the option array.
	 *
	 * @param string $key Option key.
	 * @return string
	 */
	private static function name( $key ) {
		return WP_Print_Options::OPTION . '[' . $key . ']';
	}

	/**
	 * A single-line text field.
	 *
	 * @param array $args Field args, with a 'key'.
	 * @return void
	 */
	public static function field_text( $args ) {
		printf(
			'<input type="text" name="%1$s" id="%2$s" value="%3$s" class="regular-text" />',
			esc_attr( self::name( $args['key'] ) ),
			esc_attr( 'wp-print-' . $args['key'] ),
			esc_attr( WP_Print_Options::get( $args['key'] ) )
		);
	}

	/**
	 * A yes/no dropdown.
	 *
	 * @param array $args Field args, with a 'key'.
	 * @return void
	 */
	public static function field_toggle( $args ) {
		$value = WP_Print_Options::can( $args['key'] );

		printf(
			'<select name="%1$s" id="%2$s"><option value="1"%3$s>%4$s</option><option value="0"%5$s>%6$s</option></select>',
			esc_attr( self::name( $args['key'] ) ),
			esc_attr( 'wp-print-' . $args['key'] ),
			selected( 1, $value, false ),
			esc_html__( 'Yes', 'wp-print' ),
			selected( 0, $value, false ),
			esc_html__( 'No', 'wp-print' )
		);
	}

	/**
	 * The icon picker: one radio per file in the plugin's images directory.
	 *
	 * @return void
	 */
	public static function field_icon() {
		$selected = WP_Print_Options::get( 'print_icon' );
		$dir      = WP_PRINT_DIR . 'images/';

		// One glob per extension rather than a {a,b} brace pattern: GLOB_BRACE is
		// not available on every platform - it is absent from musl-based builds, so
		// the whole picker would vanish there - and glob() silently returns nothing
		// when the flag is unsupported.
		$files = array();
		foreach ( array( 'gif', 'png', 'jpg', 'jpeg', 'svg', 'webp' ) as $extension ) {
			$files = array_merge( $files, (array) glob( $dir . '*.' . $extension ) );
		}

		$files = array_filter( $files );

		if ( ! $files ) {
			esc_html_e( 'No print icons were found.', 'wp-print' );

			return;
		}

		sort( $files );

		foreach ( $files as $file ) {
			$name = basename( $file );

			printf(
				'<p><label><input type="radio" name="%1$s" value="%2$s"%3$s />&nbsp;&nbsp;&nbsp;<img src="%4$s" alt="%2$s" />&nbsp;&nbsp;&nbsp;(%2$s)</label></p>',
				esc_attr( self::name( 'print_icon' ) ),
				esc_attr( $name ),
				checked( $selected, $name, false ),
				esc_url( WP_PRINT_URL . 'images/' . $name )
			);
		}
	}

	/**
	 * The style dropdown, plus the custom HTML template it reveals.
	 *
	 * @return void
	 */
	public static function field_style() {
		$style = (int) WP_Print_Options::get( 'print_style' );

		$choices = array(
			WP_Print_Link::STYLE_ICON_TEXT => __( 'Print Icon With Text Link', 'wp-print' ),
			WP_Print_Link::STYLE_ICON      => __( 'Print Icon Only', 'wp-print' ),
			WP_Print_Link::STYLE_TEXT      => __( 'Print Text Link Only', 'wp-print' ),
			WP_Print_Link::STYLE_CUSTOM    => __( 'Custom', 'wp-print' ),
		);

		echo '<select name="' . esc_attr( self::name( 'print_style' ) ) . '" id="wp-print-style" data-print-toggle="wp-print-custom">';
		foreach ( $choices as $value => $label ) {
			printf(
				'<option value="%1$d"%2$s>%3$s</option>',
				(int) $value,
				selected( $style, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';

		$hidden = WP_Print_Link::STYLE_CUSTOM === $style ? '' : ' hidden';
		?>
		<div id="wp-print-custom" class="wp-print-custom<?php echo esc_attr( $hidden ); ?>">
			<p>
				<textarea rows="3" cols="80" class="large-text code" name="<?php echo esc_attr( self::name( 'print_html' ) ); ?>" id="wp-print-html"><?php echo esc_textarea( WP_Print_Options::get( 'print_html' ) ); ?></textarea>
			</p>
			<p class="description">
				<?php esc_html_e( 'HTML is allowed. These placeholders are replaced when the link is rendered:', 'wp-print' ); ?>
			</p>
			<ul>
				<li><code>%PRINT_URL%</code> &mdash; <?php esc_html_e( 'URL to the printable post/page.', 'wp-print' ); ?></li>
				<li><code>%PRINT_TEXT%</code> &mdash; <?php esc_html_e( 'Print text link of the post/page that you have typed in above.', 'wp-print' ); ?></li>
				<li><code>%PRINT_ICON_URL%</code> &mdash; <?php esc_html_e( 'URL to the print icon you have chosen above.', 'wp-print' ); ?></li>
			</ul>
			<p>
				<button type="button" class="button" data-print-restore="print_html" data-print-target="wp-print-html">
					<?php esc_html_e( 'Restore Default Template', 'wp-print' ); ?>
				</button>
			</p>
		</div>
		<?php
	}

	/**
	 * The disclaimer textarea and its Restore Default button.
	 *
	 * @return void
	 */
	public static function field_disclaimer() {
		?>
		<p>
			<textarea rows="3" cols="80" class="large-text code" name="<?php echo esc_attr( self::name( 'disclaimer' ) ); ?>" id="wp-print-disclaimer"><?php echo esc_textarea( WP_Print_Options::get( 'disclaimer' ) ); ?></textarea>
		</p>
		<p class="description"><?php esc_html_e( 'HTML is allowed.', 'wp-print' ); ?></p>
		<p>
			<button type="button" class="button" data-print-restore="disclaimer" data-print-target="wp-print-disclaimer">
				<?php esc_html_e( 'Restore Default Template', 'wp-print' ); ?>
			</button>
		</p>
		<?php
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
			<style>.wp-print-custom.hidden { display: none; } .wp-print-custom { margin-top: 1em; }</style>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}

WP_Print_Admin::init();
