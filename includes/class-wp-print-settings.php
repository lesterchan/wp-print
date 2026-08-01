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
 * and the sanitize callback. They meet at the tabs -- Settings registers each
 * section against tab_page( $tab ) and says which tab that is, and the screen
 * draws the tab it was asked for and calls
 * settings_fields( WP_Print_Settings::GROUP ).
 *
 * The screen is two tabs, Settings and Templates, which is what every plugin in
 * this family with a template to edit looks like. One register_setting(), one
 * group and one option row serve both: a tab divides a screen, not the data
 * behind it.
 *
 * This replaced a hand-rolled form that posted to itself and did its own nonce
 * and capability handling. The submitted field names nest under
 * wp_print_options[...] because register_setting() hands the whole array to one
 * sanitize callback, which is also where a key the plugin has retired is dropped.
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
	 * Settings section: the print link's HTML template.
	 *
	 * Was 'wp_print_styles' and titled Print Styles, when the link was a style out
	 * of four plus two labels. There are no styles: there is a template, and the
	 * section is named for it.
	 *
	 * @var string
	 */
	const SECTION_LINK = 'wp_print_link';

	/**
	 * Settings section: what goes into the printed document.
	 *
	 * Named for what it holds rather than the 'wp_print_options' it used to be
	 * called, which is now the name of the settings row -- two different things
	 * answering to one string is how a section id ends up passed to
	 * register_setting(). Its title says what it holds for the same reason: the
	 * screen is called Print Settings, and a section sharing that name is a
	 * heading that looks like the page it is on.
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
	 * The tabs the screen is split across.
	 *
	 * Named Settings and Templates in every plugin in this family that has a
	 * template to edit -- not "Print Settings" and "Print Templates", because the
	 * h1 above them has already said which plugin this is.
	 *
	 * @return array Tab slug => label.
	 */
	public static function tabs() {
		return array(
			'settings'  => __( 'Settings', 'wp-print' ),
			'templates' => __( 'Templates', 'wp-print' ),
		);
	}

	/**
	 * Which tab the request is asking for.
	 *
	 * Anything unrecognised falls back to the first, so a hand-typed or a stale
	 * bookmarked ?tab= gets a screen rather than an empty form.
	 *
	 * @return string
	 */
	public static function current_tab() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Chooses which tab to draw and nothing else; nothing is read from the request beyond that and nothing is written.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings';

		return array_key_exists( $tab, self::tabs() ) ? $tab : 'settings';
	}

	/**
	 * The Settings API page a tab's sections are registered against.
	 *
	 * Each tab is its own page as far as add_settings_section() and
	 * do_settings_sections() are concerned, which is what stops one tab drawing
	 * the other's fields. It is emphatically not its own *setting*: there is one
	 * register_setting(), one group and one option row across both, because a tab
	 * is a division of a screen and not of the data behind it.
	 *
	 * @param string $tab Tab slug.
	 * @return string
	 */
	public static function tab_page( $tab ) {
		return WP_Print_Admin::PAGE . '-' . $tab;
	}

	/**
	 * The URL of one tab.
	 *
	 * @param string $tab Tab slug.
	 * @return string
	 */
	public static function tab_url( $tab ) {
		return add_query_arg(
			array(
				'page' => WP_Print_Admin::PAGE,
				'tab'  => $tab,
			),
			admin_url( 'options-general.php' )
		);
	}

	/**
	 * Register the setting, its sections and its fields.
	 *
	 * @return void
	 */
	public static function register() {
		$settings  = self::tab_page( 'settings' );
		$templates = self::tab_page( 'templates' );

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
			self::SECTION_LINK,
			__( 'Link', 'wp-print' ),
			'__return_empty_string',
			$templates
		);

		add_settings_field(
			'print_html',
			__( 'Print Link Template', 'wp-print' ),
			array( __CLASS__, 'field_template' ),
			$templates,
			self::SECTION_LINK
		);

		add_settings_section(
			self::SECTION_CONTENT,
			__( 'What Gets Printed', 'wp-print' ),
			'__return_empty_string',
			$settings
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
				$settings,
				self::SECTION_CONTENT,
				array( 'key' => $key )
			);
		}

		add_settings_field(
			'disclaimer',
			__( 'Disclaimer/Copyright Text?', 'wp-print' ),
			array( __CLASS__, 'field_disclaimer' ),
			$settings,
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
	 * reverting to a default or blanking. That is not a nicety, it is what makes
	 * the two tabs safe: the Settings API hands this callback only the fields the
	 * submitting form posted, so the Templates tab posts print_html and nothing
	 * else and the Settings tab posts everything else and not print_html. A
	 * sanitizer that returned only what it was given would blank a site's link
	 * template the first time anybody saved the other tab, silently.
	 *
	 * It holds for the same reason outside the screen. register_setting() hangs
	 * this on sanitize_option_wp_print_options, which means it also runs for
	 * update_option() calls made by WP-CLI, a migration or another plugin. Those
	 * are usually partial, and blanking the disclaimer and the link template
	 * because a caller only wanted to flip one toggle is not a defensible reading
	 * of "sanitize".
	 *
	 * @param mixed $input Raw submitted value.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$current = WP_Print_Options::get();
		$input   = is_array( $input ) ? $input : array();
		$clean   = array();

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
	 * The custom HTML template, and its Restore Default button.
	 *
	 * The only control the print link has. Up to 3.0.0 it was hidden behind a
	 * four-way style dropdown and revealed for the Custom entry alone -- but the
	 * other three entries were templates too, written in PHP instead of in this
	 * box, and one of them with a placeholder for the post type says everything
	 * all four said.
	 *
	 * @return void
	 */
	public static function field_template() {
		?>
		<p>
			<textarea rows="3" class="large-text code" name="<?php echo esc_attr( self::name( 'print_html' ) ); ?>" id="wp-print-html"><?php echo esc_textarea( WP_Print_Options::get( 'print_html' ) ); ?></textarea>
		</p>
		<p class="description">
			<?php esc_html_e( 'HTML is allowed. These placeholders are replaced when the link is rendered:', 'wp-print' ); ?>
		</p>
		<ul>
			<li><code>%PRINT_URL%</code> &mdash; <?php esc_html_e( 'URL to the printable post/page.', 'wp-print' ); ?></li>
			<li><code>%POST_TYPE%</code> &mdash; <?php esc_html_e( 'What is being printed, in the singular: Post, Page, or the name of a custom post type.', 'wp-print' ); ?></li>
			<li><code>%PRINT_ICON%</code> &mdash; <?php esc_html_e( 'The printer glyph, as an inline SVG that takes its colour from your theme.', 'wp-print' ); ?></li>
		</ul>
		<p>
			<button type="button" class="button" data-print-restore="print_html" data-print-target="wp-print-html">
				<?php esc_html_e( 'Restore Default Template', 'wp-print' ); ?>
			</button>
		</p>
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
