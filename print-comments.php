<?php
/**
 * Printer friendly comments template.
 *
 * Copy this file to your theme's directory to customise it. WP-Print loads the
 * child theme's copy first, then the parent theme's, then this one, so your
 * changes survive an upgrade.
 *
 * @package WP-Print
 */

defined( 'ABSPATH' ) || exit;

if ( ! empty( $comments ) ) :
	$print_comment_count = 1;
	?>
	<span style="float: <?php echo is_rtl() ? 'left' : 'right'; ?>;" id="comments_controls">
		<?php print_comments_number(); ?>
		(<a href="#comments_box" data-print-action="open" data-print-target="comments_box"><?php esc_html_e( 'Open', 'wp-print' ); ?></a>
		| <a href="#comments_box" data-print-action="close" data-print-target="comments_box"><?php esc_html_e( 'Close', 'wp-print' ); ?></a>)
	</span>
	<div id="comments_box">
		<p id="CommentTitle"><?php print_comments_number(); ?> <?php esc_html_e( 'To', 'wp-print' ); ?> "<?php the_title(); ?>"</p>
		<?php
		/*
		 * comments_template() globalises $comment, and comment_author(),
		 * comment_date() and comment_type() all read that global, so the loop
		 * variable has to be it rather than a local under another name.
		 */
		foreach ( $comments as $comment ) : // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- See above.
			?>
			<p class="CommentDate">
				<strong>#<?php echo esc_html( number_format_i18n( $print_comment_count ) ); ?>
					<?php comment_type( __( 'Comment', 'wp-print' ), __( 'Trackback', 'wp-print' ), __( 'Pingback', 'wp-print' ) ); ?></strong>
				<?php esc_html_e( 'By', 'wp-print' ); ?> <u><?php comment_author(); ?></u>
				<?php esc_html_e( 'On', 'wp-print' ); ?>
				<?php
				comment_date(
					sprintf(
						/* translators: 1: date format, 2: time format */
						__( '%1$s @ %2$s', 'wp-print' ),
						get_option( 'date_format' ),
						get_option( 'time_format' )
					)
				);
				?>
			</p>
			<div class="CommentContent">
				<?php if ( '0' === $comment->comment_approved ) : ?>
					<p><em><?php esc_html_e( 'Your comment is awaiting moderation.', 'wp-print' ); ?></em></p>
				<?php endif; ?>
				<?php print_comments_content(); ?>
			</div>
			<?php ++$print_comment_count; ?>
		<?php endforeach; ?>
		<hr class="Divider" style="text-align: center;" />
	</div>
<?php endif; ?>
