<?php
/**
 * Global Plugin Management
 *
 * @package   ng-multisite-plugin-manager
 * @copyright Copyright (c) 2017, Ashley Gibson
 * @license   GPL2+
 */

/**
 * Register network admin menu
 *
 * @since 1.0
 * @return void
 */
function ng_mpm_add_menu() {
	add_submenu_page( 'plugins.php', __( 'Plugin Management', 'ng-multisite-plugin-manager' ), __( 'Plugin Management', 'ng-multisite-plugin-manager' ), 'manage_network_options', 'plugin-management', 'ng_mpm_render_admin_page' );
}

add_action( 'network_admin_menu', 'ng_mpm_add_menu' );

/**
 * Render network admin page
 *
 * @since 1.0
 * @return void
 */
function ng_mpm_render_admin_page() {

	if ( ! current_user_can( 'manage_network_options' ) ) {
		wp_die( __( 'Access forbidden.', 'ng-multisite-plugin-manager' ) );
	}

	$plugins           = get_plugins();
	$auto_activate     = (array) get_site_option( 'pm_auto_activate_list', array() );
	$user_control      = (array) get_site_option( 'pm_user_control_list', array() );
	$supporter_control = (array) get_site_option( 'pm_supporter_control_list', array() );
	?>
	<div class="wrap">
		<h1><?php _e( 'Manage Plugins', 'ng-multisite-plugin-manager' ); ?></h1>

		<?php
		if ( isset( $_GET['ng-mpm-notice'] ) && 'settings-updated' == $_GET['ng-mpm-notice'] ) {
			?>
			<div id="message" class="updated notice is-dismissible">
				<p><?php _e( 'Plugin settings updated.', 'ng-multisite-plugin-manager' ); ?></p>
			</div>
			<?php
		}
		?>

		<form action="" method="POST">
			<table class="widefat">
				<thead>
				<tr>
					<th><?php _e( 'Name', 'ng-multisite-plugin-manager' ); ?></th>
					<th><?php _e( 'Version', 'ng-multisite-plugin-manager' ); ?></th>
					<th><?php _e( 'Author', 'ng-multisite-plugin-manager' ); ?></th>
					<th title="<?php _e( 'Users may activate/deactivate', 'ng-multisite-plugin-manager' ); ?>"><?php _e( 'User Control', 'ng-multisite-plugin-manager' ); ?></th>
					<th><?php _e( 'Mass Activate', 'ng-multisite-plugin-manager' ); ?></th>
					<th><?php _e( 'Mass Deactivate', 'ng-multisite-plugin-manager' ); ?></th>
				</tr>
				</thead>

				<tbody>
				<?php
				foreach ( $plugins as $file => $plugin ) {
					// Skip network only/active plugins.
					if ( is_network_only_plugin( $file ) || is_plugin_active_for_network( $file ) ) {
						continue;
					}

					$user_checked      = in_array( $file, $user_control );
					$supporter_checked = in_array( $file, $supporter_control );
					$auto_checked      = in_array( $file, $auto_activate );
					?>
					<tr>
						<td><?php echo esc_html( $plugin['Name'] ); ?></td>
						<td><?php echo esc_html( $plugin['Version'] ); ?></td>
						<td><?php echo esc_html( $plugin['Author'] ); ?></td>
						<td>
							<select name="control[<?php echo esc_attr( $file ); ?>]">
								<option value="none" <?php selected( empty( $user_checked ) && empty( $supporter_checked ) && empty( $auto_checked ) ); ?>><?php _e( 'None', 'ng-multisite-plugin-manager' ); ?></option>
								<option value="all" <?php selected( $user_checked ); ?>><?php _e( 'All Users', 'ng-multisite-plugin-manager' ); ?></option>
								<option value="auto" <?php selected( $auto_checked ); ?>><?php _e( 'Auto-Activate (All Users)', 'ng-multisite-plugin-manager' ); ?></option>
							</select>
						</td>
						<td>
							<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'mass_activate', urlencode( $file ) ), 'ng_mpm_activate_all' ) ); ?>"><?php _e( 'Activate All', 'ng-multisite-plugin-manager' ); ?></a>
						</td>
						<td>
							<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'mass_deactivate', urlencode( $file ) ), 'ng_mpm_deactivate_all' ) ); ?>"><?php _e( 'Deactivate All', 'ng-multisite-plugin-manager' ); ?></a>
						</td>
					</tr>
					<?php
				}
				?>
				</tbody>
			</table>

			<?php submit_button( __( 'Update Options', 'ng-multisite-plugin-manager' ) ); ?>
			<?php wp_nonce_field( 'ng_mpm_save_global_plugin_settings', 'ng_mpm_save_global_plugin_settings_nonce' ); ?>
		</form>
	</div>
	<?php

}

/**
 * Save global plugin settings
 *
 * @since 1.0
 * @return void
 */
function ng_mpm_save_global_settings() {

	if ( ! isset( $_POST['ng_mpm_save_global_plugin_settings_nonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( $_POST['ng_mpm_save_global_plugin_settings_nonce'], 'ng_mpm_save_global_plugin_settings' ) ) {
		wp_die( __( 'Failed nonce security check.', 'ng-multisite-plugin-manager' ) );
	}

	if ( ! current_user_can( 'manage_network_options' ) ) {
		wp_die( __( 'You do not have permission to perform this action.', 'ng-multisite-plugin-manager' ) );
	}

	if ( ! isset( $_POST['control'] ) || ! is_array( $_POST['control'] ) ) {
		return;
	}

	$supporter_control = array();
	$user_control      = array();
	$auto_activate     = array();

	foreach ( $_POST['control'] as $plugin => $value ) {
		if ( $value == 'none' ) {
			continue;
		}

		switch ( $value ) {
			case 'supporters' :
				$supporter_control[] = sanitize_text_field( $plugin );
				break;

			case 'all' :
				$user_control[] = sanitize_text_field( $plugin );
				break;

			case 'auto' :
				$auto_activate[] = sanitize_text_field( $plugin );
				break;
		}
	}

	if ( ! empty( $supporter_control ) ) {
		update_site_option( 'pm_supporter_control_list', array_unique( $supporter_control ) );
	} else {
		delete_site_option( 'pm_supporter_control_list' );
	}

	if ( ! empty( $user_control ) ) {
		update_site_option( 'pm_user_control_list', array_unique( $user_control ) );
	} else {
		delete_site_option( 'pm_user_control_list' );
	}

	if ( ! empty( $auto_activate ) ) {
		update_site_option( 'pm_auto_activate_list', array_unique( $auto_activate ) );
	} else {
		delete_site_option( 'pm_auto_activate_list' );
	}

	$redirect_url = add_query_arg( array(
		'page'          => 'plugin-management',
		'ng-mpm-notice' => 'settings-updated'
	), network_admin_url( 'plugins.php' ) );

	wp_safe_redirect( $redirect_url );
	exit;

}

add_action( 'admin_init', 'ng_mpm_save_global_settings' );

/**
 * Activate plugin on all sites
 *
 * @since 1.0
 * @return void
 */
function ng_mpm_process_activate_all() {

	if ( ! isset( $_GET['mass_activate'] ) || ! isset( $_GET['_wpnonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'ng_mpm_activate_all' ) ) {
		wp_die( __( 'Failed nonce security check.', 'ng-multisite-plugin-manager' ) );
	}

	// Can't do it if more than 10,000 sites.
	if ( wp_is_large_network() ) {
		wp_safe_redirect( network_admin_url( 'plugins.php?page=plugin-management&ng_mpm_notice=large_network' ) );
		exit;
	}

	set_time_limit( 120 );

	$plugin = urldecode( $_GET['mass_activate'] );

	$blogs = get_sites( array(
		'fields' => 'ids',
		'number' => 10000,
		//'public' => 1
	) );

	if ( $blogs ) {
		foreach ( $blogs as $blog_id ) {
			switch_to_blog( $blog_id );
			activate_plugin( sanitize_text_field( $plugin ) );
			restore_current_blog();
		}
	}

	wp_safe_redirect( add_query_arg( 'plugin', urlencode( $plugin ), network_admin_url( 'plugins.php?page=plugin-management&ng-mpm-notice=mass-activated' ) ) );
	exit;

}

add_action( 'admin_init', 'ng_mpm_process_activate_all' );

/**
 * Dectivate plugin on all sites
 *
 * @since 1.0
 * @return void
 */
function ng_mpm_process_deactivate_all() {

	if ( ! isset( $_GET['mass_deactivate'] ) || ! isset( $_GET['_wpnonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'ng_mpm_deactivate_all' ) ) {
		wp_die( __( 'Failed nonce security check.', 'ng-multisite-plugin-manager' ) );
	}

	// Can't do it if more than 10,000 sites.
	if ( wp_is_large_network() ) {
		wp_safe_redirect( network_admin_url( 'plugins.php?page=plugin-management&ng_mpm_notice=large_network' ) );
		exit;
	}

	set_time_limit( 120 );

	$plugin = urldecode( $_GET['mass_deactivate'] );

	$blogs = get_sites( array(
		'fields' => 'ids',
		'number' => 10000,
		//'public' => 1
	) );

	if ( $blogs ) {
		foreach ( $blogs as $blog_id ) {
			switch_to_blog( $blog_id );
			deactivate_plugins( sanitize_text_field( $plugin ), true );
			restore_current_blog();
		}
	}

	wp_safe_redirect( add_query_arg( 'plugin', urlencode( $plugin ), network_admin_url( 'plugins.php?page=plugin-management&ng-mpm-notice=mass-deactivated' ) ) );
	exit;

}

add_action( 'admin_init', 'ng_mpm_process_deactivate_all' );

/**
 * Auto activate plugins when a new site is created.
 *
 * @param int $blog_id ID of the new blog.
 *
 * @since 1.0
 * @return void
 */
function ng_mpm_auto_activate_on_new_blog( $blog_id ) {

	require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

	$auto_activate = (array) get_site_option( 'pm_auto_activate_list', array() );

	if ( count( $auto_activate ) && 'EMPTY' != $auto_activate[0] ) {
		switch_to_blog( $blog_id );
		activate_plugins( $auto_activate, '', false ); //silently activate any plugins
		restore_current_blog();
	}

}

add_action( 'wpmu_new_blog', 'ng_mpm_auto_activate_on_new_blog', 50 );