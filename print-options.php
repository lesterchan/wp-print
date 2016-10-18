<?php
### Variables Variables Variables
$base_name = plugin_basename( 'wp-print/print-options.php' );
$base_page = 'admin.php?page=' . $base_name;
$print_settings = array('print_options');


### Form Processing
if( ! empty( $_POST['Submit'] ) ) {
    check_admin_referer( 'wp-print_options' );

    $print_options = array();
    $print_options['post_text']         = ! empty( $_POST['print_post_text'] )  ? addslashes( trim( wp_filter_kses( $_POST['print_post_text'] ) ) ) : '';
    $print_options['page_text']         = ! empty( $_POST['print_page_text'] )  ? addslashes( trim( wp_filter_kses( $_POST['print_page_text'] ) ) ) : '';
    $print_options['print_icon']        = ! empty( $_POST['print_icon'] )       ? trim( $_POST['print_icon'] ) : '';
    $print_options['print_style']       = isset( $_POST['print_style'] )        ? intval($_POST['print_style'] ) : 1;
    $print_options['print_html']        = ! empty( $_POST['print_html'] )       ? trim( $_POST['print_html'] ) : '';
    $print_options['comments']          = isset( $_POST['print_comments'] )     ? intval( $_POST['print_comments'] ): 0;
    $print_options['links']             = isset( $_POST['print_links'] )        ? intval( $_POST['print_links'] ) : 1;
    $print_options['images']            = isset( $_POST['print_images'] )       ? intval( $_POST['print_images'] ) : 0;
    $print_options['thumbnail']         = isset( $_POST['print_thumbnail'] )    ? intval( $_POST['print_thumbnail'] ) : 0;
    $print_options['videos']            = isset( $_POST['print_videos'] )       ? intval( $_POST['print_videos'] ) : 1;
    $print_options['disclaimer']        = ! empty( $_POST['print_disclaimer'] ) ? trim( $_POST['print_disclaimer'] ) : '';
    $update_print_queries = array();
    $update_print_text = array();
    $update_print_queries[] = update_option( 'print_options', $print_options );
    $update_print_text[] = __( 'Print Options', 'wp-print' );
    $i = 0;
    $text = '';
    foreach( $update_print_queries as $update_print_query ) {
        if( $update_print_query ) {
            $text .= '<p style="color: green;">' . $update_print_text[$i] . ' ' .__( 'Updated', 'wp-print' ) . '</p>';
        }
        $i++;
    }
    if( empty( $text ) ) {
        $text = '<p style="color: red;">' . __( 'No Print Option Updated', 'wp-print' ) . '</p>';
    }
}

$print_options = get_option( 'print_options' );
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
            <th scope="row" valign="top"><?php _e('Print Thumbnail?', 'wp-print'); ?></th>
            <td>
                <select name="print_thumbnail" size="1">
                    <option value="1"<?php selected('1', $print_options['thumbnail']); ?>><?php _e('Yes', 'wp-print'); ?></option>
                    <option value="0"<?php selected('0', $print_options['thumbnail']); ?>><?php _e('No', 'wp-print'); ?></option>
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