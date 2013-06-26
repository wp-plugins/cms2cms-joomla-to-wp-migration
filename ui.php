<?php

if ( !defined('CMS2CMS_VERSION') ) {
    die();
}

$nonce = $_REQUEST['_wpnonce'];
if ( wp_verify_nonce( $nonce, 'cms2cms_logout' ) && $_POST['cms2cms_logout'] == 1 ) {
    cms2cms_delete_option('cms2cms-login');
    cms2cms_delete_option('cms2cms-key');
    cms2cms_delete_option('cms2cms-depth');
}

$user_ID = get_current_user_id();
$user_info = get_userdata($user_ID);

$cms2cms_access_login = cms2cms_get_option('cms2cms-login');
$cms2cms_access_key = cms2cms_get_option('cms2cms-key');
$cms2cms_is_activated = ($cms2cms_access_key != false);

$cms2cms_target_url = get_site_url();

$cms2cms_bridge_url = str_replace($cms2cms_target_url, '', CMS2CMS_FRONT_URL);
$cms2cms_bridge_url = '/' . trim($cms2cms_bridge_url, DIRECTORY_SEPARATOR);

$cms2cms_action_register = CMS2CMS_APP.'/auth/register';
$cms2cms_action_login = CMS2CMS_APP.'/auth/login';
$cms2cms_action_forgot_password = CMS2CMS_APP.'/auth/forgot-password';
$cms2cms_action_verify = CMS2CMS_APP.'/wizard/verify';
$cms2cms_action_run = CMS2CMS_APP.'/wizard';

$cms2cms_authentication = array(
    'email' => $cms2cms_access_login,
    'accessKey' => $cms2cms_access_key
);

$cms2cms_download_bridge = CMS2CMS_APP.'/wizard/get-bridge?callback=plugin&authentication='.urlencode(json_encode($cms2cms_authentication));

$cms2cms_ajax_nonce = wp_create_nonce('cms2cms-ajax-security-check');

?>


<div class="wrap">

<div class="cms2cms-plugin">

    <div id="icon-plugins" class="icon32"><br></div>
    <h2><?php echo CMS2CMS_PLUGIN_NAME_LONG; ?></h2>

    <?php if ($cms2cms_is_activated) { ?>
        <div class="cms2cms-message">
                <span>
                    <?php echo  sprintf(
                        __('You are logged in CMS2CMS as %s', 'cms2cms-migration'),
                        get_option('cms2cms-login')
                    ); ?>
                </span>
                <div class="cms2cms-logout">
                    <form action="" method="post">
                        <input type="hidden" name="cms2cms_logout" value="1"/>
                        <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('cms2cms_logout');?>"/>
                        <button class="button">
                            &times;
                            <?php _e('Logout', 'cms2cms-migration');?>
                        </button>
                    </form>
                </div>
        </div>
    <?php } ?>

	<ol id="cms2cms_accordeon">
    <?php

    $cms2cms_step_counter = 1;

    if ( !$cms2cms_is_activated ) { ?>
        <li id="cms2cms_accordeon_item_id_<?php echo $cms2cms_step_counter++;?>" class="cms2cms_accordeon_item cms2cms_accordeon_item_register">
            <h3>
                <?php _e('Sign In', 'cms2cms-migration'); ?>
                <span class="spinner"></span>
            </h3>
            <form action="<?php echo $cms2cms_action_register; ?>"
                  callback="callback_auth"
                  validate="auth_check_password"
                  class="step_form"
                  id="cms2cms_form_register">

                <h3 class="nav-tab-wrapper">
                    <a href="<?php echo $cms2cms_action_register; ?>" class="nav-tab nav-tab-active" change_li_to=''>
                        <?php _e('Register CMS2CMS Account', 'cms2cms-migration'); ?>
                    </a>
                    <a href="<?php echo $cms2cms_action_login; ?>" class="nav-tab">
                        <?php _e('Login', 'cms2cms-migration'); ?>
                    </a>
                    <a href="<?php echo $cms2cms_action_forgot_password; ?>" class="nav-tab cms2cms-real-link">
                        <?php _e('Forgot password?', 'cms2cms-migration'); ?>
                    </a>
                </h3>

                <table class="form-table">
                    <tbody>
                        <tr valign="top">
                            <th scope="row">
                                <label for="cms2cms-user-email"><?php _e('Email:', 'cms2cms-migration');?></label>
                            </th>
                            <td>
                                <input type="text" id="cms2cms-user-email" name="email" value="<?php echo $user_info->user_email ?>" class="regular-text"/>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">
                                <label for="cms2cms-user-password"><?php _e('Password:', 'cms2cms-migration'); ?></label>
                            </th>
                            <td>
                                <input type="password" id="cms2cms-user-password" name="password" value="" class="regular-text"/>
                                <p class="description for__cms2cms_accordeon_item_register">
                                    <?php _e('Minimum 6 characters', 'cms2cms-migration'); ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div>
                    <input type="hidden" id="cms2cms-site-url" name="siteUrl" value="<?php echo $cms2cms_target_url; ?>"/>
                    <input type="hidden" id="cms2cms-bridge-url" name="targetBridgePath" value="<?php echo $cms2cms_bridge_url; ?>"/>
                    <input type="hidden" id="cms2cms-access-key" name="accessKey" value="<?php echo $cms2cms_access_key; ?>"/>
                    <input type="hidden" name="termsOfService" value="1">
                    <input type="hidden" name="peioaj" value="">
                    <div class="error_message"></div>

                    <button type="submit" class="button button-primary button-large">
                        <?php _e('Continue', 'cms2cms-migration'); ?>
                    </button>
                </div>
            </form>
        </li>

        <?php } /* cms2cms_is_activated */ ?>

        <li id="cms2cms_accordeon_item_id_<?php echo $cms2cms_step_counter++;?>" class="cms2cms_accordeon_item">
            <h3>
                <?php echo sprintf(
                    __('Connect %s', 'cms2cms-migration'),
                    CMS2CMS_PLUGIN_SOURCE_NAME
                ); ?>
                <span class="spinner"></span>
            </h3>
            <form action="<?php echo $cms2cms_action_verify; ?>"
                  callback="callback_verify"
                  validate="verify"
                  class="step_form"
                  id="cms2cms_form_verify">
                <ol>
                    <li>
                        <a href="<?php echo $cms2cms_download_bridge;?>" class="button">
                            <?php echo __('Download the Bridge file', 'cms2cms-migration'); ?>
                        </a>
                    </li>
                    <li>
                        <?php _e('Unzip it', 'cms2cms-migration');?>
                        <p class="description">
                            <?php _e('Find the cms2cms.zip on your computer, right-click it and select Extract in the menu.', 'cms2cms-migration'); ?>
                        </p>
                    </li>
                    <li>
                        <?php echo sprintf(
                            __('Upload to the root folder on your %s website.', 'cms2cms-migration'),
                            CMS2CMS_PLUGIN_SOURCE_NAME
                        ); ?>
                        <a href="<?php echo CMS2CMS_VIDEO_LINK?>" target="_blank"><?php _e('Watch the video', 'cms2cms-migration');?></a>
                    </li>
                    <li>
                        <?php echo sprintf(
                            __('Specify %s website URL', 'cms2cms-migration'),
                            CMS2CMS_PLUGIN_SOURCE_NAME
                        ); ?>
                        <br/>
                        <input type="text" name="sourceUrl" value="" class="regular-text" placeholder="<?php
                            echo sprintf(
                                __('http://your_%s_website.com/', 'cms2cms-migration'),
                                strtolower(CMS2CMS_PLUGIN_SOURCE_TYPE)
                            );
                        ?>"/>
                        <input type="hidden" name="sourceType" value="<?php echo CMS2CMS_PLUGIN_SOURCE_TYPE; ?>" />
                        <input type="hidden" name="targetUrl" value="<?php echo $cms2cms_target_url;?>" />
                        <input type="hidden" name="targetType" value="<?php echo CMS2CMS_PLUGIN_TARGET_TYPE; ?>" />
                        <input type="hidden" name="targetBridgePath" value="<?php echo $cms2cms_bridge_url;?>" />
                    </li>
                </ol>
                <div class="error_message"></div>
                <button type="submit" class="button button-primary button-large">
                    <?php _e('Verify connection', 'cms2cms-migration'); ?>
                </button>
            </form>
        </li>

        <li id="cms2cms_accordeon_item_id_<?php echo $cms2cms_step_counter++;?>" class="cms2cms_accordeon_item">
            <h3>
                <?php _e('Configure and Start Migration', 'cms2cms-migration'); ?>
                <span class="spinner"></span>
            </h3>
            <form action="<?php echo $cms2cms_action_run; ?>"
                  class="cms2cms_step_migration_run step_form"
                  method="post"
                  id="cms2cms_form_run">
                <?php _e('You\'ll be redirected to CMS2CMS application website in order to select your migration preferences and complete your migration.', 'cms2cms-migration'); ?>
                <input type="hidden" name="sourceUrl" value="">
                <input type="hidden" name="sourceType" value="">
                <input type="hidden" name="targetUrl" value="">
                <input type="hidden" name="targetType" value="">
                <input type="hidden" name="migrationHash" value="">
                <input type="hidden" name="targetBridgePath" value="<?php echo $cms2cms_bridge_url; ?>"/>
                <div class="error_message"></div>
                <button type="submit" class="button button-primary button-large">
                    <?php _e('Start migration', 'cms2cms-migration'); ?>
                </button>
            </form>
        </li>
    </ol>

 </div> <!-- /plugin -->

 <div id="cms2cms-description">
     <p>
         <?php
        _e('CMS2CMS.com is the one-of-its kind tool for fast, accurate and trouble-free website migration from Joomla to WordPress. Just a few mouse clicks - and your Joomla articles, categories, images, users, comments, internal links etc are safely delivered to the new WordPress website.', 'cms2cms-migration');
        ?>
     </p>
     <p>
         <a href="http://www.cms2cms.com/how-it-works/" class="button" target="_blank">
             <?php _e('See How it Works', 'cms2cms-migration'); ?>
         </a>
     </p>
     <p>
        <?php
        _e('Take a quick demo tour to get the idea about how your migration will be handled.', 'cms2cms-migration');?>
     </p>
 </div>

</div> <!-- /wrap -->
