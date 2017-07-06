<?php
/**
 * Uninstall the plugin.
 *
 * @package WPDiscourse
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Todo: I'm removing the options clean-up calls for now so that users can easily update the plugin from a zip file.
 * When I find a better way of approaching this I'll add back the following code:
 * delete_option( 'dcwl_groups' );
 * delete_site_option( 'dcwl_groups' );
 * delete_transient( 'wpdc_groups_data' );
 */
