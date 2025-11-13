<?php
/**
 * Custom post type implementation for Codex auctions.
 *
 * @package CodexAjaxAuctions
 */

namespace Codfaa\Auctions;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the auction post type and its admin UI.
 */
class Auction_Post_Type {

	/**
	 * Auction post type slug.
	 */
	public const POST_TYPE = 'codfaa_auction';

	/**
	 * Meta keys used by the auction post type.
	 */
	public const META_PRODUCT_ID          = '_codfaa_product_id';
	public const META_REGISTRATION_ID     = '_codfaa_registration_product_id';
	public const META_BID_PRODUCT_ID      = '_codfaa_bid_product_id';
	public const META_REQUIRED_PARTICIPANTS = '_codfaa_required_participants';
	public const META_TIMER_SECONDS       = '_codfaa_timer_seconds';

	/**
	 * Boot hooks.
	 */
	public function boot() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_post_statuses' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta_boxes' ), 10, 2 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'register_admin_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_admin_columns' ), 10, 2 );
	}

	/**
	 * Register the Codex auction custom post type.
	 */
	public function register_post_type() {
		$labels = array(
			'name'               => __( 'Auctions', 'codex-ajax-auctions' ),
			'singular_name'      => __( 'Auction', 'codex-ajax-auctions' ),
			'add_new_item'       => __( 'Add New Auction', 'codex-ajax-auctions' ),
			'edit_item'          => __( 'Edit Auction', 'codex-ajax-auctions' ),
			'new_item'           => __( 'New Auction', 'codex-ajax-auctions' ),
			'view_item'          => __( 'View Auction', 'codex-ajax-auctions' ),
			'search_items'       => __( 'Search Auctions', 'codex-ajax-auctions' ),
			'not_found'          => __( 'No auctions found.', 'codex-ajax-auctions' ),
			'not_found_in_trash' => __( 'No auctions found in Trash.', 'codex-ajax-auctions' ),
			'all_items'          => __( 'All Auctions', 'codex-ajax-auctions' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'menu_icon'          => 'dashicons-hammer',
			'supports'           => array( 'title', 'excerpt', 'thumbnail' ),
			'capability_type'    => 'post',
			'has_archive'        => false,
			'rewrite'            => false,
			'show_in_rest'       => false,
		);

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Register additional auction post statuses.
	 */
	public function register_post_statuses() {
		register_post_status( 'codfaa-upcoming', array(
			'label'                     => _x( 'Upcoming', 'post status', 'codex-ajax-auctions' ),
			'public'                    => false,
			'internal'                  => true,
			'label_count'               => _n_noop( 'Upcoming <span class="count">(%s)</span>', 'Upcoming <span class="count">(%s)</span>', 'codex-ajax-auctions' ),
			'post_type'                 => array( self::POST_TYPE ),
		) );

		register_post_status( 'codfaa-live', array(
			'label'                     => _x( 'Live', 'post status', 'codex-ajax-auctions' ),
			'public'                    => false,
			'internal'                  => true,
			'label_count'               => _n_noop( 'Live <span class="count">(%s)</span>', 'Live <span class="count">(%s)</span>', 'codex-ajax-auctions' ),
			'post_type'                 => array( self::POST_TYPE ),
		) );

		register_post_status( 'codfaa-ended', array(
			'label'                     => _x( 'Ended', 'post status', 'codex-ajax-auctions' ),
			'public'                    => false,
			'internal'                  => true,
			'label_count'               => _n_noop( 'Ended <span class="count">(%s)</span>', 'Ended <span class="count">(%s)</span>', 'codex-ajax-auctions' ),
			'post_type'                 => array( self::POST_TYPE ),
		) );
	}

	/**
	 * Register admin meta boxes for auction configuration.
	 */
	public function register_meta_boxes() {
		add_meta_box(
			'codfaa-auction-config',
			__( 'Auction Configuration', 'codex-ajax-auctions' ),
			array( $this, 'render_config_metabox' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render the configuration meta box.
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public function render_config_metabox( $post ) {
		wp_nonce_field( 'codfaa_save_auction', 'codfaa_auction_nonce' );

		$product_id      = (int) get_post_meta( $post->ID, self::META_PRODUCT_ID, true );
		$registration_id = (int) get_post_meta( $post->ID, self::META_REGISTRATION_ID, true );
		$bid_product_id  = (int) get_post_meta( $post->ID, self::META_BID_PRODUCT_ID, true );
		$required        = (int) get_post_meta( $post->ID, self::META_REQUIRED_PARTICIPANTS, true );
		$timer           = (int) get_post_meta( $post->ID, self::META_TIMER_SECONDS, true );

		$products = $this->get_product_options();

		include CODFAA_PLUGIN_DIR . 'admin/views/metabox-auction-config.php';
	}

	/**
	 * Persist meta box values when the post is saved.
	 *
	 * @param int      $post_id Auction post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function save_meta_boxes( $post_id, $post ) {
		if ( ! isset( $_POST['codfaa_auction_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['codfaa_auction_nonce'] ) ), 'codfaa_save_auction' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$product_id      = isset( $_POST['codfaa_product_id'] ) ? (int) $_POST['codfaa_product_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$registration_id = isset( $_POST['codfaa_registration_product_id'] ) ? (int) $_POST['codfaa_registration_product_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$bid_product_id  = isset( $_POST['codfaa_bid_product_id'] ) ? (int) $_POST['codfaa_bid_product_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$required        = isset( $_POST['codfaa_required_participants'] ) ? max( 0, (int) $_POST['codfaa_required_participants'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$timer           = isset( $_POST['codfaa_timer_seconds'] ) ? max( 0, (int) $_POST['codfaa_timer_seconds'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		update_post_meta( $post_id, self::META_PRODUCT_ID, $product_id );
		update_post_meta( $post_id, self::META_REGISTRATION_ID, $registration_id );
		update_post_meta( $post_id, self::META_BID_PRODUCT_ID, $bid_product_id );
		update_post_meta( $post_id, self::META_REQUIRED_PARTICIPANTS, $required );
		update_post_meta( $post_id, self::META_TIMER_SECONDS, $timer );
	}

	/**
	 * Add custom columns to the auction list table.
	 *
	 * @param array<string,string> $columns Default columns.
	 * @return array<string,string>
	 */
	public function register_admin_columns( $columns ) {
		$columns['codfaa_product']      = __( 'Product', 'codex-ajax-auctions' );
		$columns['codfaa_registration'] = __( 'Registration Fee', 'codex-ajax-auctions' );
		$columns['codfaa_bid_product']  = __( 'Bid Fee Product', 'codex-ajax-auctions' );
		return $columns;
	}

	/**
	 * Render custom column values for auction posts.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Current post ID.
	 */
	public function render_admin_columns( $column, $post_id ) {
		switch ( $column ) {
			case 'codfaa_product':
				$product_id = (int) get_post_meta( $post_id, self::META_PRODUCT_ID, true );
				if ( $product_id ) {
					$product = get_post( $product_id );
					if ( $product ) {
						echo esc_html( $product->post_title );
						break;
					}
				}
				echo '&mdash;';
				break;

			case 'codfaa_registration':
				$registration_id = (int) get_post_meta( $post_id, self::META_REGISTRATION_ID, true );
				if ( $registration_id ) {
					$registration = get_post( $registration_id );
					if ( $registration ) {
						echo esc_html( $registration->post_title );
						break;
					}
				}
				echo '&mdash;';
				break;

			case 'codfaa_bid_product':
				$bid_product_id = (int) get_post_meta( $post_id, self::META_BID_PRODUCT_ID, true );
				if ( $bid_product_id ) {
					$bid_product = get_post( $bid_product_id );
					if ( $bid_product ) {
						echo esc_html( $bid_product->post_title );
						break;
					}
				}
				echo '&mdash;';
				break;
		}
	}

	/**
	 * Retrieve product options for the admin dropdown.
	 *
	 * @return array<int,string>
	 */
	private function get_product_options() {
		$products = get_posts(
			array(
				'post_type'      => 'product',
				'numberposts'    => -1,
				'post_status'    => array( 'publish', 'draft', 'pending' ),
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$options = array( 0 => __( 'Select a product', 'codex-ajax-auctions' ) );

		foreach ( $products as $product ) {
			$options[ $product->ID ] = $product->post_title;
		}

		return $options;
	}
}
