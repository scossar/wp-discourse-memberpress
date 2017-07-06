<?php

namespace WPDCMemberPress;

/**
 * Todo: disable plugin functions if Discourse is being used as the SSO provider for WordPress. That can be made to work later.
 *
 * Class DiscourseMemberPress
 * @package WPDCMemberPress
 */
class DiscourseMemberPress {
	use DiscourseMemberPressUtilities;

	protected $dcmp_groups = array(
		'dcmp_group_associations' => array(),
	);

	public function __construct() {
	}

	public function init() {
		add_action( 'init', array( $this, 'initialize_plugin' ) );
//		add_action( 'wishlistmember_add_user_levels', array(
//			$this,
//			'add_unconfirmed_member_to_discourse_groups',
//		), 10, 2 );
//		add_action( 'wishlistmember_confirm_user_levels', array(
//			$this,
//			'add_confirmed_member_to_discourse_group',
//		), 10, 2 );
//		add_action( 'wishlistmember_remove_user_levels', array( $this, 'remove_member_from_discourse_groups' ), 10, 2 );
//
//		add_filter( 'wishlistmember_login_redirect_override', array( $this, 'remove_login_redirect' ) );
	}

	public function initialize_plugin() {
		add_option( 'dcmp_groups', $this->dcmp_groups );
	}

	/**
	 * If the WishList login redirect is enabled, SSO will be broken. This will override the WishList login redirect
	 * for SSO requests.
	 *
	 * @return bool
	 */
	public function remove_login_redirect() {
		if ( ! empty( $_REQUEST['redirect_to'] ) && false !== strpos( $_REQUEST['redirect_to'], 'sso' ) ) {

			return true;
		}

		return false;
	}

	public function add_confirmed_member_to_discourse_group( $user_id, $levels ) {
		foreach ( $levels as $level_id ) {
			$this->add_member_to_discourse_group( $user_id, $level_id );
		}
	}

	public function add_unconfirmed_member_to_discourse_groups( $user_id, $levels ) {

		// If an admin is registering the user, don't require confirmation.
		$admin_registration = current_user_can( 'administrator' ) && is_admin();

		foreach ( $levels as $level_id ) {
			$level_data                 = wlmapi_get_level( $level_id );
			$require_email_confirmation = isset( $level_data['level'] ) &&
			                              isset( $level_data['level']['require_email_confirmation'] ) &&
			                              1 === intval( $level_data['level']['require_email_confirmation'] ) &&
			                              ! $admin_registration;

			if ( ! $require_email_confirmation ) {
				$this->add_member_to_discourse_group( $user_id, $level_id );
			}
		}
	}

	public function remove_member_from_discourse_groups( $user_id, $levels ) {
		foreach ( $levels as $level_id ) {
			$this->remove_member_from_discourse_group( $user_id, $level_id );
		}
	}

	protected function add_member_to_discourse_group( $user_id, $level_id ) {
		$dcmp_groups             = get_option( 'dcmp_groups' );
		$dcmp_group_associations = $dcmp_groups['dcmp_group_associations'];
		if ( array_key_exists( $level_id, $dcmp_group_associations ) ) {
			$level                      = $dcmp_group_associations[ $level_id ];
			$require_email_verification = isset( $level['require_activation'] ) && 1 === intval( $level['require_activation'] ) ? 1 : 0;
			$discourse_groups           = $level['dc_group_ids'];

			if ( $discourse_groups ) {
				$discourse_user_id = $this->lookup_or_create_discourse_user( $user_id, $require_email_verification );

				if ( $discourse_user_id && ! is_wp_error( $discourse_user_id ) ) {

					foreach ( $discourse_groups as $discourse_group ) {
						$this->add_user_to_group( $discourse_user_id, $discourse_group );
					}
				}
			}
		}
	}

	protected function add_user_to_group( $user_id, $discourse_group_id ) {
		$connection_options = get_option( 'discourse_connect' );
		$base_url           = $connection_options['url'];
		$api_key            = $connection_options['api-key'];
		$api_username       = $connection_options['publish-username'];
		if ( $base_url && $api_key && $api_username ) {
			$add_to_group_url = esc_url( $base_url . "/admin/groups/$discourse_group_id/members.json" );
			$response         = wp_remote_post( $add_to_group_url, array(
				'method' => 'PUT',
				'body'   => array(
					'user_ids'     => $user_id,
					'api_key'      => $api_key,
					'api_username' => $api_username,
				),
			) );

			return wp_remote_retrieve_response_code( $response );
		}

		return new \WP_Error( 'unable_to_add_user_to_discourse_group', "The Discourse settings aren't properly configured." );
	}

	protected function remove_member_from_discourse_group( $user_id, $level_id ) {

		$dcmp_groups             = get_option( 'dcmp_groups' );
		$dcmp_group_associations = $dcmp_groups['dcmp_group_associations'];
		if ( array_key_exists( $level_id, $dcmp_group_associations ) ) {
			$level            = $dcmp_group_associations[ $level_id ];
			$auto_remove      = isset( $level['auto_remove'] ) && 1 === intval( $level['auto_remove'] ) ? 1 : 0;
			$discourse_groups = $level['dc_group_ids'];

			if ( $discourse_groups ) {
				$wp_user = get_user_by( 'id', $user_id );
				if ( $auto_remove ) {
					$discourse_user_id = $this->lookup_discourse_user( $wp_user );

					if ( $discourse_user_id && ! is_wp_error( $discourse_user_id ) ) {

						foreach ( $discourse_groups as $discourse_group ) {

							// This method returns the response code, something could be done with it.
							$this->remove_user_from_group( $discourse_user_id, $discourse_group );
						}
					}
				}
			}
		}

		// Return something more meaningful here.
		return 0;
	}

	protected function remove_user_from_group( $discourse_user_id, $discourse_group_id ) {
		$base_url     = $this->get_connection_option( 'url' );
		$api_key      = $this->get_connection_option( 'api-key' );
		$api_username = $this->get_connection_option( 'publish-username' );

		if ( $base_url && $api_key && $api_username ) {
			$remove_from_group_url = esc_url( $base_url . "/admin/groups/$discourse_group_id/members.json" );
			$response              = wp_remote_post( $remove_from_group_url, array(
				'method' => 'DELETE',
				'body'   => array(
					'user_id'      => $discourse_user_id,
					'api_key'      => $api_key,
					'api_username' => $api_username,
				),
			) );

			return wp_remote_retrieve_response_code( $response );
		}

		return new \WP_Error( 'discourse_configuration_error', 'The WP Discourse plugin options have not been properly configured.' );
	}
}
