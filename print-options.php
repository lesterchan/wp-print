<?php
/*
 * WordPress Plugin: WP-Print
 * Copyright (c) 2013 Lester "GaMerZ" Chan
 *
 * File Written By:
 * - Lester "GaMerZ" Chan
 * - http://lesterchan.net
 *
 * File Information:
 * - Print Options Page
 * - wp-content/plugins/wp-print/print-options.php
 */


### Variables Variables Variables
$base_name = plugin_basename('wp-print/print-options.php');
$base_page = 'admin.php?page='.$base_name;
$id = intval($_GET['id']);
$mode = trim($_GET['mode']);
$print_settings = array('print_options');


### Form Processing
// Update Options
if(!empty($_POST['Submit'])) {
	check_admin_referer('wp-print_options');
	$print_options = array();
	$print_options['post_text'] = addslashes(trim(wp_filter_kses($_POST['print_post_text'])));
	$print_options['page_text'] = addslashes(trim(wp_filter_kses($_POST['print_page_text'])));
	$print_options['print_icon'] = trim($_POST['print_icon']);
	$print_options['print_style'] = intval($_POST['print_style']);
	$print_options['print_html'] = trim($_POST['print_html']);
	$print_options['comments'] = intval($_POST['print_comments']);
	$print_options['links'] = intval($_POST['print_links']);
	$print_options['images'] = intval($_POST['print_images']);
	$print_options['videos'] = intval($_POST['print_videos']);
	$print_options['disclaimer'] = trim($_POST['print_disclaimer']);
	$update_print_queries = array();
	$update_print_text = array();
	$update_print_queries[] = update_option('print_options', $print_options);
	$update_print_text[] = __('Print Options', 'wp-print');
	$i=0;
	$text = '';
	foreach($update_print_queries as $update_print_query) {
		if($update_print_query) {
			$text .= '<font color="green">'.$update_print_text[$i].' '.__('Updated', 'wp-print').'</font><br />';
		}
		$i++;
	}
	if(empty($text)) {
		$text = '<font color="red">'.__('No Print Option Updated', 'wp-print').'</font>';
	}
}
// Uninstall WP-Print
if(!empty($_POST['do'])) {
	switch($_POST['do']) {
		case __('UNINSTALL WP-Print', 'wp-print') :
			if(trim($_POST['uninstall_print_yes']) == 'yes') {
				echo '<div id="message" class="updated fade">';
				echo '<p>';
				foreach($print_settings as $setting) {
					$delete_setting = delete_option($setting);
					if($delete_setting) {
						echo '<font color="green">';
						printf(__('Setting Key \'%s\' has been deleted.', 'wp-print'), "<strong><em>{$setting}</em></strong>");
						echo '</font><br />';
					} else {
						echo '<font color="red">';
						printf(__('Error deleting Setting Key \'%s\'.', 'wp-print'), "<strong><em>{$setting}</em></strong>");
						echo '</font><br />';
					}
				}
				echo '</p>';
				echo '</div>';
				$mode = 'end-UNINSTALL';
			}
			break;
	}
}

### Determines Which Mode It Is
switch($mode) {
		//  Deactivating WP-Print
		case 'end-UNINSTALL':
			$deactivate_url = 'plugins.php?action=deactivate&amp;plugin=wp-print/wp-print.php';
			if(function_exists('wp_nonce_url')) {
				$deactivate_url = wp_nonce_url($deactivate_url, 'deactivate-plugin_wp-print/wp-print.php');
			}
			echo '<div class="wrap">';
			echo '<h2>'.__('Uninstall WP-Print', 'wp-print').'</h2>';
			echo '<p><strong>'.sprintf(__('<a href="%s">Click Here</a> To Finish The Uninstallation And WP-Print Will Be Deactivated Automatically.', 'wp-print'), $deactivate_url).'</strong></p>';
			echo '</div>';
			break;
	// Main Page
	default:
	$print_options = get_option('print_options');
?>
<script type="text/javascript">
	/* <![CDATA[*/
	function check_print_style() {
		if (parseInt(jQuery("#print_style").val()) == 4) {
				jQuery("#print_style_custom").show();
		} else {
			if(jQuery("#print_style_custom").is(":visible")) {
				jQuery("#print_style_custom").hide();
			}
		}
	}
	function print_default_templates(template) {
		var default_template;
		switch(template) {
			case 'html':
				default_template = '<a href="%PRINT_URL%" rel="nofollow" title="%PRINT_TEXT%">%PRINT_TEXT%</a>';
				break;
			case 'disclaimer':
				default_template = '<?php echo js_escape(sprintf(__('Copyright &copy; %s %s. All rights reserved.', 'wp-print'), date('Y'), get_option('blogname'))); ?>';
				break;
		}
		jQuery("#print_template_" + template).val(default_template);
	}
	/* ]]> */
</script>
<?php if(!empty($text)) { echo '<!-- Last Action --><div id="message" class="updated fade"><p>'.$text.'</p></div>'; } ?>
<form method="post" action="<?php echo admin_url('admin.php?page='.plugin_basename(__FILE__)); ?>">
<?php wp_nonce_field('wp-print_options'); ?>
<div class="wrap">
	<?php screen_icon(); ?>
	<h2><?php _e('Print Options', 'wp-print'); ?></h2>
	<h3><?php _e('Print Styles', 'wp-print'); ?></h3>
	<table class="form-table">
		<tr>
			<th scope="row" valign="top"><?php _e('Print Text Link For Post', 'wp-print'); ?></th>
			<td>
				<input type="text" name="print_post_text" value="<?php echo stripslashes($print_options['post_text']); ?>" size="30" />
			</td>
		</tr>
		<tr>
			<th scope="row" valign="top"><?php _e('Print Text Link For Page', 'wp-print'); ?></th>
			<td>
				<input type="text" name="print_page_text" value="<?php echo stripslashes($print_options['page_text']); ?>" size="30" />
			</td>
		</tr>
		<tr>
			<th scope="row" valign="top"><?php _e('Print Icon', 'wp-print'); ?></th>
			<td>
				<?php
					$print_icon = $print_options['print_icon'];
					$print_icon_url = plugins_url('wp-print/images');
					$print_icon_path = WP_PLUGIN_DIR.'/wp-print/images';
					if($handle = @opendir($print_icon_path)) {
						while (false !== ($filename = readdir($handle))) {
							if ($filename != '.' && $filename != '..') {
								if(is_file($print_icon_path.'/'.$filename)) {
									echo '<p>';
									if($print_icon == $filename) {
										echo '<input type="radio" name="print_icon" value="'.$filename.'" checked="checked" />'."\n";
									} else {
										echo '<input type="radio" name="print_icon" value="'.$filename.'" />'."\n";
									}
									echo '&nbsp;&nbsp;&nbsp;';
									echo '<img src="'.$print_icon_url.'/'.$filename.'" alt="'.$filename.'" />'."\n";
									echo '&nbsp;&nbsp;&nbsp;('.$filename.')';
									echo '</p>'."\n";
								}
							}
						}
						closedir($handle);
					}
				?>
			</td>
		</tr>
		<tr>
			<th scope="row" valign="top"><?php _e('Print Text Link Style', 'wp-print'); ?></th>
			<td>
				<select name="print_style" id="print_style" size="1" onchange="check_print_style();">
					<option value="1"<?php selected('1', $print_options['print_style']); ?>><?php _e('Print Icon With Text Link', 'wp-print'); ?></option>
					<option value="2"<?php selected('2', $print_options['print_style']); ?>><?php _e('Print Icon Only', 'wp-print'); ?></option>
					<option value="3"<?php selected('3', $print_options['print_style']); ?>><?php _e('Print Text Link Only', 'wp-print'); ?></option>
					<option value="4"<?php selected('4', $print_options['print_style']); ?>><?php _e('Custom', 'wp-print'); ?></option>
				</select>
				<div id="print_style_custom" style="display: <?php if(intval($print_options['print_style']) == 4) { echo 'block'; } else { echo 'none'; } ?>; margin-top: 20px;">
					<textarea rows="2" cols="80" name="print_html" id="print_template_html"><?php echo htmlspecialchars(stripslashes($print_options['print_html'])); ?></textarea><br />
					<?php _e('HTML is allowed.', 'wp-print'); ?><br />
					%PRINT_URL% - <?php _e('URL to the printable post/page.', 'wp-print'); ?><br />
					%PRINT_TEXT% - <?php _e('Print text link of the post/page that you have typed in above.', 'wp-print'); ?><br />
					%PRINT_ICON_URL% - <?php _e('URL to the print icon you have chosen above.', 'wp-print'); ?><br />
					<input type="button" name="RestoreDefault" value="<?php _e('Restore Default Template', 'wp-print'); ?>" onclick="print_default_templates('html');" class="button" />
				</div>
			</td>
		</tr>
	</table>
	<h3><?php _e('Print Options', 'wp-print'); ?></h3>
	<table class="form-table">
		 <tr>
			<th scope="row" valign="top"><?php _e('Print Comments?', 'wp-print'); ?></th>
			<td>
				<select name="print_comments" size="1">
					<option value="1"<?php selected('1', $print_options['comments']); ?>><?php _e('Yes', 'wp-print'); ?></option>
					<option value="0"<?php selected('0', $print_options['comments']); ?>><?php _e('No', 'wp-print'); ?></option>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row" valign="top"><?php _e('Print Links?', 'wp-print'); ?></th>
			<td>
				<select name="print_links" size="1">
					<option value="1"<?php selected('1', $print_options['links']); ?>><?php _e('Yes', 'wp-print'); ?></option>
					<option value="0"<?php selected('0', $print_options['links']); ?>><?php _e('No', 'wp-print'); ?></option>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row" valign="top"><?php _e('Print Images?', 'wp-print'); ?></th>
			<td>
				<select name="print_images" size="1">
					<option value="1"<?php selected('1', $print_options['images']); ?>><?php _e('Yes', 'wp-print'); ?></option>
					<option value="0"<?php selected('0', $print_options['images']); ?>><?php _e('No', 'wp-print'); ?></option>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row" valign="top"><?php _e('Print Videos?', 'wp-print'); ?></th>
			<td>
				<select name="print_videos" size="1">
					<option value="1"<?php selected('1', $print_options['videos']); ?>><?php _e('Yes', 'wp-print'); ?></option>
					<option value="0"<?php selected('0', $print_options['videos']); ?>><?php _e('No', 'wp-print'); ?></option>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row" valign="top">
				<?php _e('Disclaimer/Copyright Text?', 'wp-print'); ?>
				<br /><br />
				<input type="button" name="RestoreDefault" value="<?php _e('Restore Default Template', 'wp-print'); ?>" onclick="print_default_templates('disclaimer');" class="button" />
			</th>
			<td>
				<textarea rows="2" cols="80" name="print_disclaimer" id="print_template_disclaimer"><?php echo htmlspecialchars(stripslashes($print_options['disclaimer'])); ?></textarea><br /><?php _e('HTML is allowed.', 'wp-print'); ?><br />
			</td>
		</tr>
	</table>
	<p class="submit">
		<input type="submit" name="Submit" class="button" value="<?php _e('Save Changes', 'wp-print'); ?>" />
	</p>
</div>
</form>
<p>&nbsp;</p>

<!-- Uninstall WP-Print -->
<form method="post" action="<?php echo admin_url('admin.php?page='.plugin_basename(__FILE__)); ?>">
<div class="wrap">
	<h3><?php _e('Uninstall WP-Print', 'wp-print'); ?></h3>
	<p>
		<?php _e('Deactivating WP-Print plugin does not remove any data that may have been created, such as the print options. To completely remove this plugin, you can uninstall it here.', 'wp-print'); ?>
	</p>
	<p style="color: red">
		<strong><?php _e('WARNING:', 'wp-print'); ?></strong><br />
		<?php _e('Once uninstalled, this cannot be undone. You should use a Database Backup plugin of WordPress to back up all the data first.', 'wp-print'); ?>
	</p>
	<p style="color: red">
		<strong><?php _e('The following WordPress Options will be DELETED:', 'wp-print'); ?></strong><br />
	</p>
	<table class="widefat">
		<thead>
			<tr>
				<th><?php _e('WordPress Options', 'wp-print'); ?></th>
			</tr>
		</thead>
		<tr>
			<td valign="top">
				<ol>
				<?php
					foreach($print_settings as $settings) {
						echo '<li>'.$settings.'</li>'."\n";
					}
				?>
				</ol>
			</td>
		</tr>
	</table>
	<p>&nbsp;</p>
	<p style="text-align: center;">
		<input type="checkbox" name="uninstall_print_yes" value="yes" />&nbsp;<?php _e('Yes', 'wp-print'); ?><br /><br />
		<input type="submit" name="do" value="<?php _e('UNINSTALL WP-Print', 'wp-print'); ?>" class="button" onclick="return confirm('<?php _e('You Are About To Uninstall WP-Print From WordPress.\nThis Action Is Not Reversible.\n\n Choose [Cancel] To Stop, [OK] To Uninstall.', 'wp-print'); ?>')" />
	</p>
</div>
</form>
<?php
} // End switch($mode)
?>