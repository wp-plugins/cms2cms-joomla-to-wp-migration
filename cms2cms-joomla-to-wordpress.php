<?php
/*
	Plugin Name: CMS2CMS Joomla to WordPress migration
	Plugin URI: http://www.cms2cms.com
	Description: Migrate your website content from Joomla to WordPress easily and automatedly in just a few simple steps.
	Version: 1.0.0
	Author: MagneticOne
	Author URI: http://magneticone.com
	License: GPLv2
*/
/*  Copyright 2013  MagneticOne  (email : contact@magneticone.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


define( 'CMS2CMS_VERSION', '0.3.9' );
define( 'CMS2CMS_APP', 'http://app.cms2cms.com' ); /* no trailing slash */
define( 'CMS2CMS_VIDEO_LINK', 'http://www.youtube.com/watch?feature=player_detailpage&v=DQK01NbrCdw#t=25s' );

define( 'CMS2CMS_OPTION_TABLE', 'cms2cms_options' );

if ( !defined('CMS2CMS_PLUGIN_SOURCE_NAME') ) {
    define( 'CMS2CMS_PLUGIN_SOURCE_NAME', __('Joomla!', 'cms2cms-mirgation') );
}
if ( !defined('CMS2CMS_PLUGIN_SOURCE_TYPE') ) {
    define( 'CMS2CMS_PLUGIN_SOURCE_TYPE', 'Joomla' );
}
if ( !defined('CMS2CMS_PLUGIN_TARGET_NAME') ) {
    define( 'CMS2CMS_PLUGIN_TARGET_NAME', __('WordPress', 'cms2cms-mirgation') );
}
if ( !defined('CMS2CMS_PLUGIN_TARGET_TYPE') ) {
    define( 'CMS2CMS_PLUGIN_TARGET_TYPE', 'WordPress' );
}

if ( !defined('CMS2CMS_PLUGIN_NAME_LONG') ) {
    define( 'CMS2CMS_PLUGIN_NAME_LONG', sprintf(
        __('CMS2CMS: Automated %s to %s Migration ', 'cms2cms-mirgation'),
        CMS2CMS_PLUGIN_SOURCE_NAME,
        CMS2CMS_PLUGIN_TARGET_NAME
    ) );
}
if ( !defined('CMS2CMS_PLUGIN_NAME_SHORT') ) {
    define( 'CMS2CMS_PLUGIN_NAME_SHORT', sprintf(
        __('%s to %s', 'cms2cms-mirgation'),
        CMS2CMS_PLUGIN_SOURCE_NAME,
        CMS2CMS_PLUGIN_TARGET_NAME
    ) );
}

$pluginurl = plugin_dir_url( __FILE__ );
if ( preg_match( '/^https/', $pluginurl ) && !preg_match( '/^https/', get_bloginfo('url') ) )
    $pluginurl = preg_replace( '/^https/', 'http', $pluginurl );
define( 'CMS2CMS_FRONT_URL', $pluginurl );
unset( $pluginurl );


/* ****************************************************** */

function cms2cms_plugin_menu() {
    add_plugins_page( CMS2CMS_PLUGIN_NAME_LONG, CMS2CMS_PLUGIN_NAME_SHORT, 'activate_plugins', 'cms2cms-mirgation', 'cms2cms_menu_page' );
}
add_action('admin_menu', 'cms2cms_plugin_menu');

function cms2cms_menu_page(){
	include 'ui.php';
}

function cms2cms_plugin_init() {
    load_plugin_textdomain( 'cms2cms-migration', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action('plugins_loaded', 'cms2cms_plugin_init');



function cms2cms_install() {
    global $wpdb;
    $table_name = $wpdb->prefix . CMS2CMS_OPTION_TABLE;

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
              id mediumint(9) NOT NULL AUTO_INCREMENT,
              option_name VARCHAR(64) DEFAULT '' NOT NULL,
              option_value VARCHAR(64) DEFAULT '' NOT NULL,
              UNIQUE KEY id (id)
    );";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

}
register_activation_hook( __FILE__, 'cms2cms_install' );

function cms2cms_get_option ( $name ) {
    global $wpdb;
    $table_name = $wpdb->prefix . CMS2CMS_OPTION_TABLE;
    $value = $wpdb->get_var( $wpdb->prepare(
        "SELECT option_value
            FROM $table_name
            WHERE option_name = %s
            LIMIT 1
	    ",
        $name
    ));

    return $value;
}

function cms2cms_set_option ( $name, $value ) {
    global $wpdb;
    $table_name = $wpdb->prefix . CMS2CMS_OPTION_TABLE;
    $wpdb->insert( $table_name, array( 'option_name' => $name, 'option_value' => $value ) );
}

function cms2cms_delete_option ( $name ) {
    global $wpdb;
    $table_name = $wpdb->prefix . CMS2CMS_OPTION_TABLE;
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM $table_name
		     WHERE option_name = %s
		    ",
            $name
        )
    );
}


/* ******************************************************* */
/* Assets */
/* ******************************************************* */

function cms2cms_wp_admin_style() {
    wp_register_style( 'cms2cms-admin-css', CMS2CMS_FRONT_URL . 'css/cms2cms.css', false, CMS2CMS_VERSION );
    wp_enqueue_style( 'cms2cms-admin-css' );

    wp_register_script( 'cms2cms-jsonp', CMS2CMS_FRONT_URL . 'js/jsonp.js', false, CMS2CMS_VERSION );
    wp_enqueue_script( 'cms2cms-jsonp' );

    wp_register_script( 'cms2cms-admin-js', CMS2CMS_FRONT_URL . 'js/cms2cms.js', array('jquery', 'cms2cms-jsonp'), CMS2CMS_VERSION );
    wp_enqueue_script( 'cms2cms-admin-js' );
}
add_action( 'admin_enqueue_scripts', 'cms2cms_wp_admin_style' );


/* ******************************************************* */
/* AJAX */
/* ******************************************************* */

/**
 * Save Access key and email
 */
function cms2cms_save_options() {
    $key = substr( $_POST['accessKey'], 0, 64 );
    $login = sanitize_email( $_POST['login'] );

    $cms2cms_site_url = get_site_url();
    $bridge_depth = str_replace($cms2cms_site_url, '', CMS2CMS_FRONT_URL);
    $bridge_depth = trim($bridge_depth, DIRECTORY_SEPARATOR);
    $bridge_depth = explode(DIRECTORY_SEPARATOR, $bridge_depth);
    $bridge_depth = count( $bridge_depth );


    $response = array(
        'errors' => _('Provided credentials are not correct: '.$key.' = '.$login )
    );

    if ( $key && $login ) {
        cms2cms_delete_option('cms2cms-key');
        cms2cms_set_option('cms2cms-key', $key);

        cms2cms_delete_option('cms2cms-login');
        cms2cms_set_option('cms2cms-login', $login);

        cms2cms_delete_option('cms2cms-depth');
        cms2cms_set_option('cms2cms-depth', $bridge_depth);

        $response = array(
            'success' => true
        );
    }

    echo json_encode($response);

    die(); // this is required to return a proper result
}
add_action('wp_ajax_cms2cms_save_options', 'cms2cms_save_options');

/**
 * Get auth string
 */
function cms2cms_get_options() {
    $key = cms2cms_get_option('cms2cms-key');
    $login = cms2cms_get_option('cms2cms-login');

    $response = 0;

    if ( $key && $login ) {
        $response = array(
            'email' => $login,
            'accessKey' => $key,
        );
    }

    echo json_encode($response);

    die(); // this is required to return a proper result
}
add_action('wp_ajax_cms2cms_get_options', 'cms2cms_get_options');

