<?php
/**
 * Printer friendly post/page template.
 *
 * Copy this file to your theme's directory to customise it. WP-Print loads the
 * child theme's copy first, then the parent theme's, then this one, so your
 * changes survive an upgrade.
 *
 * Deliberately does not call wp_head() or wp_footer(): a print view that pulled
 * in the theme's stylesheets and every other plugin's assets would not print
 * cleanly. That is also why the script below is a plain tag.
 *
 * @package WP-Print
 */

defined( 'ABSPATH' ) || exit;

// Not every locale carries a region: get_bloginfo( 'language' ) returns 'ca' as
// well as 'en-US'. strpos() returns false for those and substr( $s, 0, false )
// is '', which used to emit lang="". Split on the dash only when there is one.
$print_language = get_bloginfo( 'language' );
$print_dash     = strpos( $print_language, '-' );

if ( false !== $print_dash ) {
	$print_language = substr( $print_language, 0, $print_dash );
}
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( $print_language ); ?>" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex, nofollow" />
	<?php // The print suffix comes from the wp_title filter Print_Template::render() adds; appending it here as well would print it twice. ?>
	<title><?php bloginfo( 'name' ); ?> <?php wp_title(); ?></title>
	<link rel="stylesheet" href="<?php echo esc_url( Print_Template::asset_url( 'print-css.css' ) ); ?>" media="screen, print" />
	<?php if ( is_rtl() ) : ?>
		<link rel="preconnect" href="https://fonts.googleapis.com" />
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
		<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@100;200;300;400;500;600;700;800;900&#038;display=swap" />
		<link rel="stylesheet" href="<?php echo esc_url( Print_Template::asset_url( 'print-css-rtl.css' ) ); ?>" media="screen, print" />
	<?php endif; ?>
	<link rel="canonical" href="<?php the_permalink(); ?>" />
	<script src="<?php echo esc_url( Print_Template::plugin_url( 'print.js' ) ); ?>" defer></script>
</head>
<body>

<main role="main" class="center">

	<?php if ( have_posts() ) : ?>

		<header class="entry-header">

			<span class="hat">
				<strong>
					- <?php bloginfo( 'name' ); ?>
					-
					<span dir="ltr"><?php echo esc_url( home_url( '/' ) ); ?></span>
					-
				</strong>
			</span>

			<?php
			while ( have_posts() ) :
				the_post();
				?>

				<h1 class="entry-title"><?php the_title(); ?></h1>

				<span class="entry-date">

					<?php esc_html_e( 'Posted By', 'wp-print' ); ?>

					<cite><?php the_author(); ?></cite>

					<?php esc_html_e( 'On', 'wp-print' ); ?>

					<time>
						<?php
						the_time(
							sprintf(
								/* translators: 1: date format, 2: time format */
								__( '%1$s @ %2$s', 'wp-print' ),
								get_option( 'date_format' ),
								get_option( 'time_format' )
							)
						);
						?>
					</time>

					<span>
						<?php esc_html_e( 'In', 'wp-print' ); ?>
						<?php print_categories(); ?> |
					</span>

					<a href="#comments_controls"><?php print_comments_number(); ?></a>

				</span>

		</header>

				<?php if ( print_can( 'thumbnail' ) && has_post_thumbnail() ) : ?>
					<div class="thumbnail"><?php the_post_thumbnail( 'medium' ); ?></div>
				<?php endif; ?>

				<div class="entry-content">
					<?php print_content(); ?>
				</div>

			<?php endwhile; ?>

		<div class="comments">
			<?php if ( print_can( 'comments' ) ) : ?>
				<?php comments_template(); ?>
			<?php endif; ?>
		</div>

		<footer class="footer">
			<p>
				<?php esc_html_e( 'Article printed from', 'wp-print' ); ?>
				<?php bloginfo( 'name' ); ?>:

				<strong dir="ltr"><?php echo esc_url( home_url( '/' ) ); ?></strong>
			</p>

			<p>
				<?php esc_html_e( 'URL to article', 'wp-print' ); ?>:
				<strong dir="ltr"><?php the_permalink(); ?></strong>
			</p>

			<?php if ( print_can( 'links' ) ) : ?>
				<p><?php print_links(); ?></p>
			<?php endif; ?>

			<p style="text-align: <?php echo is_rtl() ? 'left' : 'right'; ?>;" id="print-link">
				<a href="#print" data-print-action="print" title="<?php esc_attr_e( 'Click here to print.', 'wp-print' ); ?>">
					<?php esc_html_e( 'Click here to print.', 'wp-print' ); ?>
				</a>
			</p>

	<?php else : ?>

		<footer class="footer">
			<p><?php esc_html_e( 'No posts matched your criteria.', 'wp-print' ); ?></p>

	<?php endif; ?>

			<p style="text-align: center;"><?php print_disclaimer(); ?></p>
		</footer>

</main>

</body>
</html>
