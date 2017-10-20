<?php
/**
 * Plugin Management for Individual Sites
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
function ng_mpm_add_site_plugins_menu() {
	add_submenu_page( null, __( 'Edit Site Plugins', 'ng-multisite-plugin-manager' ), __( 'Edit Site Plugins', 'ng-multisite-plugin-manager' ), 'manage_network_options', 'site-plugins', 'ng_mpm_render_site_plugins_page' );
}

add_action( 'network_admin_menu', 'ng_mpm_add_site_plugins_menu' );

/**
 * Add "Plugins" tab to "Edit Site" menu.
 *
 * @param array $links
 *
 * @since 1.0
 * @return array
 */
function ng_mpm_edit_site_link( $links ) {
	$links['site-plugins'] = array(
		'label' => __( 'Plugins', 'ng-multisite-plugin-manager' ),
		'url'   => 'index.php?page=site-plugins',
		'cap'   => 'manage_sites'
	);

	return $links;
}

add_filter( 'network_edit_site_nav_links', 'ng_mpm_edit_site_link' );

/**
 * Render "Edit Site Plugins" page.
 *
 * @since 1.0
 * @return void
 */
function ng_mpm_render_site_plugins_page() {

	if ( ! current_user_can( 'manage_sites' ) ) {
		wp_die( __( 'Sorry, you are not allowed to edit this site.' ) );
	}

	$blog_id = isset( $_REQUEST['id'] ) ? intval( $_REQUEST['id'] ) : 0;

	if ( ! $blog_id ) {
		wp_die( __( 'Invalid site ID.' ) );
	}

	$site_details = get_site( $blog_id );
	if ( ! $site_details ) {
		wp_die( __( 'The requested site does not exist.' ) );
	}

	if ( ! can_edit_network( $site_details->site_id ) ) {
		wp_die( __( 'Sorry, you are not allowed to access this page.' ), 403 );
	}

	$plugins          = get_plugins();
	$override_plugins = (array) get_blog_option( $blog_id, 'pm_plugin_override_list' );
	?>
	<div class="wrap">
		<h1><?php printf( __( 'Edit Site: %s' ), esc_html( $site_details->blogname ) ); ?></h1>
		<p class="edit-site-actions">
			<a href="<?php echo esc_url( get_home_url( $blog_id, '/' ) ); ?>"><?php _e( 'Visit' ); ?></a> |
			<a href="<?php echo esc_url( get_admin_url( $blog_id ) ); ?>"><?php _e( 'Dashboard' ); ?></a>
		</p>

		<?php
		network_edit_site_nav( array(
			'blog_id'  => $blog_id,
			'selected' => 'site-plugins'
		) );

		if ( isset( $_GET['ng-mpm-notice'] ) && 'site-updated' == $_GET['ng-mpm-notice'] ) {
			?>
			<div id="message" class="updated notice is-dismissible">
				<p><?php _e( 'Site settings updated.', 'ng-multisite-plugin-manager' ); ?></p>
			</div>
			<?php
		}
		?>

		<p><?php _e( 'Checked plugins here will be accessible to this site, overriding the sitewide Plugin Management settings. Uncheck to return to sitewide settings.', 'ng-multisite-plugin-manager' ); ?></p>

		<form action="" method="POST">
			<table class="widefat">
				<thead>
				<tr>
					<th title="<?php esc_attr_e( 'Blog users may activate/deactivate', 'ng-multisite-plugin-manager' ); ?>"><?php _e( 'User Control', 'ng-multisite-plugin-manager' ); ?></th>
					<th><?php _e( 'Name', 'ng-multisite-plugin-manager' ); ?></th>
					<th><?php _e( 'Version', 'ng-multisite-plugin-manager' ); ?></th>
					<th><?php _e( 'Author', 'ng-multisite-plugin-manager' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<?php
				foreach ( $plugins as $file => $plugin ) {

					// Skip network only and network activated plugins.
					if ( is_network_only_plugin( $file ) || is_plugin_active_for_network( $file ) ) {
						continue;
					}

					?>
					<tr>
						<td>
							<label><input type="checkbox" name="plugins[<?php echo esc_attr( $file ); ?>]" value="1" <?php checked( in_array( $file, $override_plugins ) ); ?>></label>
						</td>
						<td><?php echo esc_html( $plugin['Name'] ); ?></td>
						<td><?php echo esc_html( $plugin['Version'] ); ?></td>
						<td><?php echo esc_html( $plugin['Author'] ); ?></td>
					</tr>
					<?php

				}
				?>
				</tbody>
			</table>

			<?php submit_button( __( 'Update Options', 'ng-multisite-plugin-manager' ) ); ?>
			<input type="hidden" name="ng_mpm_blog_id" value="<?php echo esc_attr( $blog_id ); ?>">
			<?php wp_nonce_field( 'ng_mpm_save_single_site_plugin_settings', 'ng_mpm_save_single_site_plugin_settings_nonce' ); ?>
		</form>
	</div>
	<?php

}

/**
 * Save single site plugin settings
 *
 * @since 1.0
 * @return void
 */
function ng_mpm_save_single_site_settings() {

	if ( ! isset( $_POST['ng_mpm_save_single_site_plugin_settings_nonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( $_POST['ng_mpm_save_single_site_plugin_settings_nonce'], 'ng_mpm_save_single_site_plugin_settings' ) ) {
		wp_die( __( 'Failed nonce security check.', 'ng-multisite-plugin-manager' ) );
	}

	if ( ! current_user_can( 'manage_sites' ) ) {
		wp_die( __( 'You do not have permission to perform this action.', 'ng-multisite-plugin-manager' ) );
	}

	$blog_id = ! empty( $_POST['ng_mpm_blog_id'] ) ? absint( $_POST['ng_mpm_blog_id'] ) : 0;

	if ( empty( $blog_id ) ) {
		wp_die( __( 'Missing site ID.', 'ng-multisite-plugin-manager' ) );
	}

	$plugins          = ! empty( $_POST['plugins'] ) ? $_POST['plugins'] : false;
	$override_plugins = array();

	if ( is_array( $plugins ) ) {
		foreach ( $plugins as $plugin => $value ) {
			$override_plugins[] = sanitize_text_field( $plugin );
		}

		update_blog_option( $blog_id, 'pm_plugin_override_list', $override_plugins );
	} else {
		delete_blog_option( $blog_id, 'pm_plugin_override_list' );
	}

	$redirect_url = add_query_arg( array(
		'page'          => 'site-plugins',
		'id'            => urlencode( $blog_id ),
		'ng-mpm-notice' => 'site-updated'
	), network_admin_url( 'index.php' ) );

	wp_safe_redirect( $redirect_url );
	exit;

}

add_action( 'admin_init', 'ng_mpm_save_single_site_settings' );