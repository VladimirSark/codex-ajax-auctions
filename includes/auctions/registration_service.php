<?php
/**
 * Handles auction registration flow and participant logging.
 *
 * @package CodexAjaxAuctions
 */

namespace Codfaa\Auctions;

defined( 'ABSPATH' ) || exit;

use WC_Order;
use WC_Order_Item_Product;
use WC_Product;

/**
 * Registration service wires AJAX endpoints and WooCommerce hooks.
 */
class Registration_Service {

	/**
	 * Nonce action identifier.
	 */
	public const NONCE_ACTION = 'codfaa_register';

	/**
	 * Session key for storing return URL data.
	 */
	private const SESSION_RETURN = 'codfaa_registration_return_url';

	/**
	 * Boot hooks.
	 */
	public function boot() {
		add_action( 'init', array( $this, 'register_assets' ) );
		add_action( 'init', array( $this, 'maybe_upgrade_participant_table' ) );
		add_action( 'wp_ajax_codfaa_register', array( $this, 'handle_registration' ) );
		add_action( 'wp_ajax_nopriv_codfaa_register', array( $this, 'handle_registration_guest' ) );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'mark_registration_line_item' ), 10, 4 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'attach_registration_metadata' ), 10, 2 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'maybe_record_participant' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'maybe_record_participant' ) );
		add_filter( 'woocommerce_get_return_url', array( $this, 'filter_return_url' ), 10, 2 );
		add_action( 'woocommerce_thankyou', array( $this, 'maybe_render_return_notice' ), 20 );
	}

	/**
	 * Register and localize frontend assets used for registration.
	 */
	public function register_assets() {
		wp_register_script(
			'codfaa-auction-registration',
			CODFAA_PLUGIN_URL . 'public/js/auction-registration.js',
			array( 'jquery' ),
			\Codex_Ajax_Auctions::VERSION,
			true
		);

		wp_localize_script(
			'codfaa-auction-registration',
			'CodfaaAuctionRegistration',
			array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( self::NONCE_ACTION ),
				'genericError'    => __( 'Something went wrong. Please try again.', 'codex-ajax-auctions' ),
				'currentUserId'   => get_current_user_id(),
				'statusInterval'  => (int) apply_filters( 'codfaa_auction_status_interval', 5000 ),
				'timerLabel'      => __( 'Time remaining', 'codex-ajax-auctions' ),
				'noBidsLabel'     => __( 'No bids yet.', 'codex-ajax-auctions' ),
				'bidSingular'     => __( '%s bid', 'codex-ajax-auctions' ),
				'bidPlural'       => __( '%s bids', 'codex-ajax-auctions' ),
				'winnerUnknown'   => __( 'Winner: —', 'codex-ajax-auctions' ),
				'winnerLabel'     => __( 'Winner: %s', 'codex-ajax-auctions' ),
				'lockedCopy'        => __( 'Complete Step 1 & wait for the pre-live countdown.', 'codex-ajax-auctions' ),
				'readyLockCopy'    => __( 'Well done, lobby is full. We are giving everyone time to get ready.', 'codex-ajax-auctions' ),
				'registeredLockCopy' => __( 'You are registered. Wait for the pre-live countdown.', 'codex-ajax-auctions' ),
				'countdownCopy'     => __( 'Auction goes live in %s', 'codex-ajax-auctions' ),
				'consentRequired' => __( 'Please accept the Terms & Conditions before registering.', 'codex-ajax-auctions' ),
			)
		);
	}

	/**
	 * Handle AJAX requests for authenticated users joining an auction.
	 */
	public function handle_registration() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! is_user_logged_in() ) {
			$this->send_error_response(
				__( 'Please log in to join the auction.', 'codex-ajax-auctions' ),
				wp_login_url( wp_get_referer() ? wp_get_referer() : home_url() ),
				401
			);
		}

		$auction_id = isset( $_POST['auction_id'] ) ? absint( $_POST['auction_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$source_url = isset( $_POST['source_url'] ) ? esc_url_raw( wp_unslash( $_POST['source_url'] ) ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! $auction_id || Auction_Post_Type::POST_TYPE !== get_post_type( $auction_id ) ) {
			$this->send_error_response( __( 'Invalid auction selected.', 'codex-ajax-auctions' ) );
		}

		$user_id = get_current_user_id();

		if ( $this->user_is_registered( $auction_id, $user_id ) ) {
			$this->send_error_response( __( 'You are already registered for this auction.', 'codex-ajax-auctions' ) );
		}

		if ( $this->user_has_pending_registration( $auction_id, $user_id ) ) {
			$this->send_error_response( __( 'Your registration is awaiting admin confirmation. Please check back soon.', 'codex-ajax-auctions' ) );
		}

		$registration_product_id = (int) get_post_meta( $auction_id, Auction_Post_Type::META_REGISTRATION_ID, true );

		if ( ! $registration_product_id ) {
			$this->send_error_response( __( 'Registration fee is not configured for this auction.', 'codex-ajax-auctions' ) );
		}

		$product = wc_get_product( $registration_product_id );

		if ( ! $product instanceof WC_Product ) {
			$this->send_error_response( __( 'Registration product could not be found.', 'codex-ajax-auctions' ) );
		}

		$this->ensure_cart_session();
		$this->store_return_destination( $auction_id, $source_url );

		$cart_item_data = array(
			'codfaa_auction_registration' => 1,
			'codfaa_auction_id'          => $auction_id,
		);

		$cart_item_key = WC()->cart ? WC()->cart->add_to_cart( $registration_product_id, 1, 0, array(), $cart_item_data ) : false;

		if ( ! $cart_item_key ) {
			$this->send_error_response( __( 'Unable to add registration fee to cart. Please try again.', 'codex-ajax-auctions' ) );
		}

		wp_send_json_success(
			array(
				'cartKey'  => $cart_item_key,
				'redirect' => wc_get_checkout_url(),
				'message'  => __( 'Registration fee added to cart. Complete checkout to confirm your spot.', 'codex-ajax-auctions' ),
			)
		);
	}

	/**
	 * Handle registration attempts for guests.
	 */
	public function handle_registration_guest() {
		wp_send_json_error(
			array(
				'message'  => __( 'Please log in to join the auction.', 'codex-ajax-auctions' ),
				'redirect' => wp_login_url( wp_get_referer() ? wp_get_referer() : home_url() ),
			),
			401
		);
	}

	/**
	 * Tag registration fee products on order line items.
	 *
	 * @param WC_Order_Item_Product $item   Order item being created.
	 * @param string                $cart_item_key Cart item key.
	 * @param array<string,mixed>   $values Cart item data.
	 * @param WC_Order              $order  Order object.
	 */
	public function mark_registration_line_item( $item, $cart_item_key, $values, $order ) {
		unset( $cart_item_key, $order );

		if ( empty( $values['codfaa_auction_registration'] ) || empty( $values['codfaa_auction_id'] ) ) {
			return;
		}

		$item->add_meta_data( '_codfaa_auction_registration', 1, true );
		$item->add_meta_data( '_codfaa_auction_id', (int) $values['codfaa_auction_id'], true );
	}

	/**
	 * Attach registration metadata to the order, including return URL.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $data  Posted checkout data.
	 */
	public function attach_registration_metadata( $order, $data ) {
		unset( $data );

		$return_data = $this->get_return_destination();

		if ( empty( $return_data['url'] ) || empty( $return_data['auction_id'] ) ) {
			return;
		}

		$order->update_meta_data( '_codfaa_return_url', esc_url_raw( $return_data['url'] ) );
		$order->update_meta_data( '_codfaa_return_auction', (int) $return_data['auction_id'] );
		if ( ! empty( $return_data['page_id'] ) ) {
			$order->update_meta_data( '_codfaa_return_page', (int) $return_data['page_id'] );
		}
		$order->save();

		if ( $order->get_user_id() ) {
			$this->set_pending_registration_flag( $return_data['auction_id'], $order->get_user_id(), $order->get_id() );
		}

		$this->clear_return_destination();
	}

	/**
	 * Record participants once their registration order is paid.
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function maybe_record_participant( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( 'yes' === $order->get_meta( '_codfaa_registration_logged', true ) ) {
			return;
		}

		$items = $order->get_items();

		if ( empty( $items ) ) {
			return;
		}

		$user_id = $order->get_user_id();

		if ( ! $user_id ) {
			return;
		}

		$registered = false;

		$affected_auctions = array();

		foreach ( $items as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$auction_id = (int) $item->get_meta( '_codfaa_auction_id', true );
			$flag       = (int) $item->get_meta( '_codfaa_auction_registration', true );

			if ( ! $auction_id || ! $flag ) {
				continue;
			}

			$this->upsert_participant( $auction_id, $user_id, $order_id );
			$affected_auctions[ $auction_id ] = true;
			$registered = true;
		}

		if ( $registered ) {
			foreach ( array_keys( $affected_auctions ) as $affected_auction_id ) {
				$this->clear_pending_registration_flag( $affected_auction_id, $user_id );
				do_action( 'codfaa_participant_registered', $affected_auction_id );
			}
			$order->update_meta_data( '_codfaa_registration_logged', 'yes' );
			$order->save();
		}
	}

	/**
	 * Modify the WooCommerce thank-you redirect URL when relevant.
	 *
	 * @param string   $return_url Default return URL.
	 * @param WC_Order $order      Order object.
	 * @return string
	 */
	public function filter_return_url( $return_url, $order ) {
		if ( ! $order instanceof WC_Order ) {
			return $return_url;
		}

		$meta_url = $order->get_meta( '_codfaa_return_url', true );
		$page_id  = (int) $order->get_meta( '_codfaa_return_page', true );

		if ( $meta_url ) {
			return esc_url_raw( $meta_url );
		}

		if ( $page_id ) {
			return get_permalink( $page_id );
		}

		return $return_url;
	}

	/**
	 * Display a fallback notice on the thank-you page.
	 *
	 * @param int $order_id Order ID.
	 */
	public function maybe_render_return_notice( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$auction_id = (int) $order->get_meta( '_codfaa_return_auction', true );
		$url        = $order->get_meta( '_codfaa_return_url', true );
		$page_id    = (int) $order->get_meta( '_codfaa_return_page', true );

		if ( ! $auction_id ) {
			foreach ( $order->get_items() as $item ) {
				if ( ! $item instanceof WC_Order_Item_Product ) {
					continue;
				}

				$found = (int) $item->get_meta( '_codfaa_auction_id', true );

				if ( $found ) {
					$auction_id = $found;
					break;
				}
			}
		}

		if ( ! $auction_id ) {
			return;
		}

		if ( ! $url && $page_id ) {
			$url = get_permalink( $page_id );
		}

		if ( ! $url ) {
			$url = get_permalink( $auction_id );
		}

		if ( ! $url ) {
			return;
		}

		printf(
			'<p class="codfaa-return-link"><a class="button" href="%1$s">%2$s</a></p>',
			esc_url( $url ),
			esc_html__( 'Return to the auction page', 'codex-ajax-auctions' )
		);
	}

	/**
	 * Add or update participant record.
	 *
	 * @param int $auction_id Auction ID.
	 * @param int $user_id    User ID.
	 * @param int $order_id   Order ID.
	 */
	private function upsert_participant( $auction_id, $user_id, $order_id ) {
		global $wpdb;

		$wpdb->replace(
			$wpdb->prefix . 'codfaa_auction_participants',
			array(
				'auction_id'    => $auction_id,
				'user_id'       => $user_id,
				'order_id'      => $order_id,
				'registered_at' => current_time( 'mysql' ),
				'total_reserved' => 0,
				'status'        => 'active',
				'removed_by'    => 0,
				'removed_at'    => null,
				'removed_reason'=> '',
			),
			array(
				'%d',
				'%d',
				'%d',
				'%s',
				'%d',
				'%s',
				'%d',
				'%s',
				'%s',
			)
		);
	}

	public function maybe_upgrade_participant_table() {
		global $wpdb;

		$table = $wpdb->prefix . 'codfaa_auction_participants';

		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) !== $table ) {
			return;
		}

		$this->maybe_add_column( $table, 'status', "ALTER TABLE {$table} ADD COLUMN status varchar(20) NOT NULL DEFAULT 'active'" );
		$this->maybe_add_column( $table, 'removed_by', "ALTER TABLE {$table} ADD COLUMN removed_by bigint(20) unsigned DEFAULT 0" );
		$this->maybe_add_column( $table, 'removed_at', "ALTER TABLE {$table} ADD COLUMN removed_at datetime NULL" );
		$this->maybe_add_column( $table, 'removed_reason', "ALTER TABLE {$table} ADD COLUMN removed_reason text NULL" );
	}

	private function maybe_add_column( $table, $column, $ddl ) {
		global $wpdb;

		$exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", $column ) );

		if ( empty( $exists ) ) {
			$wpdb->query( $ddl );
		}
	}

	/**
	 * Check if the user has a pending registration order awaiting confirmation.
	 *
	 * @param int $auction_id Auction ID.
	 * @param int $user_id    User ID.
	 * @return bool
	 */
private function user_has_pending_registration( $auction_id, $user_id ) {
		if ( ! $auction_id || ! $user_id ) {
			return false;
		}

		$meta_key      = $this->get_pending_meta_key( $auction_id );
		$pending_order = (int) get_user_meta( $user_id, $meta_key, true );

		if ( $pending_order ) {
			$order = wc_get_order( $pending_order );

			if ( $order instanceof WC_Order && in_array( $order->get_status(), array( 'pending', 'on-hold' ), true ) ) {
				return true;
			}

			delete_user_meta( $user_id, $meta_key );
		}

		$orders = array();

		if ( function_exists( 'wc_get_orders' ) ) {
			$orders = wc_get_orders(
				array(
					'customer_id' => $user_id,
					'limit'       => 1,
					'return'      => 'ids',
					'status'      => array( 'pending', 'on-hold' ),
					'type'        => 'shop_order',
					'meta_key'    => '_codfaa_return_auction',
					'meta_value'  => $auction_id,
				)
			);
		}

		if ( ! empty( $orders ) ) {
			return true;
		}

		global $wpdb;

		$order_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT oi.order_id
				FROM {$wpdb->prefix}woocommerce_order_items AS oi
				INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS oim
					ON oi.order_item_id = oim.order_item_id
				INNER JOIN {$wpdb->prefix}posts AS orders
					ON oi.order_id = orders.ID
				INNER JOIN {$wpdb->prefix}postmeta AS customer
					ON orders.ID = customer.post_id AND customer.meta_key = '_customer_user'
				WHERE oim.meta_key = '_codfaa_auction_id'
					AND oim.meta_value = %d
					AND customer.meta_value = %d
					AND orders.post_status IN ( 'wc-pending', 'wc-on-hold', 'pending', 'on-hold' )
				LIMIT 1",
				$auction_id,
				$user_id
			)
		);

		return ! empty( $order_id );
	}

	private function set_pending_registration_flag( $auction_id, $user_id, $order_id ) {
		if ( ! $auction_id || ! $user_id ) {
			return;
		}

		update_user_meta( $user_id, $this->get_pending_meta_key( $auction_id ), (int) $order_id );
	}

	private function clear_pending_registration_flag( $auction_id, $user_id ) {
		if ( ! $auction_id || ! $user_id ) {
			return;
		}

		delete_user_meta( $user_id, $this->get_pending_meta_key( $auction_id ) );
	}

	private function get_pending_meta_key( $auction_id ) {
		return '_codfaa_pending_registration_' . absint( $auction_id );
	}

	/**
	 * Determine if the logged-in user already confirmed registration.
	 *
	 * @param int $auction_id Auction ID.
	 * @param int $user_id    User ID.
	 * @return bool
	 */
	private function user_is_registered( $auction_id, $user_id ) {
		if ( ! $auction_id || ! $user_id ) {
			return false;
		}

		global $wpdb;

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}codfaa_auction_participants WHERE auction_id = %d AND user_id = %d AND status = 'active' LIMIT 1",
				$auction_id,
				$user_id
			)
		);

		return ! empty( $exists );
	}

	/**
	 * Ensure WooCommerce cart and session exist for AJAX requests.
	 */
	private function ensure_cart_session() {
		if ( function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
			return;
		}

		if ( null === WC()->cart ) {
			WC()->cart = new \WC_Cart();
		}
	}

	/**
	 * Persist the desired return URL in the WooCommerce session.
	 *
	 * @param int    $auction_id Auction ID.
	 * @param string $url        Source URL.
	 */
	private function store_return_destination( $auction_id, $url ) {
		if ( ! $url || ! wp_http_validate_url( $url ) ) {
			return;
		}

		if ( ! WC()->session ) {
			return;
		}

		$page_id = url_to_postid( $url );

		WC()->session->set(
			self::SESSION_RETURN,
			array(
				'auction_id' => $auction_id,
				'url'        => esc_url_raw( $url ),
				'page_id'    => $page_id,
			)
		);
	}

	/**
	 * Retrieve stored return destination.
	 *
	 * @return array{auction_id:int,url:string}
	 */
	private function get_return_destination() {
		if ( ! WC()->session ) {
			return array();
		}

		$data = WC()->session->get( self::SESSION_RETURN );

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Clear stored return destination from the session.
	 */
	private function clear_return_destination() {
		if ( WC()->session ) {
			WC()->session->set( self::SESSION_RETURN, null );
		}
	}

	/**
	 * Send JSON error response and exit.
	 *
	 * @param string $message  Error message.
	 * @param string $redirect Optional redirect URL.
	 * @param int    $status   HTTP status code.
	 */
	private function send_error_response( $message, $redirect = '', $status = 400 ) {
		$data = array( 'message' => $message );

		if ( $redirect ) {
			$data['redirect'] = $redirect;
		}

		wp_send_json_error( $data, $status );
	}
}
