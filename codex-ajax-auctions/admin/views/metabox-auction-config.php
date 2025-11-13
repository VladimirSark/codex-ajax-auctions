<?php
/**
 * Auction configuration metabox view.
 *
 * @var int   $product_id
 * @var int   $registration_id
 * @var int   $bid_product_id
 * @var int   $required
 * @var int   $timer
 * @var array $products
 */

defined( 'ABSPATH' ) || exit;
?>
<table class="form-table codfaa-auction-config">
	<tbody>
		<tr>
			<th scope="row"><label for="codfaa_product_id"><?php esc_html_e( 'Auction Product', 'codex-ajax-auctions' ); ?></label></th>
			<td>
				<select name="codfaa_product_id" id="codfaa_product_id" class="codfaa-select widefat">
					<?php foreach ( $products as $id => $title ) : ?>
						<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $product_id, $id ); ?>><?php echo esc_html( $title ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Select the WooCommerce product that will be awarded to the winner.', 'codex-ajax-auctions' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="codfaa_registration_product_id"><?php esc_html_e( 'Registration Fee Product', 'codex-ajax-auctions' ); ?></label></th>
			<td>
				<select name="codfaa_registration_product_id" id="codfaa_registration_product_id" class="codfaa-select widefat">
					<?php foreach ( $products as $id => $title ) : ?>
						<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $registration_id, $id ); ?>><?php echo esc_html( $title ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Choose the WooCommerce product that will be added to the cart as the registration fee.', 'codex-ajax-auctions' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="codfaa_bid_product_id"><?php esc_html_e( 'Bid Fee Product', 'codex-ajax-auctions' ); ?></label></th>
			<td>
				<select name="codfaa_bid_product_id" id="codfaa_bid_product_id" class="codfaa-select widefat">
					<?php foreach ( $products as $id => $title ) : ?>
						<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $bid_product_id, $id ); ?>><?php echo esc_html( $title ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Select the WooCommerce product that represents the cost of a single bid.', 'codex-ajax-auctions' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="codfaa_required_participants"><?php esc_html_e( 'Required Participants', 'codex-ajax-auctions' ); ?></label></th>
			<td>
				<input type="number" min="0" step="1" class="small-text" name="codfaa_required_participants" id="codfaa_required_participants" value="<?php echo esc_attr( $required ); ?>" />
				<p class="description"><?php esc_html_e( 'Minimum participants needed before the auction can start.', 'codex-ajax-auctions' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="codfaa_timer_seconds"><?php esc_html_e( 'Auction Timer (seconds)', 'codex-ajax-auctions' ); ?></label></th>
			<td>
				<input type="number" min="0" step="1" class="small-text" name="codfaa_timer_seconds" id="codfaa_timer_seconds" value="<?php echo esc_attr( $timer ); ?>" />
				<p class="description"><?php esc_html_e( 'Countdown length that resets after each bid.', 'codex-ajax-auctions' ); ?></p>
			</td>
		</tr>
	</tbody>
</table>
