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

	protected $options;

	protected $dcmp_groups = array(
		'dcmp_product_ids'        => array(),
		'dcmp_group_associations' => array(),
		'dcmp_discourse_groups'   => array(),
	);

	public function __construct() {
	}

	public function init() {
		add_action( 'init', array( $this, 'initialize_plugin' ) );
		add_action( 'mepr-txn-store', array( $this, 'sync_discourse_groups' ) );
		add_action( 'save_post', array( $this, 'add_memberpress_product' ) );
		add_action( 'publish_to_trash', array( $this, 'remove_memberpress_product' ) );
	}

	public function remove_memberpress_product( $post ) {
		if ( 'memberpressproduct' === $post->post_type ) {
			$post_id = $post->ID;
			$dcmp_options = get_option( 'dcmp_options' );
			$product_ids  = ! empty( $dcmp_options['dcmp_product_ids'] ) ? $dcmp_options['dcmp_product_ids'] : array();
			$key = array_search( $post_id, $product_ids, true );
			if ( $key >= 0 ) {
				unset( $product_ids[ $key ] );
				$dcmp_options['dcmp_product_ids'] = $product_ids;
				update_option( 'dcmp_options', $dcmp_options );
			}
		}
	}

	public function add_memberpress_product( $post_id ) {
		$post = get_post( $post_id );
		if ( ! is_wp_error( $post ) && 'memberpressproduct' === $post->post_type && 'publish' === $post->post_status ) {

			$dcmp_options = get_option( 'dcmp_options' );
			$product_ids  = ! empty( $dcmp_options['dcmp_product_ids'] ) ? $dcmp_options['dcmp_product_ids'] : array();
			if ( ! in_array( $post_id, $product_ids, true ) ) {
				$product_ids[]                    = $post_id;
				$dcmp_options['dcmp_product_ids'] = $product_ids;
				update_option( 'dcmp_options', $dcmp_options );
			}
		}
	}

	public function sync_discourse_groups( $transaction ) {
		write_log( 'transaction', $transaction );
	}

	public function initialize_plugin() {
		add_filter( 'wpdc_utilities_options_array', array( $this, 'add_options' ) );
		add_option( 'dcmp_groups', $this->dcmp_groups );

//		$this->options = $this->get_options();

//		$groups = $this->get_discourse_groups();
//		$levels = $this->get_memberpress_levels();
	}

	public function add_options( $wpdc_options ) {
		static $merged_options = [];

		if ( empty( $merged_options ) ) {
			$added_options = get_option( 'dcmp_groups' );
			if ( is_array( $added_options ) ) {
				$merged_options = array_merge( $wpdc_options, $added_options );
			} else {
				$merged_options = $wpdc_options;
			}
		}

		return $merged_options;
	}

}
