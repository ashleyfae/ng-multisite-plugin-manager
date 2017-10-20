<?php
/**
 * Plugin Name: NG Multisite Plugin Manager
 * Plugin URI: https://www.nosegraze.com
 * Description: Manage plugin access permissions across your entire multisite network.
 * Version: 1.0
 * Author: Ashley Gibson
 * Author URI: https://www.nosegraze.com
 * Network: true
 * License: GPL2
 *
 * @package   ng-multisite-plugin-manager
 * @copyright Copyright (c) 2017, Ashley Gibson
 * @license   GPL2+
 *
 * Forked from "Multisite Plugin Manager" by Aaron Edwards.
 * @link      http://wordpress.org/extend/plugins/multisite-plugin-manager/
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

/**
 * Include required files.
 */
require_once 'includes/global-management.php';
require_once 'includes/single-site-management.php';

/**
 * Remove restricted plugins from plugin list.
 *
 * @param array $plugins
 *
 * @since 1.0
 * @return array
 */
function ng_mpm_filter_plugins_list( $plugins ) {

	// Admins get to see all plugins.
	if ( is_super_admin() ) {
		return $plugins;
	}

	$auto_activate     = (array) get_site_option( 'pm_auto_activate_list', array() );
	$user_control      = (array) get_site_option( 'pm_user_control_list', array() );
	$supporter_control = (array) get_site_option( 'pm_supporter_control_list', array() );
	$override_plugins  = (array) get_option( 'pm_plugin_override_list', array() );

	foreach ( (array) $plugins as $plugin_file => $plugin_data ) {

		if ( in_array( $plugin_file, $user_control ) || in_array( $plugin_file, $auto_activate ) || in_array( $plugin_file, $supporter_control ) || in_array( $plugin_file, $override_plugins ) ) {
			//do nothing - leave it in
		} else {
			unset( $plugins[ $plugin_file ] ); //remove plugin
		}

	}

	return $plugins;

}

add_filter( 'all_plugins', 'ng_mpm_filter_plugins_list' );

/**
 * Remove plugin meta
 *
 * @param array  $plugin_meta
 * @param string $plugin_file
 *
 * @since 1.0
 * @return array
 */
function ng_mpm_remove_plugin_meta( $plugin_meta, $plugin_file ) {

	if ( is_network_admin() || is_super_admin() ) {
		return $plugin_meta;
	}

	remove_all_actions( 'after_plugin_row_' . $plugin_file );

	return array();

}

add_filter( 'plugin_row_meta', 'ng_mpm_remove_plugin_meta', 10, 2 );

/**
 * Remove plugin update row for non-network admins.
 *
 * @since 1.0
 * @return void
 */
function ng_mpm_remove_plugin_update_row() {

	if ( ! is_network_admin() && ! is_super_admin() ) {
		remove_all_actions( 'after_plugin_row' );
	}

}

add_action( 'admin_init', 'ng_mpm_remove_plugin_update_row' );