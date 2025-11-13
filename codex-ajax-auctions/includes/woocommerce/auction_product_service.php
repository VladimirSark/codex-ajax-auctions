<?php
/**
 * Register the Codex auction product type and associated admin fields.
 *
 * @package CodexAjaxAuctions
 */

namespace Codfaa\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Handles WooCommerce hooks for the auction product type configuration.
 */
class Auction_Product_Service {

	/**
	 * Internal product type identifier.
	 */
	const PRODUCT_TYPE = 'codfaa_auction';

	/**
	 * Map of auction meta fields.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private $fields = array();

	/**
	 * Register hooks.
	 */
	public function boot() {
		$this->fields = $this->get_field_definitions();
		add_action( 'init', array( $this, 'register_product_type_term' ) );

		add_filter( 'product_type_selector', array( $this, 'register_product_type' ) );
		add_filter( 'woocommerce_product_class', array( $this, 'map_product_class' ), 10, 2 );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'set_product_type_on_object' ) );
		add_action( 'save_post_product', array( $this, 'maybe_assign_product_type_term' ), 10, 3 );
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_settings_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_settings_panel' ) );
		add_action( 'woocommerce_process_product_meta_' . self::PRODUCT_TYPE, array( $this, 'save_product_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Register auction product type label.
	 *
	 * @param array<string,string> $types Existing product types.
	 * @return array<string,string>
	 */
	public function register_product_type( $types ) {
		$types[ self::PRODUCT_TYPE ] = __( 'Auction', 'codex-ajax-auctions' );
		return $types;
	}

	/**
	 * Ensure WooCommerce treats the auction as a simple product subclass.
	 *
	 * @param string $classname Resolved class name.
	 * @param string $type      Product type slug.
	 * @return string
	 */
	public function map_product_class( $classname, $type ) {
		if ( self::PRODUCT_TYPE === $type ) {
			$classname = 'Codfaa\\WooCommerce\\Product\\WC_Product_Codfaa_Auction';
		}

		return $classname;
	}

	/**
	 * Append the auction tab and expose default tabs for auction products.
	 *
	 * @param array<string,array<string,mixed>> $tabs Tabs configuration.
	 * @return array<string,array<string,mixed>>
	 */
	public function add_settings_tab( $tabs ) {
		$auction_tab = array(
			'label'  => __( 'Auction Settings', 'codex-ajax-auctions' ),
			'target' => 'codfaa_auction_product_data',
			'class'  => array( 'show_if_' . self::PRODUCT_TYPE ),
			'priority' => 40,
		);

		$tabs['codfaa_auction'] = $auction_tab;

		$tab_keys = array( 'general', 'inventory', 'shipping', 'linked_product', 'attribute', 'advanced' );

		foreach ( $tab_keys as $tab_key ) {
			if ( isset( $tabs[ $tab_key ]['class'] ) && is_array( $tabs[ $tab_key ]['class'] ) ) {
				$tabs[ $tab_key ]['class'][] = 'show_if_' . self::PRODUCT_TYPE;
			}
		}

		return $tabs;
	}

	/**
	 * Render the custom auction settings panel in the product editor.
	 */
	public function render_settings_panel() {
		echo '<div id="codfaa_auction_product_data" class="panel woocommerce_options_panel hidden">';
		echo '<div class="options_group">';

		foreach ( $this->fields as $field_id => $config ) {
			$this->render_field( $field_id, $config );
		}

		echo '</div>';
		echo '</div>';
	}

	/**
	 * Output a single WooCommerce admin field.
	 *
	 * @param string               $field_id Unique field identifier.
	 * @param array<string,mixed> $config   Field configuration values.
	 */
	private function render_field( $field_id, $config ) {
		$value = get_post_meta( get_the_ID(), $config['key'], true );

		$args = array(
			'id'          => $field_id,
			'value'       => $value,
			'label'       => esc_html( $config['label'] ),
			'desc_tip'    => true,
			'description' => esc_html( $config['desc'] ),
			'wrapper_class' => 'show_if_' . self::PRODUCT_TYPE,
			'custom_attributes' => array(),
			'placeholder' => isset( $config['placeholder'] ) ? esc_attr( $config['placeholder'] ) : '',
		);

		if ( 'price' === $config['type'] ) {
			woocommerce_wp_text_input(
				array_merge(
					$args,
					array(
						'data_type' => 'price',
						'name'      => $config['key'],
						'class'     => 'wc_input_price short',
					)
				)
			);
			return;
		}

		$custom_attributes = array();

		if ( isset( $config['min'] ) ) {
			$custom_attributes['min'] = (int) $config['min'];
		}

		if ( isset( $config['step'] ) ) {
			$custom_attributes['step'] = (int) $config['step'];
		}

		woocommerce_wp_text_input(
			array_merge(
				$args,
				array(
					'data_type'         => 'int',
					'name'              => $config['key'],
					'class'             => 'short',
					'type'              => 'number',
					'custom_attributes' => $custom_attributes,
				)
			)
		);
	}

	/**
	 * Persist auction fields when the product is saved.
	 *
	 * @param int $post_id Product ID.
	 */
	public function save_product_settings( $post_id ) {
		foreach ( $this->fields as $config ) {
			$key = isset( $config['key'] ) ? $config['key'] : null;

			if ( ! $key ) {
				continue;
			}

			$value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

			if ( 'price' === $config['type'] ) {
				$sanitized = wc_format_decimal( $value );
			} else {
				$sanitized = max( 0, intval( $value ) );
			}

			if ( '' === $sanitized || null === $sanitized ) {
				delete_post_meta( $post_id, $key );
				continue;
			}

			update_post_meta( $post_id, $key, $sanitized );
		}
	}

	/**
	 * Enqueue admin scripts for handling the product type UI.
	 */
	public function enqueue_admin_assets() {
		$screen = get_current_screen();

		if ( ! $screen || 'product' !== $screen->id ) {
			return;
		}

		wp_enqueue_script(
			'codfaa-auction-product',
			CODFAA_PLUGIN_URL . 'assets/admin/js/auction-product.js',
			array( 'jquery', 'wc-admin-meta-boxes' ),
			\Codex_Ajax_Auctions::VERSION,
			true
		);
	}

	/**
	 * Make sure the `product_type` taxonomy is aware of our auction type.
	 */
	public function register_product_type_term() {
		if ( ! taxonomy_exists( 'product_type' ) ) {
			return;
		}

		if ( ! term_exists( self::PRODUCT_TYPE, 'product_type' ) ) {
			wp_insert_term(
				__( 'Auction', 'codex-ajax-auctions' ),
				'product_type',
				array(
					'slug' => self::PRODUCT_TYPE,
				)
			);
		}
	}

	/**
	 * Ensure the WooCommerce product object keeps the auction type when saving.
	 *
	 * @param \WC_Product $product Product object being persisted.
	 */
	public function set_product_type_on_object( $product ) {
		if ( empty( $_POST['product-type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		$type = sanitize_text_field( wp_unslash( $_POST['product-type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( self::PRODUCT_TYPE !== $type ) {
			return;
		}

		if ( method_exists( $product, 'set_props' ) ) {
			$product->set_props(
				array(
					'type' => self::PRODUCT_TYPE,
				)
			);
		}
	}

	/**
	 * Make sure the product_type taxonomy reflects the auction type on save.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an existing post.
	 */
	public function maybe_assign_product_type_term( $post_id, $post, $update ) {
		unset( $update );

		if ( empty( $_POST['product-type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		$type = sanitize_text_field( wp_unslash( $_POST['product-type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( self::PRODUCT_TYPE !== $type ) {
			return;
		}

		wp_set_object_terms( $post_id, self::PRODUCT_TYPE, 'product_type', false );
	}

	/**
	 * Build the auction field configuration.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function get_field_definitions() {
		return array(
			'codfaa_registration_fee' => array(
				'key'         => '_codfaa_registration_fee',
				'label'       => __( 'Registration Fee (credits)', 'codex-ajax-auctions' ),
				'desc'        => __( 'Credits required to register for the auction.', 'codex-ajax-auctions' ),
				'type'        => 'number',
				'placeholder' => __( 'e.g. 10', 'codex-ajax-auctions' ),
				'min'         => 0,
				'step'        => 1,
			),
			'codfaa_bid_cost'        => array(
				'key'         => '_codfaa_bid_cost',
				'label'       => __( 'Bid Cost (credits)', 'codex-ajax-auctions' ),
				'desc'        => __( 'Credits reserved for each bid placed.', 'codex-ajax-auctions' ),
				'type'        => 'number',
				'placeholder' => __( 'e.g. 2', 'codex-ajax-auctions' ),
				'min'         => 0,
				'step'        => 1,
			),
			'codfaa_required_participants' => array(
				'key'         => '_codfaa_required_participants',
				'label'       => __( 'Required Participants', 'codex-ajax-auctions' ),
				'desc'        => __( 'Number of registered users required before the auction can go live.', 'codex-ajax-auctions' ),
				'type'        => 'number',
				'placeholder' => __( 'e.g. 25', 'codex-ajax-auctions' ),
				'min'         => 1,
				'step'        => 1,
			),
			'codfaa_auction_timer'  => array(
				'key'         => '_codfaa_auction_timer',
				'label'       => __( 'Auction Timer (seconds)', 'codex-ajax-auctions' ),
				'desc'        => __( 'Countdown duration that resets on each bid.', 'codex-ajax-auctions' ),
				'type'        => 'number',
				'placeholder' => __( 'e.g. 30', 'codex-ajax-auctions' ),
				'min'         => 5,
				'step'        => 1,
			),
			'codfaa_buy_now_price'  => array(
				'key'         => '_codfaa_buy_now_price',
				'label'       => __( 'Buy It Now Price', 'codex-ajax-auctions' ),
				'desc'        => __( 'Optional fixed price to allow direct purchase (currency).', 'codex-ajax-auctions' ),
				'type'        => 'price',
				'placeholder' => __( 'e.g. 99.99', 'codex-ajax-auctions' ),
			),
		);
	}
}
