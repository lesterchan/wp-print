<?php
/**
 * The registered setting, its sections, its fields and its sanitiser.
 *
 * @package WP-Print
 */

defined( 'ABSPATH' ) || exit;

/**
 * Everything the Settings API needs to know about WP-Print's one option row.
 *
 * The split with WP_Print_Admin is the family's: Admin owns the menu and the
 * screen it renders, Settings owns register_setting(), the sections, the fields
 * and the sanitize callback. They meet at two constants -- Settings registers
 * its sections against WP_Print_Admin::PAGE, and the screen calls
 * settings_fields( WP_Print_Settings::GROUP ).
 *
 * This replaced a hand-rolled form that posted to itself and did its own nonce
 * and capability handling. Every stored key is unchanged; the submitted field
 * names nest under wp_print_options[...] because register_setting() hands the
 * whole array to one sanitize callback.
 */
class WP_Print_Settings {

	/**
	 * Settings group passed to register_setting() and settings_fields().
	 *
	 * Spelled the same as the settings row: one name for one thing, so a form
	 * that posts the group and a callback that writes the row can never drift
	 * apart.
	 *
	 * @var string
	 */
	const GROUP = 'wp_print_options';

	/**
	 * Settings section: how the print link itself looks.
	 *
	 * @var string
	 */
	const SECTION_STYLES = 'wp_print_styles';

	/**
	 * Settings section: what goes into the printed document.
	 *
	 * Named for what it holds rather than the 'wp_print_options' it used to be
	 * called, which is now the name of the settings row -- two different things
	 * answering to one string is how a section id ends up passed to
	 * register_setting().
	 *
	 * @var string
	 */
	const SECTION_CONTENT = 'wp_print_content';

	/**
	 * Hook the registration into WordPress.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register the setting, its sections and its fields.
	 *
	 * @return void
	 */
	public static function register() {
		register_setting(
			self::GROUP,
			WP_Print_Options::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => WP_Print_Options::get_defaults(),
			)
		);

		add_settings_section(
			self::SECTION_STYLES,
			__( 'Print Styles', 'wp-print' ),
			'__return_empty_string',
			WP_Print_Admin::PAGE
		);

		add_settings_field(
			'post_text',
			__( 'Print Text Link For Post', 'wp-print' ),
			array( __CLASS__, 'field_text' ),
			WP_Print_Admin::PAGE,
			self::SECTION_STYLES,
			array( 'key' => 'post_text' )
		);

		add_settings_field(
			'page_text',
			__( 'Print Text Link For Page', 'wp-print' ),
			array( __CLASS__, 'field_text' ),
			WP_Print_Admin::PAGE,
			self::SECTION_STYLES,
			array( 'key' => 'page_text' )
		);

		add_settings_field(
			'print_style',
			__( 'Print Text Link Style', 'wp-print' ),
			array( __CLASS__, 'field_style' ),
			WP_Print_Admin::PAGE,
			self::SECTION_STYLES
		);

		add_settings_section(
			self::SECTION_CONTENT,
			__( 'Print Options', 'wp-print' ),
			'__return_empty_string',
			WP_Print_Admin::PAGE
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
				WP_Print_Admin::PAGE,
				self::SECTION_CONTENT,
				array( 'key' => $key )
			);
		}

		add_settings_field(
			'disclaimer',
			__( 'Disclaimer/Copyright Text?', 'wp-print' ),
			array( __CLASS__, 'field_disclaimer' ),
			WP_Print_Admin::PAGE,
			self::SECTION_CONTENT
		);
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
	 * this on sanitize_option_wp_print_options, which means it also runs for
	 * update_option() calls made by WP-CLI, a migration or another plugin. Those
	 * are usually partial, and blanking the disclaimer and the custom template
	 * because a caller only wanted to flip one toggle is not a defensible reading
	 * of "sanitize".
	 *
	 * @param mixed $input Raw submitted value.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$defaults = WP_Print_Options::get_defaults();
		$current  = WP_Print_Options::get();
		$input    = is_array( $input ) ? $input : array();
		$clean    = array();

		foreach ( WP_Print_Options::text_keys() as $key ) {
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

		foreach ( WP_Print_Options::html_keys() as $key ) {
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

		foreach ( WP_Print_Options::bool_keys() as $key ) {
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

		// Preserve anything a filter or an older version stored that this screen
		// does not render, rather than dropping it on the first save - except a
		// key the plugin has deliberately retired, which must not survive.
		$current = array_diff_key( $current, array_flip( WP_Print_Options::retired_keys() ) );

		return array_merge( $current, $clean );
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
				<textarea rows="3" class="large-text code" name="<?php echo esc_attr( self::name( 'print_html' ) ); ?>" id="wp-print-html"><?php echo esc_textarea( WP_Print_Options::get( 'print_html' ) ); ?></textarea>
			</p>
			<p class="description">
				<?php esc_html_e( 'HTML is allowed. These placeholders are replaced when the link is rendered:', 'wp-print' ); ?>
			</p>
			<ul>
				<li><code>%PRINT_URL%</code> &mdash; <?php esc_html_e( 'URL to the printable post/page.', 'wp-print' ); ?></li>
				<li><code>%PRINT_TEXT%</code> &mdash; <?php esc_html_e( 'Print text link of the post/page that you have typed in above.', 'wp-print' ); ?></li>
				<li><code>%PRINT_ICON%</code> &mdash; <?php esc_html_e( 'The printer glyph, as an inline SVG that takes its colour from your theme.', 'wp-print' ); ?></li>
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
			<textarea rows="3" class="large-text code" name="<?php echo esc_attr( self::name( 'disclaimer' ) ); ?>" id="wp-print-disclaimer"><?php echo esc_textarea( WP_Print_Options::get( 'disclaimer' ) ); ?></textarea>
		</p>
		<p class="description"><?php esc_html_e( 'HTML is allowed.', 'wp-print' ); ?></p>
		<p>
			<button type="button" class="button" data-print-restore="disclaimer" data-print-target="wp-print-disclaimer">
				<?php esc_html_e( 'Restore Default Template', 'wp-print' ); ?>
			</button>
		</p>
		<?php
	}
}

WP_Print_Settings::init();
