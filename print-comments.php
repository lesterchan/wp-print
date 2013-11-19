<?php
/*
 * WordPress Plugin: WP-Print
 * Copyright (c) 2012 Lester "GaMerZ" Chan
 *
 * File Written By:
 * - Lester "GaMerZ" Chan
 * - http://lesterchan.net
 *
 * File Information:
 * - Printer Friendly Comments Template
 * - wp-content/plugins/wp-print/print-comments.php
 */

if($comments) : ?>
	<?php $comment_count = 1; global $text_direction; ?>
	<span style='float:<?php echo ('rtl' == $text_direction) ? 'left' : 'right'; ?>' id='comments_controls'><?php print_comments_number(); ?> (<a  href="#" onclick="javascript:document.getElementById('comments_box').style.display = 'block'; return false;"><?php _e('Open', 'wp-print'); ?></a> | <a href="#" onclick="javascript:document.getElementById('comments_box').style.display = 'none'; return false;"><?php _e('Close', 'wp-print'); ?></a>)</span>
	<div id="comments_box">
		<p id="CommentTitle"><?php print_comments_number(); ?> <?php _e('To', 'wp-print'); ?> "<?php the_title(); ?>"</p>
		<?php foreach ($comments as $comment) : ?>
			<p class="CommentDate">
				<strong>#<?php echo number_format_i18n($comment_count); ?> <?php comment_type(__('Comment', 'wp-print'), __('Trackback', 'wp-print'), __('Pingback', 'wp-print')); ?></strong> <?php _e('By', 'wp-print'); ?> <u><?php comment_author(); ?></u> <?php _e('On', 'wp-print'); ?> <?php comment_date(sprintf(__('%s @ %s', 'wp-print'), get_option('date_format'), get_option('time_format'))); ?>
			</p>
			<div class="CommentContent">
				<?php if ($comment->comment_approved == '0') : ?>
					<p><em><?php _e('Your comment is awaiting moderation.', 'wp-print'); ?></em></p>
				<?php endif; ?>
				<?php print_comments_content(); ?>
			</div>
			<?php $comment_count++; ?>
		<?php endforeach; ?>
		<hr class="Divider" style="text-align: center;" />
	</div>
<?php endif; ?>